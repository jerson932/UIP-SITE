<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\Enlace;
use App\Models\Log;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// Panel de "Gestión de enlaces" (Fase 22), a pedido del usuario:
// "quisiera tener un panel para administrar a mis enlaces, darle la url y
// que puedan iniciar sesion en su perfil". Antes de esto, un enlace solo se
// podía crear indirectamente desde el panel general de Usuarios (con rol
// "Enlace"), sin quedar vinculado a un registro Enlace real — por eso el
// usuario reportó un 403 al iniciar sesión con un enlace nuevo distinto al
// de demo (EnlaceController lee $request->user()->enlace, que quedaba
// null). Este panel resuelve las dos cosas a la vez: administra el
// contacto (Enlace, un registro por dependencia) Y, opcionalmente, crea o
// reinicia su acceso de inicio de sesión, dejando siempre Enlace.user_id
// correctamente enlazado.
//
// La URL de acceso es la misma para todo el sistema (no hay subdominios
// por dependencia): /login. Al crear o reiniciar el acceso, la vista
// muestra esa URL junto con el correo y la contraseña generada para que el
// administrador se la comparta al enlace.
class EnlaceAdminController extends Controller
{
    private function log(string $accion, Enlace $enlace, array $detalle = []): void
    {
        Log::create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'entidad' => 'enlace',
            'entidad_id' => $enlace->id,
            'ip' => request()->ip(),
            'detalle' => $detalle,
        ]);
    }

    public function index(): View
    {
        $enlaces = Enlace::with(['dependencia', 'user'])->orderBy('nombre')->get();

        // Las vistas de este panel viven en admin/gestion_enlaces/ (no
        // admin/enlaces/) a propósito: el nombre "enlaces" (plural) es
        // demasiado parecido a admin/enlace/ (singular, el portal de
        // autoservicio del propio enlace, Fase 20/21) y, copiando los
        // archivos a mano, es muy fácil confundir una carpeta con la otra
        // — como pasó. Las rutas/URLs (admin.enlaces.*, /admin/enlaces) no
        // cambiaron, solo el nombre de la carpeta de vistas.
        return view('admin.gestion_enlaces.index', ['enlaces' => $enlaces]);
    }

    public function create(): View
    {
        return view('admin.gestion_enlaces.create', [
            'dependencias' => Dependencia::where('activa', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'dependencia_id' => ['required', 'exists:dependencias,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:255'],
        ]);

        $enlace = Enlace::create($data + ['activo' => true]);

        $this->log('enlace.creado', $enlace, ['dependencia' => $enlace->dependencia?->nombre]);

        return redirect()->route('admin.enlaces.index')->with('status', "Enlace {$enlace->nombre} creado. Aún no tiene acceso de inicio de sesión — puedes dárselo desde \"Editar\".");
    }

    public function edit(Enlace $enlace): View
    {
        $enlace->load('user', 'dependencia');

        return view('admin.gestion_enlaces.edit', [
            'enlace' => $enlace,
            'dependencias' => Dependencia::where('activa', true)->orderBy('nombre')->get(),
            'loginUrl' => route('login'),
        ]);
    }

    public function update(Request $request, Enlace $enlace): RedirectResponse
    {
        $data = $request->validate([
            'dependencia_id' => ['required', 'exists:dependencias,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $enlace->update([
            'dependencia_id' => $data['dependencia_id'],
            'nombre' => $data['nombre'],
            'correo' => $data['correo'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'activo' => (bool) ($data['activo'] ?? false),
        ]);

        // Si este enlace ya tiene cuenta de acceso, mantenerla consistente
        // con el contacto (mismo nombre/dependencia) — evita que el panel
        // de Usuarios y este panel queden desincronizados.
        if ($enlace->user) {
            $enlace->user->update([
                'name' => $enlace->nombre,
                'dependencia_id' => $enlace->dependencia_id,
                'activo' => $enlace->activo,
            ]);
        }

        $this->log('enlace.editado', $enlace, ['dependencia' => $enlace->dependencia?->nombre]);

        return redirect()->route('admin.enlaces.index')->with('status', "Enlace {$enlace->nombre} actualizado.");
    }

    /**
     * Crea (o, si ya existe, reinicia la contraseña de) la cuenta de acceso
     * de este enlace — el paso que faltaba para que "Enlace" como rol
     * funcionara de verdad: vincula Enlace.user_id, así EnlaceController ya
     * no responde 403 para este contacto.
     */
    public function crearAcceso(Request $request, Enlace $enlace): RedirectResponse
    {
        $data = $request->validate([
            'email' => [
                'required', 'string', 'email', 'max:255',
                $enlace->user
                    ? Rule::unique('users', 'email')->ignore($enlace->user_id)
                    : 'unique:users,email',
            ],
        ], [
            // Mensaje explícito: el proyecto corre con APP_LOCALE=es pero
            // no tiene archivos de idioma en español instalados (solo el
            // "en" que trae Laravel por defecto), así que sin este mensaje
            // a medida el error de "unique" se muestra como la clave cruda
            // "validation.unique" en vez de una frase legible.
            'email.unique' => 'Ese correo ya está en uso por otra cuenta del sistema (puede ser la tuya u otro usuario) — usa un correo distinto para el acceso de este enlace.',
        ]);

        $rolEnlace = Rol::where('nombre', 'Enlace')->first();
        if (! $rolEnlace) {
            return back()->with('error', 'No se encontró el rol "Enlace" en el catálogo de roles. Revisa la configuración de roles antes de continuar.');
        }

        // Contraseña temporal (sin símbolos, más fácil de dictar/copiar al
        // compartir el acceso) — el enlace puede cambiarla luego desde su
        // propio perfil.
        $passwordTemporal = Str::password(10, symbols: false);

        if ($enlace->user) {
            $enlace->user->update([
                'name' => $enlace->nombre,
                'email' => $data['email'],
                'password' => Hash::make($passwordTemporal),
                'role_id' => $rolEnlace->id,
                'dependencia_id' => $enlace->dependencia_id,
                'activo' => true,
            ]);
            $usuario = $enlace->user;
            $accion = 'enlace.acceso_reiniciado';
        } else {
            $usuario = User::create([
                'name' => $enlace->nombre,
                'email' => $data['email'],
                'password' => Hash::make($passwordTemporal),
                'role_id' => $rolEnlace->id,
                'dependencia_id' => $enlace->dependencia_id,
                'activo' => true,
                'email_verified_at' => now(),
            ]);
            $enlace->update(['user_id' => $usuario->id]);
            $accion = 'enlace.acceso_creado';
        }

        $this->log($accion, $enlace, ['email' => $usuario->email]);

        return redirect()->route('admin.enlaces.edit', $enlace)->with([
            'status' => "Acceso listo para {$enlace->nombre}.",
            'acceso_generado' => [
                'url' => route('login'),
                'email' => $usuario->email,
                'password' => $passwordTemporal,
            ],
        ]);
    }
}
