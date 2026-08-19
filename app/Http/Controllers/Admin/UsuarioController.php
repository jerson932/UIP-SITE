<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

// Ejemplo de ruta protegida por PERMISO (no solo por estar autenticado):
// requiere 'usuarios.gestionar', que en PermissionSeeder solo tienen
// Administrador y Coordinador. Sirve para probar CheckPermission end to end.
class UsuarioController extends Controller
{
    public function index(): View
    {
        $usuarios = User::with('rol', 'dependencia')->orderBy('name')->get();

        return view('admin.usuarios', ['usuarios' => $usuarios]);
    }
}
