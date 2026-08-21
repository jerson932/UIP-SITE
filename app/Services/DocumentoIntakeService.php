<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Solicitud;
use Illuminate\Http\UploadedFile;

// Adjunto opcional del interesado al momento de presentar la solicitud
// (pdf, Word, Excel o foto) — compartido entre el formulario público
// (Public\SolicitudPublicaController) y el registro interno
// (Admin\SolicitudController), para que ambos guarden el archivo de la
// misma forma que ya usa DocumentoController: en el disco "local"
// (storage/app/private, no accesible por URL pública), dentro de la
// carpeta de la solicitud. Queda oculto al ciudadano por defecto
// (visible_ciudadano = false), igual que cualquier otro documento — la UIP
// lo revisa y lo publica desde la pestaña "Documentos" si corresponde,
// manteniendo un único flujo de publicación en todo el sistema.
class DocumentoIntakeService
{
    public const MAX_KB = 10240; // 10 MB

    // 'csv' se agregó en Fase 20 a pedido del usuario, para que los
    // enlaces de dependencia puedan adjuntar hojas de datos sin tener que
    // convertirlas a Excel primero.
    public const MIMES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'jpg', 'jpeg', 'png'];

    public function guardar(Solicitud $solicitud, ?UploadedFile $archivo, bool $subidoPorCiudadano, ?int $userId): ?Documento
    {
        if (! $archivo) {
            return null;
        }

        $extension = strtolower($archivo->getClientOriginalExtension());
        $tipo = match (true) {
            $extension === 'pdf' => 'pdf',
            in_array($extension, ['doc', 'docx'], true) => 'docx',
            default => 'otro',
        };

        $rutaGuardada = $archivo->store('documentos/solicitud_'.$solicitud->id, 'local');
        if ($rutaGuardada === false) {
            return null;
        }

        return Documento::create([
            'solicitud_id' => $solicitud->id,
            'nombre' => $archivo->getClientOriginalName(),
            'ruta_archivo' => $rutaGuardada,
            'tipo' => $tipo,
            'visible_ciudadano' => false,
            'subido_por_user_id' => $userId,
            'subido_por_ciudadano' => $subidoPorCiudadano,
        ]);
    }
}
