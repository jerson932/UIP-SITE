<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aclaracion;
use App\Models\Actuacion;
use App\Models\Ampliacion;
use App\Models\Configuracion;
use App\Models\Documento;
use App\Models\Feriado;
use App\Models\Prorroga;
use App\Models\RecursoRevision;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\SolicitudHistorial;
use App\Services\DocumentoIntakeService;
use App\Services\NotificacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

// Formularios reales para registrar actuaciones sobre un expediente ya
// aceptado: prórroga, aclaración al ciudadano, ampliación y recurso de
// revisión (spec tabla 7 "Registro formal de cada actuación" + tabla 13,
// "Acción manual -> El sistema debe hacer automáticamente"). Cada acción
// crea su propio registro (prorrogas/aclaraciones/ampliaciones/recursos_
// revision), una fila en "actuaciones" (el registro formal, spec tabla 7)
// y una entrada en solicitud_historial — mismo patrón que
// SolicitudActionController (Fase 6).
//
// El envío real de correo (SMTP, Fase 11) lo resuelve NotificacionService a
// partir de las plantillas ya sembradas en "plantillas_correo".
class ActuacionController extends Controller
{
    public function __construct(
        private NotificacionService $notificaciones,
        private DocumentoIntakeService $documentos
    ) {
    }

    /**
     * PDF opcional de la actuación (prórroga/aclaración/recurso, Fase 22):
     * se guarda como un Documento normal del expediente, ya visible al
     * ciudadano (se está enviando por correo de todas formas, así que
     * ocultarlo en el portal de seguimiento no tendría sentido) y queda
     * enlazado a la actuación vía su columna documento_id.
     */
    private function adjuntarDocumento(Solicitud $solicitud, Request $request, string $campo): ?Documento
    {
        $archivo = $request->file($campo);
        if (! $archivo) {
            return null;
        }

        $documento = $this->documentos->guardar($solicitud, $archivo, false, auth()->id());
        $documento?->update(['visible_ciudadano' => true]);

        return $documento;
    }

    /**
     * @return array{ruta_absoluta: string, nombre: string}|null
     */
    private function adjuntoParaCorreo(?Documento $documento): ?array
    {
        if (! $documento) {
            return null;
        }

        return [
            'ruta_absoluta' => Storage::disk('local')->path($documento->ruta_archivo),
            'nombre' => $documento->nombre,
        ];
    }

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

    private function registrarActuacion(Solicitud $solicitud, string $tipo, string $descripcion): Actuacion
    {
        return Actuacion::create([
            'solicitud_id' => $solicitud->id,
            'tipo' => $tipo,
            'iniciado_por' => 'uip',
            'user_id' => auth()->id(),
            'fecha' => now()->toDateString(),
            'descripcion' => $descripcion,
        ]);
    }

    private function cambiarEstado(Solicitud $solicitud, string $clave): void
    {
        $nuevoEstado = SolicitudEstado::where('clave', $clave)->first();
        if ($nuevoEstado && $solicitud->estado_id !== $nuevoEstado->id) {
            $solicitud->update(['estado_id' => $nuevoEstado->id]);
        }
    }

    public function crearProrroga(Request $request, Solicitud $solicitud): RedirectResponse
    {
        if (in_array($solicitud->estado?->clave, ['pendiente_validacion', 'finalizada', 'rechazada'], true)) {
            return back()->with('error', 'Solo se puede registrar una prórroga sobre un expediente en trámite.');
        }
        if (! $solicitud->fecha_vencimiento) {
            return back()->with('error', 'Debe asignarse la contraseña (y su plazo) antes de registrar una prórroga.');
        }

        $data = $request->validate([
            'fecha_nueva' => ['required', 'date', 'after:'.$solicitud->fecha_vencimiento->toDateString()],
            'motivo' => ['required', 'string', 'max:2000'],
            'documento' => ['nullable', 'file', 'mimes:pdf', 'max:'.DocumentoIntakeService::MAX_KB],
        ]);

        $estadoAnterior = $solicitud->estado_id;
        $fechaAnterior = $solicitud->fecha_vencimiento->toDateString();

        $actuacion = $this->registrarActuacion($solicitud, 'prorroga', $data['motivo']);
        $documento = $this->adjuntarDocumento($solicitud, $request, 'documento');

        Prorroga::create([
            'solicitud_id' => $solicitud->id,
            'actuacion_id' => $actuacion->id,
            'documento_id' => $documento?->id,
            'user_id' => auth()->id(),
            'fecha_anterior' => $fechaAnterior,
            'fecha_nueva' => $data['fecha_nueva'],
            'solicitada_en' => now()->toDateString(),
            'motivo' => $data['motivo'],
        ]);

        $solicitud->update(['fecha_vencimiento' => $data['fecha_nueva']]);
        $this->cambiarEstado($solicitud, 'prorroga');

        $enviarCorreo = $request->boolean('enviar_correo', true);
        $correo = $enviarCorreo
            ? $this->notificaciones->enviar($solicitud, 'prorroga', [], auth()->id(), $this->adjuntoParaCorreo($documento))
            : null;

        $this->historial(
            $solicitud,
            "Prórroga registrada: plazo extendido de {$fechaAnterior} a {$data['fecha_nueva']}. Motivo: {$data['motivo']}"
            .($documento ? " Documento adjunto: {$documento->nombre}." : ''),
            $estadoAnterior,
            $solicitud->estado_id
        );

        $mensajeCorreo = $enviarCorreo ? $this->notificaciones->describirResultado($correo) : 'No se envió correo (opción desmarcada).';

        return back()->with('status', 'Prórroga registrada. '.$mensajeCorreo);
    }

