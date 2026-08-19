<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SolicitudActionController;
use App\Http\Controllers\Admin\SolicitudController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// --- Autenticación (Fase 3) ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// --- Panel administrativo (protegido: sesión + cuenta activa) ---
Route::middleware(['auth', 'active'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Ejemplo de ruta protegida ademas por permiso granular.
    Route::get('/usuarios', [UsuarioController::class, 'index'])
        ->middleware('permission:usuarios.gestionar')
        ->name('usuarios.index');

    // --- Solicitudes (Fase 6: recepción y validación administrativa) ---
    Route::middleware('permission:solicitudes.ver')->group(function () {
        Route::get('/solicitudes', [SolicitudController::class, 'index'])->name('solicitudes.index');
        Route::get('/solicitudes/{solicitud}', [SolicitudController::class, 'show'])->name('solicitudes.show');
    });

    Route::middleware('permission:solicitudes.validar')->group(function () {
        Route::post('/solicitudes/{solicitud}/aceptar', [SolicitudActionController::class, 'aceptar'])->name('solicitudes.aceptar');
        Route::post('/solicitudes/{solicitud}/rechazar', [SolicitudActionController::class, 'rechazar'])->name('solicitudes.rechazar');
    });

    Route::post('/solicitudes/{solicitud}/contrasena', [SolicitudActionController::class, 'asignarContrasena'])
        ->middleware('permission:solicitudes.asignar_contrasena')
        ->name('solicitudes.contrasena');

    Route::post('/solicitudes/{solicitud}/dependencia', [SolicitudActionController::class, 'asignarDependencia'])
        ->middleware('permission:solicitudes.asignar_dependencia')
        ->name('solicitudes.dependencia');

    Route::post('/solicitudes/{solicitud}/finalizar', [SolicitudActionController::class, 'finalizar'])
        ->middleware('permission:solicitudes.finalizar')
        ->name('solicitudes.finalizar');
});
