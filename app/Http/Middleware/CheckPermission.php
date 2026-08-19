<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Uso en rutas: ->middleware('permission:solicitudes.validar')
// o con varias claves (basta con tener una): ->middleware('permission:solicitudes.ver,solicitudes.validar')
// Se registra como alias 'permission'.
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$claves): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! $user->hasAnyPermission($claves)) {
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }

        return $next($request);
    }
}
