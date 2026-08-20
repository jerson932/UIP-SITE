<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\Log;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// Gestión de usuarios (permiso 'usuarios.gestionar', ver PermissionSeeder):
// alta, edición, asignación de rol/dependencia y activar/desactivar cuentas.
// Cada acción queda registrada en "logs" (auditoría técnica, spec: "quien
// hizo que, cuando") — esta tabla existía en el esquema desde la Fase 2 pero
// nadie la usaba todavía; es el lugar natural para este tipo de acción
// administrativa (a diferencia de solicitud_historial, que es específico de
// un expediente).
class UsuarioController extends Controller
{
    private function log(string $accion, User $usuario, array $detalle = []): void
    {
        Log::create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'entidad' => 'usuario',
            'entidad_id' => $usuario->id,
            'ip' => request()->ip(),
            'detalle' => $detalle,
        ]);
    }

    public function index(): View
    {
        $usuarios = User::with('rol', 'dependencia')->orderBy('name')->get();

        return view('admin.usuarios.index', ['usuarios' => $usuarios]);
    }

    public function create(): View
    {
        return view('admin.usuarios.create', [
            'roles' => Rol::orderBy('nombre')->get(),
            'dependencias' => Dependencia::where('activa', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'dependencia_id' => ['nullable', 'exists:dependencias,id'],
        ]);

        $usuario = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => $data['role_id'],
            'dependencia_id' => $data['dependencia_id'] ?? null,
            'activo' => true,
            'email_verified_at' => now(),
        ]);

        $this->log('usuario.creado', $usuario, ['email' => $usuario->email, 'rol' => $usuario->rol?->nombre]);

        return redirect()->route('admin.usuarios.index')->with('status', "Usuario {$usuario->name} creado.");
    }

    public function edit(User $usuario): View
    {
        return view('admin.usuarios.edit', [
            'usuario' => $usuario,
            'roles' => Rol::orderBy('nombre')->get(),
            'dependencias' => Dependencia::where('activa', true)->orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'dependencia_id' => ['nullable', 'exists:dependencias,id'],
        ]);

        $usuario->name = $data['name'];
        $usuario->email = $data['email'];
        $usuario->role_id = $data['role_id'];
        $usuario->dependencia_id = $data['dependencia_id'] ?? null;

        if (! empty($data['password'])) {
            $usuario->password = Hash::make($data['password']);
        }

        $usuario->save();

        $this->log('usuario.editado', $usuario, [
            'rol' => $usuario->rol?->nombre,
            'contrasena_cambiada' => ! empty($data['password']),
        ]);

        return redirect()->route('admin.usuarios.index')->with('status', "Usuario {$usuario->name} actualizado.");
    }

    public function toggleEstado(User $usuario): RedirectResponse
    {
        if ($usuario->id === auth()->id()) {
            return back()->with('error', 'No puedes activar/desactivar tu propia cuenta.');
        }

        $usuario->activo = ! $usuario->activo;
        $usuario->save();

        $this->log($usuario->activo ? 'usuario.activado' : 'usuario.desactivado', $usuario);

        return back()->with('status', $usuario->activo
            ? "Usuario {$usuario->name} activado."
            : "Usuario {$usuario->name} desactivado. Su sesión actual (si tenía una) se cerrará en su próxima petición.");
    }
}
