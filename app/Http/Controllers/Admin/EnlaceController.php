<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Solicitud;
use App\Models\SolicitudHistorial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Panel del enlace (Fase 20): el contacto de una dependencia (un User con
// registro Enlace propio, ver Enlace::user()/User::enlace()) entra con la
// misma sesión de administrador pero solo puede VER — no cambiar estado,
// no reasignar, no generar oficios — los expedientes que están asignados a
// SU dependencia ahora mismo (solicitud.dependencia_id), y dejar una
// observación o adjuntar un documento con la respuesta. Esto es justo lo
// que pidió el usuario: "un panel donde las unidades o dependencias o
// enlaces puedan acceder y solo visualizar el proceso de asignación
// esperando yo una observación y que ellos puedan adjuntar archivos".
//
// El permiso 'enlace.ver_asignadas' (solo el rol "Enlace" lo tiene, según
// PermissionSeeder) es lo que gatea las rutas /admin/enlace/*; el alcance
// por dependencia se aplica DENTRO de cada método (no hay forma de
// expresar "solo lo mío" en el middleware permission:*), y también se
// repite en DocumentoController::store()/download() como segunda barrera,
// por si un enlace intenta usar las rutas genéricas de documentos con el
// ID de un expediente ajeno.
class EnlaceController extends Controller
{
    /**
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException si el usuario autenticado no es un enlace de dependencia
     */
    private function miDependenciaId(Request $request): int
    {
        $enlace = $request->user()->enlace;
        if (! $enlace) {
            abort(403, 'Tu cuenta no está vinculada a ninguna dependencia como enlace.');
        }

        return $enlace->dependencia_id;
    }

    public function index(Request $request): View
    {
        $dependenciaId = $this->miDependenciaId($request);

        $solicitudes = Solicitud::query()
            ->with(['solicitante', 'estado'])
            ->where('dependencia_id', $dependenciaId)
            ->orderByDesc('fecha_ingreso')
            ->paginate(15);

        return view('admin.enlace.index', [
            'solicitudes' => $solicitudes,
            'dependencia' => $request->user()->enlace->dependencia,
        ]);
    }

    public function show(Request $request, Solicitud $solicitud): View
    {
        $dependenciaId = $this->miDependenciaId($request);

        if ($solicitud->dependencia_id !== $dependenciaId) {
            abort(403, 'Este expediente no está asignado a tu dependencia.');
        }

        $solicitud->load([
            'solicitante', 'estado',
            'solicitud_historial' => fn ($q) => $q->orderByDesc('created_at'),
            'documentos' => fn ($q) => $q->orderByDesc('created_at'),
        ]);

        return view('admin.enlace.show', [
            'solicitud' => $solicitud,
            'documentosOficiales' => $solicitud->documentos->where('dependencia_id', $dependenciaId)->whereNotNull('plantilla_id'),
            // Antes esta vista recibía $solicitud->documentos completo (TODOS
            // los documentos del expediente, sin filtrar) y lo mostraba en una
            // tarjeta "Todos los documentos del expediente" — el enlace podía
            // ver archivos subidos por otras dependencias o por la propia UIP.
            // El usuario pidió limitarlo a "los archivos que el enlace carga":
            // solo lo que el propio usuario autenticado subió.
            'documentosPropios' => $solicitud->documentos->where('subido_por_user_id', $request->user()->id),
        ]);
    }

    /**
     * La "observación" es el mecanismo por el que el enlace responde sin
     * poder tocar el estado del expediente ni ninguna otra acción interna
     * — queda como una entrada más del historial (visible también al
     * administrador en la pestaña "Historial"), igual que cualquier otra
     * nota del expediente.
     */
    public function guardarObservacion(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $dependenciaId = $this->miDependenciaId($request);

        if ($solicitud->dependencia_id !== $dependenciaId) {
            abort(403, 'Este expediente no está asignado a tu dependencia.');
        }

        $data = $request->validate([
            'observacion' => ['required', 'string', 'min:3', 'max:4000'],
        ]);

        $dependenciaNombre = $request->user()->enlace->dependencia?->nombre ?? 'la dependencia';

        SolicitudHistorial::create([
            'solicitud_id' => $solicitud->id,
            'user_id' => $request->user()->id,
            'tipo_actor' => 'administrador',
            'descripcion' => "Observación de {$dependenciaNombre} ({$request->user()->name}): {$data['observacion']}",
            // Deliberación interna entre la UIP y la dependencia que
            // responde — no es un hito que el ciudadano deba ver en su
            // portal de seguimiento (Fase 22b).
            'visible_ciudadano' => false,
        ]);

        return back()->with('status', 'Observación guardada.');
    }
}
