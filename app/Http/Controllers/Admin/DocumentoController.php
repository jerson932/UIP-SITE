<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Documento;
use App\Models\Solicitud;
use App\Models\SolicitudHistorial;
use App\Services\DocumentoIntakeService;
use App\Services\NotificacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Carga de documentos al expediente (spec tabla 3 "Documentos generables" +
// listado ya construido en Fase 6, pestaña "Documentos"). Los archivos se
// guardan en el disco "local" (storage/app/private, NO accesible por URL
// pública) y solo se sirven a través de download() protegido por permiso:
// así un documento marcado como no visible al ciudadano nunca queda
// expuesto, ni siquiera adivinando la ruta.
//
// Dos permisos separados, tal como ya estaban sembrados en PermissionSeeder:
// "documentos.subir" (cargar) y "documentos.publicar" (marcarlo visible al
// ciudadano) — un usuario con solo el primero sube documentos internos y
// otro con el segundo los publica, o el mismo usuario puede tener ambos.
class DocumentoController extends Controller
{
    private const MAX_KB = 10240; // 10 MB

    public function __construct(private NotificacionService $notificaciones)
    {
    }

    private function historial(Solicitud $solicitud, string $texto, bool $visibleCiudadano = true): void
    {
        SolicitudHistorial::create([
            'solicitud_id' => $solicitud->id,
            'user_id' => auth()->id(),
            'tipo_actor' => 'administrador',
            'descripcion' => $texto,
            'visible_ciudadano' => $visibleCiudadano,
        ]);
    }

    /**
     * Cuando un documento pasa a ser visible al ciudadano (al publicarlo,
     * o al subirlo ya marcado como público) se PUEDE avisar por correo con
     * el documento adjunto — reutiliza la plantilla "documentos_disponibles"
     * (sembrada desde Fase 11, nunca disparada hasta Fase 22c). A partir de
     * Fase 22c esto es opcional (checkbox "Enviar correo"), no automático:
     * "en publicar es como dando opcion de que se enviara a su correo del
     * interesado" — el envío obligatorio real ocurre al notificar/finalizar
     * el expediente (ver SolicitudActionController::finalizar()), no aquí.
     */
    private function notificarDocumentoPublicado(Solicitud $solicitud, Documento $documento): void
    {
        $this->notificaciones->enviar($solicitud, 'documentos_disponibles', [], auth()->id(), [
            'ruta_absoluta' => Storage::disk('local')->path($documento->ruta_archivo),
            'nombre' => $documento->nombre,
        ]);
    }

    /**
     * Un enlace (contacto de dependencia con cuenta propia, ver
     * EnlaceController) solo puede subir/descargar documentos de
     * expedientes asignados a SU dependencia — aunque tenga el permiso
     * 'documentos.subir'/'solicitudes.ver' vía el rol "Enlace", esta ruta
     * es compartida con el panel administrativo completo y no debe dejar
     * que adivine el ID de un expediente de otra dependencia. Un
     * administrador/coordinador normal no tiene registro "enlace" y no le
     * aplica esta restricción.
     */
    private function abortSiFueraDeAlcanceDelEnlace(Solicitud $solicitud): void
    {
        $enlace = auth()->user()?->enlace;
        if ($enlace && $solicitud->dependencia_id !== $enlace->dependencia_id) {
            abort(403, 'Este expediente no está asignado a tu dependencia.');
        }
    }

    public function store(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $this->abortSiFueraDeAlcanceDelEnlace($solicitud);

        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:'.implode(',', DocumentoIntakeService::MIMES), 'max:'.self::MAX_KB],
            'nombre' => ['nullable', 'string', 'max:255'],
            'visible_ciudadano' => ['nullable', 'boolean'],
            'enviar_correo' => ['nullable', 'boolean'],
        ]);

        $archivo = $request->file('archivo');
        $extension = strtolower($archivo->getClientOriginalExtension());
        $tipo = $extension === 'pdf' ? 'pdf' : (in_array($extension, ['doc', 'docx'], true) ? 'docx' : 'otro');

        $rutaGuardada = $archivo->store('documentos/solicitud_'.$solicitud->id, 'local');
        if ($rutaGuardada === false) {
            return back()->with('error', 'No se pudo guardar el archivo en el servidor. Intenta de nuevo.');
        }

        // Aunque el formulario mande visible_ciudadano=1, solo se respeta si
        // quien sube el archivo tiene el permiso de publicar — evita que una
        // petición manipulada publique un documento sin ese permiso.
        $visible = (bool) ($data['visible_ciudadano'] ?? false) && auth()->user()->hasPermission('documentos.publicar');

        $documento = Documento::create([
            'solicitud_id' => $solicitud->id,
            'nombre' => ($data['nombre'] ?? null) ?: $archivo->getClientOriginalName(),
            'ruta_archivo' => $rutaGuardada,
            'tipo' => $tipo,
            'visible_ciudadano' => $visible,
            'subido_por_user_id' => auth()->id(),
            'subido_por_ciudadano' => false,
        ]);

        $this->historial(
            $solicitud,
            "Documento cargado: {$documento->nombre}".($visible ? ' (visible al ciudadano).' : '.')
        );

        $mensajeCorreo = '';
        if ($visible) {
            $enviarCorreo = $request->boolean('enviar_correo', true);
            if ($enviarCorreo) {
                $this->notificarDocumentoPublicado($solicitud, $documento);
                $mensajeCorreo = ' Se notificó por correo al interesado con el documento adjunto.';
            } else {
                $mensajeCorreo = ' No se envió correo (opción desmarcada).';
            }
        }

        return back()->with('status', 'Documento cargado correctamente.'.$mensajeCorreo);
    }

    public function publicar(Request $request, Solicitud $solicitud, Documento $documento): RedirectResponse
    {
        if ($documento->solicitud_id !== $solicitud->id) {
            abort(404);
        }

        if ($documento->visible_ciudadano) {
            return back()->with('error', 'Ese documento ya es visible al ciudadano.');
        }

        $request->validate(['enviar_correo' => ['nullable', 'boolean']]);

        $documento->update(['visible_ciudadano' => true]);

        $this->historial($solicitud, "Documento publicado (visible al ciudadano): {$documento->nombre}");

        $enviarCorreo = $request->boolean('enviar_correo', true);
        if ($enviarCorreo) {
            $this->notificarDocumentoPublicado($solicitud, $documento);

            return back()->with('status', 'Documento publicado. Ya es visible para el ciudadano en su seguimiento, y se le notificó por correo con el documento adjunto.');
        }

        return back()->with('status', 'Documento publicado. Ya es visible para el ciudadano en su seguimiento. No se envió correo (opción desmarcada).');
    }

    /**
     * Revierte la publicación de un documento (Fase 22c): deja de ser
     * visible en el portal de seguimiento del ciudadano. No se puede
     * "deshacer" un correo que ya se haya enviado con este documento
     * adjunto — eso se advierte en el diálogo de confirmación del botón.
     * Queda registrado en el historial, pero solo internamente (no se
     * expone al ciudadano que un documento fue ocultado).
     */
    public function ocultar(Solicitud $solicitud, Documento $documento): RedirectResponse
    {
        if ($documento->solicitud_id !== $solicitud->id) {
            abort(404);
        }

        if (! $documento->visible_ciudadano) {
            return back()->with('error', 'Ese documento ya está oculto para el ciudadano.');
        }

        $documento->update(['visible_ciudadano' => false]);

        $this->historial($solicitud, "Documento oculto (ya no es visible al ciudadano): {$documento->nombre}", visibleCiudadano: false);

        return back()->with('status', 'Documento oculto. Ya no es visible para el ciudadano en su seguimiento.');
    }

    public function download(Solicitud $solicitud, Documento $documento): StreamedResponse
    {
        if ($documento->solicitud_id !== $solicitud->id) {
            abort(404);
        }

        $this->abortSiFueraDeAlcanceDelEnlace($solicitud);

        if (! Storage::disk('local')->exists($documento->ruta_archivo)) {
            abort(404, 'El archivo ya no está disponible en el servidor.');
        }

        return Storage::disk('local')->download($documento->ruta_archivo, $documento->nombreDescarga());
    }
}
