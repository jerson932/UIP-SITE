<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\Enlace;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\SolicitudHistorial;
use App\Services\DocumentoIntakeService;
use App\Services\DocumentoOficialService;
use App\Services\NotificacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

// Acciones administrativas manuales sobre un expediente (spec tabla 13,
// "Acción manual -> El sistema debe hacer automáticamente"). Cada método
// aquí es la contraparte real de un botón del prototipo HTML
// (aceptarSolicitud(), asignarNumero(), etc.) y deja su propia entrada en
// solicitud_historial, tal como exige la spec ("...registrar historial").
//
// El envío real de correo (SMTP, Fase 11) lo resuelve NotificacionService a
// partir de las plantillas ya sembradas en "plantillas_correo".
class SolicitudActionController extends Controller
{
    public function __construct(
        private NotificacionService $notificaciones,
        private DocumentoOficialService $documentosOficiales,
        private DocumentoIntakeService $documentos
    ) {
    }

    // $visibleCiudadano: false marca entradas de deliberación interna (a
    // qué dependencia/enlace se asignó el expediente, correos ad-hoc) que
    // no deben aparecer en el portal público de seguimiento del ciudadano
    // (Fase 22b) — ver SeguimientoController::consultar().
    private function historial(Solicitud $solicitud, string $texto, ?int $estadoAnteriorId = null, ?int $estadoNuevoId = null, bool $visibleCiudadano = true): void
    {
        SolicitudHistorial::create([
            'solicitud_id' => $solicitud->id,
            'user_id' => auth()->id(),
            'tipo_actor' => 'administrador',
            'descripcion' => $texto,
            'estado_anterior_id' => $estadoAnteriorId,
            'estado_nuevo_id' => $estadoNuevoId,
            'visible_ciudadano' => $visibleCiudadano,
        ]);
    }

    public function aceptar(Solicitud $solicitud): RedirectResponse
    {
        if ($solicitud->estado?->clave !== 'pendiente_validacion') {
            return back()->with('error', 'Esta solicitud ya fue validada.');
        }

        $estadoAnterior = $solicitud->estado_id;
        $nuevoEstado = SolicitudEstado::where('clave', 'en_seguimiento')->firstOrFail();

        $solicitud->update([
            'es_informacion_publica' => 'si',
            'es_competencia' => 'si',
            'estado_id' => $nuevoEstado->id,
        ]);

        $this->historial(
            $solicitud,
            'Solicitud aceptada como información pública y de competencia de la institución. Ya puede asignarse contraseña.',
            $estadoAnterior,
            $nuevoEstado->id
        );

        return back()->with('status', 'Solicitud aceptada. Ahora puede asignar la contraseña.');
    }

    public function rechazar(Solicitud $solicitud): RedirectResponse
    {
        if ($solicitud->estado?->clave !== 'pendiente_validacion') {
            return back()->with('error', 'Esta solicitud ya fue validada.');
        }

        $estadoAnterior = $solicitud->estado_id;
        $nuevoEstado = SolicitudEstado::where('clave', 'rechazada')->firstOrFail();

        $solicitud->update([
            'es_informacion_publica' => 'no',
            'estado_id' => $nuevoEstado->id,
            'fecha_finalizacion' => now()->toDateString(),
        ]);

        $this->historial(
            $solicitud,
            'Solicitud rechazada: no constituye información pública o no es competencia de la institución.',
            $estadoAnterior,
            $nuevoEstado->id
        );

        return back()->with('status', 'Solicitud rechazada.');
    }

    public function asignarContrasena(Request $request, Solicitud $solicitud): RedirectResponse
    {
        if (! $solicitud->puedeAsignarContrasena()) {
            return back()->with('error', 'Debe aceptarse la solicitud antes de asignar contraseña.');
        }

        if ($solicitud->contrasena) {
            return back()->with('error', 'Esta solicitud ya tiene una contraseña asignada.');
        }

        $data = $request->validate([
            'contrasena' => ['required', 'string', 'max:255', 'unique:solicitudes,contrasena'],
        ], [
            'contrasena.unique' => 'Esa contraseña ya está asignada a otro expediente.',
        ]);

        $solicitud->update([
            'contrasena' => $data['contrasena'],
            'fecha_vencimiento' => $solicitud->fecha_vencimiento ?? now()->addWeekdays(10)->toDateString(),
        ]);

        $correo = $this->notificaciones->enviar($solicitud, 'solicitud_recibida', [], auth()->id());

        $this->historial(
            $solicitud,
            "Contraseña asignada: No. {$data['contrasena']}. Plazo de 10 días hábiles notificado al interesado (prórroga posible hasta el 8vo día)."
        );

        return back()->with('status', 'Contraseña guardada. '.$this->notificaciones->describirResultado($correo));
    }

