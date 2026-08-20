<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Documento;
use App\Models\Solicitud;
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
    public function form(): View
    {
        return view('public.seguimiento-form');
    }

    public function consultar(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'codigo_acceso' => ['required', 'string', 'max:255'],
        ]);

        $throttleKey = 'seguimiento|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'codigo_acceso' => "Demasiados intentos. Intenta de nuevo en {$seconds} segundos.",
            ]);
        }

        $codigo = mb_strtolower(trim($data['codigo_acceso']));

        $solicitud = Solicitud::whereRaw('LOWER(codigo_acceso) = ?', [$codigo])->first();

        if (! $solicitud) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'codigo_acceso' => 'No encontramos ningún expediente con ese código de acceso. Verifícalo e intenta de nuevo.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $solicitud->load(['estado', 'solicitud_historial' => function ($q) {
            $q->orderBy('created_at');
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
        ]);
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

        if (! Storage::disk('local')->exists($documento->ruta_archivo)) {
            abort(404, 'El archivo ya no está disponible en el servidor.');
        }

        return Storage::disk('local')->download($documento->ruta_archivo, $documento->nombre);
    }
}
