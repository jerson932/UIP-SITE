<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Documento;
use App\Models\Solicitud;
use App\Models\SolicitudHistorial;
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

    private function historial(Solicitud $solicitud, string $texto): void
    {
        SolicitudHistorial::create([
            'solicitud_id' => $solicitud->id,
            'user_id' => auth()->id(),
            'tipo_actor' => 'administrador',
            'descripcion' => $texto,
        ]);
    }

    public function store(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:'.self::MAX_KB],
            'nombre' => ['nullable', 'string', 'max:255'],
            'visible_ciudadano' => ['nullable', 'boolean'],
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

        return back()->with('status', 'Documento cargado correctamente.');
    }

    public function publicar(Solicitud $solicitud, Documento $documento): RedirectResponse
    {
        if ($documento->solicitud_id !== $solicitud->id) {
            abort(404);
        }

        if ($documento->visible_ciudadano) {
            return back()->with('error', 'Ese documento ya es visible al ciudadano.');
        }

        $documento->update(['visible_ciudadano' => true]);

        $this->historial($solicitud, "Documento publicado (visible al ciudadano): {$documento->nombre}");

        return back()->with('status', 'Documento publicado. Ya es visible para el ciudadano en su seguimiento.');
    }

    public function download(Solicitud $solicitud, Documento $documento): StreamedResponse
    {
        if ($documento->solicitud_id !== $solicitud->id) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($documento->ruta_archivo)) {
            abort(404, 'El archivo ya no está disponible en el servidor.');
        }

        return Storage::disk('local')->download($documento->ruta_archivo, $documento->nombre);
    }
}
