<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Actuacion;
use App\Models\Ampliacion;
use App\Models\Documento;
use App\Models\RecursoRevision;
use App\Models\Solicitud;
use App\Models\SolicitudHistorial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Portal del ciudadano (Fase 12): consulta pública y de solo lectura del
// estado de un expediente, sin iniciar sesión de administrador. El único
// "credential" es el código de acceso que se le entrega al interesado al
// recibir su solicitud (spec: cada solicitud tiene un codigo_acceso único,
// distinto de la Contraseña No. formal que se asigna después de aceptarla).
//
// No hay una "sesión de ciudadano": la consulta se resuelve y renderiza en
// la misma petición POST, sin exponer el id numérico de la solicitud en la
// URL — así no se puede simplemente enumerar /seguimiento/1, /seguimiento/2,
// etc. Los documentos publicados se descargan con un enlace firmado y con
// vencimiento corto (URL::temporarySignedRoute), no por id directo.
class SeguimientoController extends Controller
{
    public function __construct(
        private \App\Services\NotificacionService $notificaciones,
        private \App\Services\DocumentoIntakeService $documentos
    ) {
    }

    public function form(): View
    {
        return view('public.seguimiento-form');
    }

    public function consultar(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'codigo_acceso' => ['required', 'string', 'max:255'],
        ]);

        $solicitud = $this->buscarSolicitud($data['codigo_acceso'], 'seguimiento|'.$request->ip());

        return $this->resultadoView($solicitud);
    }

    /**
     * Localiza el expediente por código de acceso (sin exponer su id
     * numérico), con el mismo throttling anti-fuerza-bruta y la misma
     * comprobación de plazo vencido que ya usaba consultar() — factorizado
     * para reutilizarlo también en solicitarRecurso()/solicitarAmpliacion()
     * (Fase 22b), que reciben el código de acceso como campo oculto del
     * formulario en vez de un id en la URL.
     */
    private function buscarSolicitud(string $codigoAccesoRaw, string $throttleKey): Solicitud
    {
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'codigo_acceso' => "Demasiados intentos. Intenta de nuevo en {$seconds} segundos.",
            ]);
        }

        $codigo = mb_strtolower(trim($codigoAccesoRaw));

        $solicitud = Solicitud::whereRaw('LOWER(codigo_acceso) = ?', [$codigo])->first();

        if (! $solicitud) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'codigo_acceso' => 'No encontramos ningún expediente con ese código de acceso. Verifícalo e intenta de nuevo.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        // El código de acceso es válido, pero si el expediente ya está
        // finalizado y pasaron más de 10 días hábiles desde la
        // finalización, el portal deja de mostrar el expediente — el
        // interesado ya recibió la resolución por correo (ver
        // NotificacionService) y este es solo el plazo de consulta en
        // línea, no un segundo plazo legal.
        if ($solicitud->accesoPortalVencido()) {
            throw ValidationException::withMessages([
                'codigo_acceso' => 'El plazo para consultar este expediente en línea ya venció (10 días hábiles después de finalizado). Revisa el correo que te enviamos con la resolución, o contacta a la UIP si necesitas el documento nuevamente.',
            ]);
        }

        return $solicitud;
    }

    /**
     * Vuelve a renderizar la página de resultado de un expediente ya
     * localizado — usado tanto por consultar() como, desde Fase 22b, por
     * las acciones de autoservicio del ciudadano (recurso/ampliación), que
     * necesitan mostrar la misma página con un mensaje de confirmación o
     * error en vez de redirigir a una URL que no existe (no hay una
     * ruta GET individual por expediente: todo pasa por el código de
     * acceso).
     */
    private function resultadoView(Solicitud $solicitud, ?string $status = null, ?string $error = null): View
    {
        $solicitud->load(['estado', 'solicitud_historial' => function ($q) {
            // visible_ciudadano=false oculta deliberación interna (a qué
            // dependencia/enlace se asignó el expediente, correos ad-hoc)
            // que nunca se pensó exponer en este portal público — ver
            // migración add_visible_ciudadano_to_solicitud_historial_table.
            $q->where('visible_ciudadano', true)->orderBy('created_at');
        }]);

        $documentos = $solicitud->documentos()
            ->where('visible_ciudadano', true)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Documento $doc) {
                $doc->url_descarga = URL::temporarySignedRoute(
                    'ciudadano.documentos.descargar',
                    now()->addMinutes(30),
                    ['documento' => $doc->id]
                );

                return $doc;
            });

        return view('public.seguimiento-resultado', [
            'solicitud' => $solicitud,
            'dias' => $solicitud->diasHabilesRestantes(),
            'documentos' => $documentos,
            'status' => $status,
            'error' => $error,
        ]);
    }

    /**
     * Autoservicio (Fase 22b): el ciudadano presenta su propio recurso de
     * revisión desde el portal de seguimiento, sin iniciar sesión — crea un
     * RecursoRevision REAL (igual que si lo creara un administrador desde
     * el panel), visible y accionable desde ahí. La única diferencia es
     * que todavía no tiene número de correlativo: ese lo asigna la UIP
     * manualmente (ver ActuacionController::asignarCorrelativoRecurso),
     * igual que el resto de números "oficiales" del sistema.
     *
     * Fase 22c: un recurso de revisión solo procede una vez notificada/
     * finalizada la resolución de la solicitud — antes de eso no hay nada
     * que "recurrir" todavía. Se usa el flag "es_final" del estado (ya
     * existía en SolicitudEstado) en vez de comparar contra una sola clave
     * puntual, así cubre tanto "finalizada" como "rechazada". También
     * permite adjuntar un documento de soporte opcional (queda interno,
     * como cualquier documento subido por el ciudadano: la UIP decide si lo
     * publica desde la pestaña "Documentos").
     */
    public function solicitarRecurso(Request $request): View
    {
        $data = $request->validate([
            'codigo_acceso' => ['required', 'string', 'max:255'],
            'motivo' => ['required', 'string', 'max:2000'],
            'archivo' => ['nullable', 'file', 'mimes:'.implode(',', \App\Services\DocumentoIntakeService::MIMES), 'max:'.\App\Services\DocumentoIntakeService::MAX_KB],
        ]);

        $solicitud = $this->buscarSolicitud($data['codigo_acceso'], 'seguimiento_recurso|'.$request->ip());

        if (! $solicitud->estado?->es_final) {
            return $this->resultadoView($solicitud, null, 'Todavía no se ha notificado la resolución de tu solicitud; podrás presentar un recurso de revisión una vez recibas la respuesta.');
        }

        RateLimiter::hit('seguimiento_recurso_accion|'.$request->ip(), 3600);

        $documento = $this->documentos->guardar($solicitud, $request->file('archivo'), true, null);

        Actuacion::create([
            'solicitud_id' => $solicitud->id,
            'tipo' => 'recurso_revision',
            'iniciado_por' => 'ciudadano',
            'fecha' => now()->toDateString(),
            'descripcion' => $data['motivo'],
        ]);

        RecursoRevision::create([
            'solicitud_id' => $solicitud->id,
            'documento_id' => $documento?->id,
            'correlativo' => null,
            'fecha_presentacion' => now()->toDateString(),
            'motivo' => $data['motivo'],
            'estado' => 'recibido',
        ]);

        SolicitudHistorial::create([
            'solicitud_id' => $solicitud->id,
            'tipo_actor' => 'ciudadano',
            'descripcion' => 'El interesado presentó un recurso de revisión desde el portal de seguimiento.'
                .($documento ? " Documento adjunto: {$documento->nombre}." : '')
                .' Pendiente de que la UIP le asigne el número de correlativo.',
        ]);

        return $this->resultadoView($solicitud, 'Tu recurso de revisión fue registrado. La UIP le asignará un número de correlativo y te lo notificará por correo.');
    }

    /**
     * Autoservicio (Fase 22b): el ciudadano pide su propia ampliación desde
     * el portal de seguimiento. Crea una Ampliacion REAL, con el mismo
     * comportamiento que ya tenía el panel administrativo: si el
     * expediente ya está finalizado se registra como "no regulada" (con su
     * correo de respuesta correspondiente); si sigue en trámite se
     * registra como "recibida" y se notifica con la nueva plantilla
     * "ampliacion_recibida".
     */
    public function solicitarAmpliacion(Request $request): View
    {
        $data = $request->validate([
            'codigo_acceso' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:2000'],
            'archivo' => ['nullable', 'file', 'mimes:'.implode(',', \App\Services\DocumentoIntakeService::MIMES), 'max:'.\App\Services\DocumentoIntakeService::MAX_KB],
        ]);

        $solicitud = $this->buscarSolicitud($data['codigo_acceso'], 'seguimiento_ampliacion|'.$request->ip());

        RateLimiter::hit('seguimiento_ampliacion_accion|'.$request->ip(), 3600);

        $finalizada = $solicitud->estado?->clave === 'finalizada';
        $estado = $finalizada ? 'rechazada_no_regulada' : 'recibida';

        $documento = $this->documentos->guardar($solicitud, $request->file('archivo'), true, null);

        Actuacion::create([
            'solicitud_id' => $solicitud->id,
            'tipo' => 'ampliacion',
            'iniciado_por' => 'ciudadano',
            'fecha' => now()->toDateString(),
            'descripcion' => $data['descripcion'],
        ]);

        Ampliacion::create([
            'solicitud_id' => $solicitud->id,
            'documento_id' => $documento?->id,
            'fecha_solicitud' => now()->toDateString(),
            'descripcion' => $data['descripcion'],
            'estado' => $estado,
        ]);

        $plantilla = $finalizada ? 'ampliacion_no_procedente' : 'ampliacion_recibida';
        $this->notificaciones->enviar($solicitud, $plantilla, [], null);

        SolicitudHistorial::create([
            'solicitud_id' => $solicitud->id,
            'tipo_actor' => 'ciudadano',
            'descripcion' => ($finalizada
                ? 'El interesado solicitó una ampliación desde el portal de seguimiento después de la resolución: no está regulada por la Ley de Acceso a la Información Pública.'
                : 'El interesado solicitó una ampliación desde el portal de seguimiento: '.$data['descripcion'])
                .($documento ? " Documento adjunto: {$documento->nombre}." : ''),
        ]);

        return $this->resultadoView($solicitud, $finalizada
            ? 'Tu solicitud de ampliación fue registrada. Este expediente ya fue resuelto, así que la ampliación no está regulada por la ley — revisa el correo que te enviamos.'
            : 'Tu solicitud de ampliación fue registrada. La UIP te notificará por correo.');
    }

    public function descargarDocumento(Request $request, Documento $documento): StreamedResponse
    {
        // La firma/vencimiento del enlace ya la valida el middleware
        // "signed" en la ruta; aquí solo queda la comprobación de negocio:
        // nunca servir un documento que no esté marcado como visible al
        // ciudadano, aunque alguien conserve un enlace firmado todavía
        // vigente de antes de que se revirtiera esa publicación.
        if (! $documento->visible_ciudadano) {
            abort(404);
        }

        if ($documento->solicitud && $documento->solicitud->accesoPortalVencido()) {
            abort(403, 'El plazo para descargar documentos de este expediente ya venció.');
        }

        if (! Storage::disk('local')->exists($documento->ruta_archivo)) {
            abort(404, 'El archivo ya no está disponible en el servidor.');
        }

        return Storage::disk('local')->download($documento->ruta_archivo, $documento->nombreDescarga());
    }
}
