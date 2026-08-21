<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\SolicitudHistorial;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Panel administrativo: resumen general (spec seccion 25/34, prototipo
// "uip_prototipo_2.html" — tarjetas de estadísticas + actividad reciente +
// solicitudes por vencer). Las consultas reutilizan las mismas reglas de
// "vencida"/"próxima a vencer" que ya usa ReporteController, para que el
// dashboard y la pantalla de Reportes nunca muestren números distintos
// para el mismo concepto.
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $porEstado = SolicitudEstado::withCount('solicitudes')
            ->orderBy('orden')
            ->get(['id', 'clave', 'etiqueta', 'color', 'es_final']);

        $totalSolicitudes = Solicitud::count();
        $pendientesValidacion = Solicitud::whereHas('estado', fn ($q) => $q->where('clave', 'pendiente_validacion'))->count();
        $enSeguimiento = Solicitud::whereHas('estado', fn ($q) => $q->where('clave', 'en_seguimiento'))->count();
        $finalizadas = Solicitud::whereHas('estado', fn ($q) => $q->where('es_final', true))->count();

        // "Vencidas"/"Por vencer" solo tienen sentido para expedientes
        // todavía activos (estado no final) que ya tienen fecha de
        // vencimiento asignada — igual que en Reportes.
        $activas = Solicitud::with(['estado'])
            ->whereHas('estado', fn ($q) => $q->where('es_final', false))
            ->whereNotNull('fecha_vencimiento')
            ->get();
        $vencidas = $activas->filter(fn ($s) => $s->diasHabilesRestantes() < 0)->count();
        $proximasAVencer = $activas->filter(fn ($s) => $s->diasHabilesRestantes() >= 0 && $s->diasHabilesRestantes() <= 2);

        $solicitudesPorVencer = $proximasAVencer
            ->sortBy(fn ($s) => $s->diasHabilesRestantes())
            ->take(6)
            ->values();

        $actividadReciente = SolicitudHistorial::with(['solicitud', 'user'])
            ->orderByDesc('created_at')
            ->take(8)
            ->get();

        return view('admin.dashboard', [
            'user' => $user,
            'totalSolicitudes' => $totalSolicitudes,
            'pendientesValidacion' => $pendientesValidacion,
            'enSeguimiento' => $enSeguimiento,
            'proximasAVencer' => $proximasAVencer->count(),
            'vencidas' => $vencidas,
            'finalizadas' => $finalizadas,
            'solicitudesPorVencer' => $solicitudesPorVencer,
            'actividadReciente' => $actividadReciente,
            'porEstado' => $porEstado,
            'permisos' => $user->rol?->permissions()->pluck('clave') ?? collect(),
        ]);
    }
}
