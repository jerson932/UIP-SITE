<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Fase 3: autenticación, roles y permisos
        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);

        // Sin esto, el middleware "guest" (RedirectIfAuthenticated) no
        // encuentra una ruta llamada "dashboard"/"home" (la nuestra se
        // llama "admin.dashboard"), así que cae a su default "/" — y como
        // "/" a su vez redirige siempre a /login, un usuario ya autenticado
        // que visita /login queda en un bucle infinito /login -> / -> /login.
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
