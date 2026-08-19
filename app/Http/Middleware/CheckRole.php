<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Uso en rutas: ->middleware('role:Administrador') o
// ->middleware('role:Administrador,Coordinador') (basta con tener uno).
// Se registra como alias 'role'. Para permisos granulares preferir
// CheckPermission ('permission:...'); este middleware sirve para vistas
// que deben limitarse a un rol completo (spec tabla 10, "alcance").
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! $user->hasAnyRole($roles)) {
            abort(403, 'Tu rol no tiene acceso a esta sección.');
        }

        return $next($request);
    }
}