    public function crearAclaracion(Request $request, Solicitud $solicitud): RedirectResponse
    {
        if (in_array($solicitud->estado?->clave, ['pendiente_validacion', 'finalizada', 'rechazada'], true)) {
            return back()->with('error', 'Solo se puede solicitar aclaración sobre un expediente en trámite.');
        }

        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:2000'],
            'documento' => ['nullable', 'file', 'mimes:pdf', 'max:'.DocumentoIntakeService::MAX_KB],
        ]);

        $plazo = (int) (Configuracion::where('clave', 'plazo_aclaracion_dias_habiles')->value('valor') ?? 2);
        $fechaSolicitud = Carbon::today();
        $fechaLimite = Feriado::sumarDiasHabiles($fechaSolicitud->copy(), $plazo);

        $estadoAnterior = $solicitud->estado_id;
        $actuacion = $this->registrarActuacion($solicitud, 'aclaracion', $data['motivo']);
        $documento = $this->adjuntarDocumento($solicitud, $request, 'documento');

        Aclaracion::create([
            'solicitud_id' => $solicitud->id,
            'actuacion_id' => $actuacion->id,
            'documento_id' => $documento?->id,
            'user_id' => auth()->id(),
            'fecha_solicitud' => $fechaSolicitud->toDateString(),
            'plazo_dias_habiles' => $plazo,
            'fecha_limite_respuesta' => $fechaLimite->toDateString(),
            'estado' => 'pendiente',
        ]);

        $solicitud->update(['requiere_aclaracion' => true]);
        $this->cambiarEstado($solicitud, 'aclaracion_solicitada');

        $enviarCorreo = $request->boolean('enviar_correo', true);
        $correo = $enviarCorreo
            ? $this->notificaciones->enviar($solicitud, 'aclaracion_solicitada', [], auth()->id(), $this->adjuntoParaCorreo($documento))
            : null;

        $this->historial(
            $solicitud,
            "Aclaración solicitada al interesado (plazo: {$plazo} días hábiles, vence {$fechaLimite->toDateString()}). Motivo: {$data['motivo']}"
            .($documento ? " Documento adjunto: {$documento->nombre}." : ''),
            $estadoAnterior,
            $solicitud->estado_id
        );

        $mensajeCorreo = $enviarCorreo ? $this->notificaciones->describirResultado($correo) : 'No se envió correo (opción desmarcada).';

        return back()->with('status', 'Aclaración registrada. '.$mensajeCorreo);
    }

    public function crearAmpliacion(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $data = $request->validate([
            'descripcion' => ['required', 'string', 'max:2000'],
            'documento' => ['nullable', 'file', 'mimes:pdf', 'max:'.DocumentoIntakeService::MAX_KB],
        ]);

        $finalizada = $solicitud->estado?->clave === 'finalizada';
        $estado = $finalizada ? 'rechazada_no_regulada' : 'recibida';

        $this->registrarActuacion($solicitud, 'ampliacion', $data['descripcion']);
        $documento = $this->adjuntarDocumento($solicitud, $request, 'documento');

        Ampliacion::create([
            'solicitud_id' => $solicitud->id,
            'documento_id' => $documento?->id,
            'fecha_solicitud' => now()->toDateString(),
            'descripcion' => $data['descripcion'],
            'estado' => $estado,
        ]);

        // Antes de Fase 22b solo existía plantilla de correo (validada
        // contra los correos reales de la UIP) para el caso "no
        // regulada" — una ampliación sobre un expediente todavía en
        // trámite no disparaba notificación automática. El usuario pidió
        // poder adjuntar PDF y enviar correo también en ese caso, igual
        // que en Prórroga/Aclaración/Recurso de Revisión, así que se
        // agregó la plantilla "ampliacion_recibida" para ese escenario y
        // el mismo checkbox "enviar_correo" (por defecto marcado).
        $enviarCorreo = $request->boolean('enviar_correo', true);
        $plantilla = $finalizada ? 'ampliacion_no_procedente' : 'ampliacion_recibida';
        $correo = $enviarCorreo
            ? $this->notificaciones->enviar($solicitud, $plantilla, [], auth()->id(), $this->adjuntoParaCorreo($documento))
            : null;

        $this->historial(
            $solicitud,
            ($finalizada
                ? 'Ampliación recibida después de la resolución: no está regulada por la Ley de Acceso a la Información Pública. Se indicó al interesado presentar una solicitud nueva.'
                : 'Ampliación registrada: '.$data['descripcion'])
            .($documento ? " Documento adjunto: {$documento->nombre}." : '')
        );

        $mensajeCorreo = $enviarCorreo ? $this->notificaciones->describirResultado($correo) : 'No se envió correo (opción desmarcada).';

        return back()->with(
            'status',
            ($finalizada ? 'Ampliación registrada como no regulada (el expediente ya está finalizado). ' : 'Ampliación registrada. ').$mensajeCorreo
        );
    }

    public function crearRecurso(Request $request, Solicitud $solicitud): RedirectResponse
    {
        if ($solicitud->estado?->clave === 'pendiente_validacion') {
            return back()->with('error', 'La solicitud debe haber sido validada antes de registrar un recurso de revisión.');
        }

        $data = $request->validate([
            'correlativo' => ['required', 'string', 'max:255', 'unique:recursos_revision,correlativo'],
            'motivo' => ['required', 'string', 'max:2000'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:today'],
            'documento' => ['nullable', 'file', 'mimes:pdf', 'max:'.DocumentoIntakeService::MAX_KB],
        ], [
            'correlativo.unique' => 'Ese correlativo ya está asignado a otro recurso de revisión.',
        ]);

        $estadoAnterior = $solicitud->estado_id;
        $this->registrarActuacion($solicitud, 'recurso_revision', $data['motivo']);
        $documento = $this->adjuntarDocumento($solicitud, $request, 'documento');

        RecursoRevision::create([
            'solicitud_id' => $solicitud->id,
            'documento_id' => $documento?->id,
            'correlativo' => $data['correlativo'],
            'fecha_presentacion' => now()->toDateString(),
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
            'motivo' => $data['motivo'],
            'estado' => 'recibido',
        ]);

        $this->cambiarEstado($solicitud, 'recurso_revision');

        $enviarCorreo = $request->boolean('enviar_correo', true);
        $correo = $enviarCorreo
            ? $this->notificaciones->enviar($solicitud, 'recurso_recibido', [
                'correlativo_recurso' => $data['correlativo'],
            ], auth()->id(), $this->adjuntoParaCorreo($documento))
            : null;

        $this->historial(
            $solicitud,
            "Recurso de revisión No. {$data['correlativo']} registrado. Motivo: {$data['motivo']}"
            .($documento ? " Documento adjunto: {$documento->nombre}." : ''),
            $estadoAnterior,
            $solicitud->estado_id
        );

        $mensajeCorreo = $enviarCorreo ? $this->notificaciones->describirResultado($correo) : 'No se envió correo (opción desmarcada).';

        return back()->with('status', 'Recurso de revisión registrado. '.$mensajeCorreo);
    }

    /**
     * Fase 22b: cuando el propio ciudadano presenta un recurso de revisión
     * desde su portal de seguimiento (SeguimientoController::
     * solicitarRecurso()), el recurso se crea sin correlativo — este es el
     * paso donde la UIP se lo asigna manualmente. Al asignarlo se dispara
     * el mismo correo "recurso_recibido" que ya recibía un recurso creado
     * directamente por un administrador.
     */
    public function asignarCorrelativoRecurso(Request $request, Solicitud $solicitud, RecursoRevision $recurso): RedirectResponse
    {
        if ($recurso->solicitud_id !== $solicitud->id) {
            abort(404);
        }

        if ($recurso->correlativo) {
            return back()->with('error', 'Este recurso de revisión ya tiene un correlativo asignado.');
        }

        $data = $request->validate([
            'correlativo' => ['required', 'string', 'max:255', 'unique:recursos_revision,correlativo'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            'correlativo.unique' => 'Ese correlativo ya está asignado a otro recurso de revisión.',
        ]);

        $recurso->update([
            'correlativo' => $data['correlativo'],
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?? $recurso->fecha_vencimiento,
        ]);

        $this->cambiarEstado($solicitud, 'recurso_revision');

        $correo = $this->notificaciones->enviar($solicitud, 'recurso_recibido', [
            'correlativo_recurso' => $data['correlativo'],
        ], auth()->id());

        $this->historial(
            $solicitud,
            "Recurso de revisión No. {$data['correlativo']} — correlativo asignado por la UIP (recurso presentado por el interesado desde su portal de seguimiento)."
        );

        return back()->with('status', 'Correlativo asignado. '.$this->notificaciones->describirResultado($correo));
    }
}