    public function asignarDependencia(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $data = $request->validate([
            'dependencia_id' => ['required', 'exists:dependencias,id'],
            'enlace_id' => ['nullable', 'exists:enlaces,id'],
        ]);

        $solicitud->update($data);

        $dependencia = Dependencia::find($data['dependencia_id']);
        $enlace = $data['enlace_id'] ?? null ? Enlace::find($data['enlace_id']) : null;

        $this->historial(
            $solicitud,
            "Asignada a {$dependencia->nombre}".($enlace ? ", enlace {$enlace->nombre}." : '.'),
            visibleCiudadano: false
        );

        // No hay una plantilla de correo sembrada para notificar al enlace
        // interno (las plantillas de "plantillas_correo" son todas hacia el
        // ciudadano) — enviar una aquí requeriría inventar un texto que no
        // se ha validado contra los correos reales de la UIP.
        return back()->with('status', 'Dependencia asignada.');
    }

    // Genera un Oficio o una Providencia de traslado hacia UNA dependencia
    // elegida en el momento (Fase 19), a partir de las plantillas .docx
    // reales (DocumentoOficialService). Cada Documento generado guarda su
    // propia dependencia_id (Fase 19b) — un expediente puede generar varios
    // oficios/providencias hacia distintas dependencias a lo largo del
    // tiempo, ninguno reemplaza al anterior. Pero generar uno SÍ actualiza
    // la asignación "actual" del expediente (solicitud.dependencia_id/
    // enlace_id): generar un oficio/providencia hacia una dependencia es,
    // en la práctica, asignarle el expediente para que busque la
    // información — antes eso solo lo hacía el botón separado "Asignar" de
    // la tarjeta "Dependencia y Enlace", y el usuario pidió que fuera
    // automático.
    // RC/No. de oficio/providencia son manuales — el sistema no los
    // inventa, igual que el resto de números "oficiales" en este
    // expediente (spec: "el número oficial ... lo asigna manualmente el
    // administrador"). FOLIO es la excepción: una vez asignado en el
    // primer oficio/providencia del expediente, ya no cambia (lo aplica
    // DocumentoOficialService::generar()).
    public function generarDocumentoOficial(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $dependencia = Dependencia::find($request->input('dependencia_id'));
        if (! $dependencia) {
            return back()->with('error', 'Selecciona la dependencia a la que se dirige el documento.');
        }

        $tipo = $this->documentosOficiales->tipoParaDependencia($dependencia);

        $data = $request->validate([
            'dependencia_id' => ['required', 'exists:dependencias,id'],
            'rc' => ['nullable', 'string', 'max:50'],
            'folio' => ['nullable', 'string', 'max:50'],
            'no_oficio' => [$tipo === 'oficio' ? 'required' : 'nullable', 'string', 'max:50'],
            'no_providencia' => [$tipo === 'providencia' ? 'required' : 'nullable', 'string', 'max:50'],
        ], [
            'no_oficio.required' => 'El número de oficio es obligatorio para esta dependencia.',
            'no_providencia.required' => 'El número de providencia es obligatorio para esta dependencia.',
        ]);

        try {
            $documento = $this->documentosOficiales->generar($solicitud, $dependencia, $data, auth()->id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $dependenciaAnteriorId = $solicitud->dependencia_id;
        $solicitud->dependencia_id = $dependencia->id;
        if ($dependenciaAnteriorId !== $dependencia->id) {
            // Cambió de dependencia: el enlace anterior (si había) ya no
            // aplica. Si la nueva dependencia tiene un único enlace activo,
            // se asigna solo; con varios (o ninguno) se deja en null para
            // que el administrador lo afine en la tarjeta "Dependencia y Enlace".
            $enlacesActivos = $dependencia->enlaces()->where('activo', true)->get();
            $solicitud->enlace_id = $enlacesActivos->count() === 1 ? $enlacesActivos->first()->id : null;
        }
        $asignacionCambio = $solicitud->isDirty(['dependencia_id', 'enlace_id']);
        if ($asignacionCambio) {
            $solicitud->save();
        }

        $this->historial(
            $solicitud,
            ($tipo === 'oficio' ? 'Oficio' : 'Providencia')." generado hacia {$dependencia->nombre}: {$documento->nombre}."
            .($asignacionCambio ? " Expediente asignado automáticamente a {$dependencia->nombre}." : ''),
            visibleCiudadano: false
        );

        return back()->with('status', ucfirst($tipo).' generado. Puede descargarlo desde la pestaña "Documentos".');
    }

    // Panel de notificación de resolución (Fase 22): además de cambiar el
    // estado a "finalizada", permite adjuntar el documento de resolución
    // (PDF) y lo envía en un correo con asunto "RESPUESTA SOLICITUD No.
    // {{contrasena}}" (plantilla "resolucion_respuesta"). El envío puede
    // desmarcarse igual que en las demás actuaciones (Prórroga, Aclaración,
    // Recurso de Revisión).
    public function finalizar(Request $request, Solicitud $solicitud): RedirectResponse
    {
        if (! $solicitud->contrasena) {
            return back()->with('error', 'No se puede finalizar un expediente sin contraseña asignada.');
        }

        $request->validate([
            'documento' => ['nullable', 'file', 'mimes:pdf', 'max:'.DocumentoIntakeService::MAX_KB],
        ]);

        $estadoAnterior = $solicitud->estado_id;
        $nuevoEstado = SolicitudEstado::where('clave', 'finalizada')->firstOrFail();

        $solicitud->update([
            'estado_id' => $nuevoEstado->id,
            'fecha_respuesta' => now()->toDateString(),
            'fecha_finalizacion' => now()->toDateString(),
        ]);

        $documento = null;
        if ($request->hasFile('documento')) {
            $documento = $this->documentos->guardar($solicitud, $request->file('documento'), false, auth()->id());
            $documento?->update(['visible_ciudadano' => true]);
        }

        $adjunto = $documento ? [
            'ruta_absoluta' => Storage::disk('local')->path($documento->ruta_archivo),
            'nombre' => $documento->nombre,
        ] : null;

        $enviarCorreo = $request->boolean('enviar_correo', true);
        $correo = $enviarCorreo
            ? $this->notificaciones->enviar($solicitud, 'resolucion_respuesta', [], auth()->id(), $adjunto)
            : null;

        $this->historial(
            $solicitud,
            'Solicitud finalizada. Información entregada al interesado.'
            .($documento ? " Documento de resolución adjunto: {$documento->nombre}." : ''),
            $estadoAnterior,
            $nuevoEstado->id
        );

        $mensajeCorreo = $enviarCorreo ? $this->notificaciones->describirResultado($correo) : 'No se envió correo (opción desmarcada).';

        return back()->with('status', 'Expediente finalizado. '.$mensajeCorreo);
    }

    // Espacio de "Enviar correo" en la pestaña Seguimiento (Fase 22):
    // correo libre (sin plantilla), para casos que no encajan en las
    // notificaciones automáticas — el destinatario por defecto es el
    // correo del interesado, pero puede cambiarse (p. ej. para reenviar a
    // otra persona). No cambia el estado del expediente, solo queda
    // registrado en la pestaña "Correos" como cualquier otro envío.
    public function enviarCorreo(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $data = $request->validate([
            'destinatario' => ['required', 'email', 'max:255'],
            'asunto' => ['required', 'string', 'max:255'],
            'cuerpo' => ['required', 'string', 'max:5000'],
            'documento' => ['nullable', 'file', 'mimes:pdf', 'max:'.DocumentoIntakeService::MAX_KB],
        ]);

        $documento = null;
        if ($request->hasFile('documento')) {
            $documento = $this->documentos->guardar($solicitud, $request->file('documento'), false, auth()->id());
            $documento?->update(['visible_ciudadano' => true]);
        }

        $adjunto = $documento ? [
            'ruta_absoluta' => Storage::disk('local')->path($documento->ruta_archivo),
            'nombre' => $documento->nombre,
        ] : null;

        $correo = $this->notificaciones->enviarLibre($solicitud, $data['destinatario'], $data['asunto'], $data['cuerpo'], auth()->id(), $adjunto);

        $this->historial(
            $solicitud,
            "Correo enviado a {$data['destinatario']}: \"{$data['asunto']}\"."
            .($documento ? " Documento adjunto: {$documento->nombre}." : ''),
            visibleCiudadano: false
        );

        return back()->with('status', 'Correo — '.$this->notificaciones->describirResultado($correo));
    }

    public function ajustarVencimiento(Request $request, Solicitud $solicitud): RedirectResponse
    {
        if ($solicitud->estado?->clave === 'pendiente_validacion') {
            return back()->with('error', 'Debe aceptarse la solicitud antes de fijar una fecha de vencimiento.');
        }

        $data = $request->validate([
            'fecha_vencimiento' => ['required', 'date'],
            'motivo' => ['required', 'string', 'max:2000'],
        ]);

        $anterior = $solicitud->fecha_vencimiento?->toDateString() ?? 'sin fecha asignada';

        $solicitud->update(['fecha_vencimiento' => $data['fecha_vencimiento']]);

        $this->historial(
            $solicitud,
            "Fecha de vencimiento ajustada manualmente de {$anterior} a {$data['fecha_vencimiento']}. Motivo: {$data['motivo']}"
        );

        return back()->with('status', 'Fecha de vencimiento actualizada.');
    }
}
