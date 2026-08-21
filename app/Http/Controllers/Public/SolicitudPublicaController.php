<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Solicitante;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\SolicitudHistorial;
use App\Services\DocumentoIntakeService;
use App\Services\NotificacionService;
use App\Support\CatalogosSolicitud;
use App\Support\GeneradorSolicitud;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// Formulario público de presentación de solicitudes de información (sin
// iniciar sesión de administrador — mismo espíritu que SeguimientoController
// en cuanto a ser anónimo y no exponer ids). Es uno de los dos puntos de
// entrada para crear un expediente; el otro es el registro interno que hace
// la UIP para lo que llega físico o por correo (Admin\SolicitudController).
class SolicitudPublicaController extends Controller
{
    public function __construct(
        private NotificacionService $notificaciones,
        private DocumentoIntakeService $documentos
    ) {
    }

    public function form(): View
    {
        return view('public.solicitud-form', [
            'departamentos' => CatalogosSolicitud::DEPARTAMENTOS,
            'generos' => CatalogosSolicitud::GENEROS,
            'rangosEdad' => CatalogosSolicitud::RANGOS_EDAD,
            'paises' => CatalogosSolicitud::PAISES,
        ]);
    }

    public function store(Request $request): View|RedirectResponse
    {
        $throttleKey = 'nueva_solicitud|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $segundos = RateLimiter::availableIn($throttleKey);
            $minutos = (int) ceil($segundos / 60);

            throw ValidationException::withMessages([
                'asunto' => "Se alcanzó el límite de solicitudes desde esta conexión. Intenta de nuevo en {$minutos} minuto(s).",
            ]);
        }

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'genero' => ['nullable', 'string', 'in:'.implode(',', CatalogosSolicitud::GENEROS)],
            'rango_edad' => ['nullable', 'string', 'in:'.implode(',', CatalogosSolicitud::RANGOS_EDAD)],
            'pais' => ['required', 'string', 'in:'.implode(',', CatalogosSolicitud::PAISES)],
            'departamento' => ['nullable', 'string', 'in:'.implode(',', CatalogosSolicitud::DEPARTAMENTOS)],
            'asunto' => ['required', 'string', 'min:10', 'max:5000'],
            'documento' => ['nullable', 'file', 'mimes:'.implode(',', DocumentoIntakeService::MIMES), 'max:'.DocumentoIntakeService::MAX_KB],
        ], [
            'correo.required' => 'El correo es obligatorio: es el único respaldo para recuperar tu código de acceso si lo pierdes.',
        ]);

        $solicitud = DB::transaction(function () use ($data, $request) {
            $solicitante = Solicitante::localizarOCrear([
                'nombre' => $data['nombre'],
                'correo' => $data['correo'],
                'telefono' => $data['telefono'] ?? null,
                'genero' => $data['genero'] ?? null,
                'rango_edad' => $data['rango_edad'] ?? null,
                'pais' => $data['pais'],
                'departamento' => $data['departamento'] ?? null,
            ]);

            $estadoInicial = SolicitudEstado::where('clave', 'pendiente_validacion')->firstOrFail();

            $solicitud = Solicitud::create([
                'codigo_ns' => GeneradorSolicitud::codigoNs(),
                'codigo_acceso' => GeneradorSolicitud::codigoAcceso(),
                'solicitante_id' => $solicitante->id,
                'asunto' => $data['asunto'],
                'medio_recepcion' => 'electronica',
                'estado_id' => $estadoInicial->id,
                'fecha_ingreso' => now()->toDateString(),
            ]);

            SolicitudHistorial::create([
                'solicitud_id' => $solicitud->id,
                'tipo_actor' => 'ciudadano',
                'descripcion' => 'Solicitud presentada por el ciudadano a través del portal en línea.',
                'estado_nuevo_id' => $estadoInicial->id,
            ]);

            $documento = $this->documentos->guardar($solicitud, $request->file('documento'), true, null);
            if ($documento) {
                SolicitudHistorial::create([
                    'solicitud_id' => $solicitud->id,
                    'tipo_actor' => 'ciudadano',
                    'descripcion' => "Archivo adjunto por el ciudadano: {$documento->nombre}.",
                ]);
            }

            return $solicitud;
        });

        RateLimiter::hit($throttleKey, 3600);

        $correo = $this->notificaciones->enviar($solicitud, 'solicitud_registrada', [], null);

        // Aviso interno a la UIP (Fase 22b): "necesito que cuando llenen
        // el formulario, ese formulario me llege al correo" — se envía a
        // la dirección fija configurable en Configuración ("correo_uip"),
        // por separado del acuse de recibo que recibe el ciudadano arriba.
        $this->notificaciones->notificarNuevaSolicitudInterna($solicitud);

        return view('public.solicitud-confirmacion', [
            'solicitud' => $solicitud,
            'correoEnviado' => $correo?->estado_entrega === 'enviado',
        ]);
    }
}
