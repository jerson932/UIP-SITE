<?php

use App\Http\Controllers\Admin\ActuacionController;
use App\Http\Controllers\Admin\ConfiguracionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentoController;
use App\Http\Controllers\Admin\ReporteController;
use App\Http\Controllers\Admin\SolicitudActionController;
use App\Http\Controllers\Admin\SolicitudController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Public\SeguimientoController;
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

// --- Portal del ciudadano (Fase 12): consulta pública de estado, sin
// iniciar sesión de administrador. No usa el middleware "guest" (ese es
// específico del guard de administradores) ni "auth" — es anónimo. ---
Route::prefix('seguimiento')->name('ciudadano.')->group(function () {
    Route::get('/', [SeguimientoController::class, 'form'])->name('seguimiento.form');
    Route::post('/', [SeguimientoController::class, 'consultar'])->name('seguimiento.consultar');

    Route::get('/documentos/{documento}/descargar', [SeguimientoController::class, 'descargarDocumento'])
        ->middleware('signed')
        ->name('documentos.descargar');
});

// --- Panel administrativo (protegido: sesión + cuenta activa) ---
Route::middleware(['auth', 'active'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Gestión de usuarios: solo roles con el permiso 'usuarios.gestionar'
    // (Administrador y Coordinador, según PermissionSeeder).
    Route::middleware('permission:usuarios.gestionar')->prefix('usuarios')->name('usuarios.')->group(function () {
        Route::get('/', [UsuarioController::class, 'index'])->name('index');
        Route::get('/crear', [UsuarioController::class, 'create'])->name('create');
        Route::post('/', [UsuarioController::class, 'store'])->name('store');
        Route::get('/{usuario}/editar', [UsuarioController::class, 'edit'])->name('edit');
        Route::post('/{usuario}', [UsuarioController::class, 'update'])->name('update');
        Route::post('/{usuario}/estado', [UsuarioController::class, 'toggleEstado'])->name('estado');
    });

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

    Route::post('/solicitudes/{solicitud}/vencimiento', [SolicitudActionController::class, 'ajustarVencimiento'])
        ->middleware('permission:solicitudes.ajustar_vencimiento')
        ->name('solicitudes.vencimiento');

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

    // --- Reportes y exportación (permiso 'reportes.exportar') ---
    Route::middleware('permission:reportes.exportar')->prefix('reportes')->name('reportes.')->group(function () {
        Route::get('/', [ReporteController::class, 'index'])->name('index');
        Route::get('/exportar', [ReporteController::class, 'exportar'])->name('exportar');
    });

    // --- Configuración general: plantillas de correo y feriados (permiso 'configuracion.gestionar') ---
    Route::middleware('permission:configuracion.gestionar')->prefix('configuracion')->name('configuracion.')->group(function () {
        Route::get('/', [ConfiguracionController::class, 'index'])->name('index');
        Route::get('/plantillas/{plantilla}/editar', [ConfiguracionController::class, 'editarPlantilla'])->name('plantillas.editar');
        Route::post('/plantillas/{plantilla}', [ConfiguracionController::class, 'actualizarPlantilla'])->name('plantillas.actualizar');
        Route::post('/feriados', [ConfiguracionController::class, 'guardarFeriado'])->name('feriados.guardar');
        Route::post('/feriados/{feriado}/eliminar', [ConfiguracionController::class, 'eliminarFeriado'])->name('feriados.eliminar');
    });
});
