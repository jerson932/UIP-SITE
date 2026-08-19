<?php

use App\Http\Controllers\Admin\ActuacionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentoController;
use App\Http\Controllers\Admin\SolicitudActionController;
use App\Http\Controllers\Admin\SolicitudController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Si ya hay sesión, ir directo al panel; si no, a login. (Antes esto
    // redirigía siempre a /login, lo que causaba un bucle infinito con el
    // middleware "guest" para usuarios ya autenticados — ver bootstrap/app.php.)
    return redirect()->route(Auth::check() ? 'admin.dashboard' : 'login');
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

    // --- Actuaciones (Fase 9: prórroga, aclaración, ampliación, recurso) ---
    Route::post('/solicitudes/{solicitud}/prorroga', [ActuacionController::class, 'crearProrroga'])
        ->middleware('permission:actuaciones.prorroga')
        ->name('solicitudes.prorroga');

    Route::post('/solicitudes/{solicitud}/aclaracion', [ActuacionController::class, 'crearAclaracion'])
        ->middleware('permission:actuaciones.aclaracion')
        ->name('solicitudes.aclaracion');

    Route::post('/solicitudes/{solicitud}/ampliacion', [ActuacionController::class, 'crearAmpliacion'])
        ->middleware('permission:actuaciones.ampliacion')
        ->name('solicitudes.ampliacion');

    Route::post('/solicitudes/{solicitud}/recurso', [ActuacionController::class, 'crearRecurso'])
        ->middleware('permission:actuaciones.recurso')
        ->name('solicitudes.recurso');

    // --- Documentos (Fase 10: carga y publicación al ciudadano) ---
    Route::post('/solicitudes/{solicitud}/documentos', [DocumentoController::class, 'store'])
        ->middleware('permission:documentos.subir')
        ->name('solicitudes.documentos.store');

    Route::get('/solicitudes/{solicitud}/documentos/{documento}/descargar', [DocumentoController::class, 'download'])
        ->middleware('permission:solicitudes.ver')
        ->name('solicitudes.documentos.descargar');

    Route::post('/solicitudes/{solicitud}/documentos/{documento}/publicar', [DocumentoController::class, 'publicar'])
        ->middleware('permission:documentos.publicar')
        ->name('solicitudes.documentos.publicar');
});
