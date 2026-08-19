<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Panel administrativo minimo para Fase 3: prueba de que el login, los
// middlewares de rol/permiso y las relaciones de la Fase 2 ya funcionan
// juntos con datos reales. La conversion completa del prototipo HTML a
// estas rutas es trabajo de las fases 6+ (Recepcion y validacion
// administrativa), no de esta fase.
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $porEstado = SolicitudEstado::withCount('solicitudes')
            ->orderBy('orden')
            ->get(['id', 'clave', 'etiqueta', 'color']);

        return view('admin.dashboard', [
            'user' => $user,
            'totalSolicitudes' => Solicitud::count(),
            'porEstado' => $porEstado,
            'permisos' => $user->rol?->permissions()->pluck('clave') ?? collect(),
        ]);
    }
}
