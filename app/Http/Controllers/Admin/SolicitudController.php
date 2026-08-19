<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Listado y detalle de expedientes (Fase 6: recepción y validación
// administrativa) — reemplaza los datos simulados del prototipo HTML por
// consultas reales, manteniendo el mismo diseño de pantalla ya validado.
class SolicitudController extends Controller
{
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
}
