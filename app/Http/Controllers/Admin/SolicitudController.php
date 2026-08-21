<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
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
use Illuminate\View\View;

// Listado, detalle y registro de expedientes (Fase 6: recepción y
// validación administrativa) — reemplaza los datos simulados del prototipo
// HTML por consultas reales, manteniendo el mismo diseño de pantalla ya
// validado. create()/store() son el registro INTERNO que hace la UIP para
// lo que llega físico o por correo (permiso 'solicitudes.crear'); el otro
// punto de entrada es el formulario público, ver
// Public\SolicitudPublicaController.
class SolicitudController extends Controller
{
    public function __construct(
        private NotificacionService $notificaciones,
        private DocumentoIntakeService $documentos
    ) {
    }

    public function index(Request $request): View
    {
        $solicitudes = Solicitud::query()
            ->with(['solicitante', 'estado'])
            ->buscar($request->string('q')->toString())
            ->when($request->filled('estado'), fn ($q) => $q->whereHas(
                'estado',
                fn ($eq) => $eq->where('clave', $request->string('estado'))
            ))
            ->orderByDesc('fecha_ingreso')
            ->paginate(15)
            ->withQueryString();

        return view('admin.solicitudes.index', [
            'solicitudes' => $solicitudes,
            'estados' => SolicitudEstado::orderBy('orden')->get(),
            'filtroEstado' => $request->string('estado')->toString(),
            'busqueda' => $request->string('q')->toString(),
        ]);
    }

    public function show(Solicitud $solicitud): View
    {
        $solicitud->load([
            'solicitante', 'estado', 'dependencia', 'enlace',
            'solicitud_historial' => fn ($q) => $q->orderByDesc('created_at'),
            'documentos' => fn ($q) => $q->orderBy('created_at'),
            'correos_enviados' => fn ($q) => $q->orderByDesc('created_at'),
            'correos_recibidos' => fn ($q) => $q->orderByDesc('created_at'),
            'prorrogas', 'aclaraciones', 'ampliaciones', 'recursos_revision',
        ]);

        return view('admin.solicitudes.show', [
            'solicitud' => $solicitud,
            'dependencias' => Dependencia::with('enlaces')->where('activa', true)->orderBy('nombre')->get(),
            'tab' => request()->string('tab', 'seguimiento')->toString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.solicitudes.create', [
            'departamentos' => CatalogosSolicitud::DEPARTAMENTOS,
            'generos' => CatalogosSolicitud::GENEROS,
            'rangosEdad' => CatalogosSolicitud::RANGOS_EDAD,
            'medios' => CatalogosSolicitud::MEDIOS_RECEPCION,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'genero' => ['nullable', 'string', 'in:'.implode(',', CatalogosSolicitud::GENEROS)],
            'rango_edad' => ['nullable', 'string', 'in:'.implode(',', CatalogosSolicitud::RANGOS_EDAD)],
            'departamento' => ['nullable', 'string', 'in:'.implode(',', CatalogosSolicitud::DEPARTAMENTOS)],
            'medio_recepcion' => ['required', 'string', 'in:'.implode(',', array_keys(CatalogosSolicitud::MEDIOS_RECEPCION))],
            'asunto' => ['required', 'string', 'min:10', 'max:5000'],
            'documento' => ['nullable', 'file', 'mimes:'.implode(',', DocumentoIntakeService::MIMES), 'max:'.DocumentoIntakeService::MAX_KB],
        ]);

        $solicitud = DB::transaction(function () use ($data, $request) {
            $solicitante = Solicitante::localizarOCrear([
                'nombre' => $data['nombre'],
                'correo' => $data['correo'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'genero' => $data['genero'] ?? null,
                'rango_edad' => $data['rango_edad'] ?? null,
                'pais' => 'Guatemala',
                'departamento' => $data['departamento'] ?? null,
            ]);

            $estadoInicial = SolicitudEstado::where('clave', 'pendiente_validacion')->firstOrFail();

            $solicitud = Solicitud::create([
                'codigo_ns' => GeneradorSolicitud::codigoNs(),
                'codigo_acceso' => GeneradorSolicitud::codigoAcceso(),
                'solicitante_id' => $solicitante->id,
                'asunto' => $data['asunto'],
                'medio_recepcion' => $data['medio_recepcion'],
                'estado_id' => $estadoInicial->id,
                'fecha_ingreso' => now()->toDateString(),
                'creado_por_user_id' => auth()->id(),
            ]);

            SolicitudHistorial::create([
                'solicitud_id' => $solicitud->id,
                'user_id' => auth()->id(),
                'tipo_actor' => 'administrador',
                'descripcion' => 'Solicitud registrada por la UIP (recepción '.CatalogosSolicitud::MEDIOS_RECEPCION[$data['medio_recepcion']].').',
                'estado_nuevo_id' => $estadoInicial->id,
            ]);

            $documento = $this->documentos->guardar($solicitud, $request->file('documento'), false, auth()->id());
            if ($documento) {
                SolicitudHistorial::create([
                    'solicitud_id' => $solicitud->id,
                    'user_id' => auth()->id(),
                    'tipo_actor' => 'administrador',
                    'descripcion' => "Archivo adjunto al registrar la solicitud: {$documento->nombre}.",
                ]);
            }

            return $solicitud;
        });

        $correo = $solicitud->solicitante->correo
            ? $this->notificaciones->enviar($solicitud, 'solicitud_registrada', [], auth()->id())
            : null;

        return redirect()->route('admin.solicitudes.show', $solicitud)->with(
            'status',
            "Solicitud {$solicitud->codigo_ns} registrada. Código de acceso: {$solicitud->codigo_acceso}. ".$this->notificaciones->describirResultado($correo)
        );
    }
}
