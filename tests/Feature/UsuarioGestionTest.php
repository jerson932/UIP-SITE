<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Log;
use App\Models\Permission;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// Gestión de usuarios (crear, editar, asignar rol/dependencia,
// activar/desactivar), gated por el permiso 'usuarios.gestionar'.
class UsuarioGestionTest extends TestCase
{
    use RefreshDatabase;

    private function adminConPermisos(array $claves): User
    {
        foreach ($claves as $clave) {
            Permission::firstOrCreate(['clave' => $clave], ['nombre' => $clave]);
        }

        $rol = Rol::create(['nombre' => 'TestRol']);
        $rol->permissions()->sync(Permission::whereIn('clave', $claves)->pluck('id'));

        return User::factory()->create([
            'password' => Hash::make('password123'),
            'role_id' => $rol->id,
            'activo' => true,
        ]);
    }

    public function test_listado_requiere_permiso_usuarios_gestionar(): void
    {
        $user = $this->adminConPermisos([]);

        $this->actingAs($user)->get('/admin/usuarios')->assertStatus(403);
    }

    public function test_crear_usuario(): void
    {
        $admin = $this->adminConPermisos(['usuarios.gestionar']);
        $rolDestino = Rol::create(['nombre' => 'Usuario UIP']);
        $dependencia = Dependencia::create(['nombre' => 'Dirección de Pruebas', 'activa' => true]);

        $response = $this->actingAs($admin)->post('/admin/usuarios', [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo.usuario@example.com',
            'password' => 'clave12345',
            'role_id' => $rolDestino->id,
            'dependencia_id' => $dependencia->id,
        ]);

        $response->assertRedirect(route('admin.usuarios.index'));
        $creado = User::where('email', 'nuevo.usuario@example.com')->first();
        $this->assertNotNull($creado);
        $this->assertEquals($rolDestino->id, $creado->role_id);
        $this->assertEquals($dependencia->id, $creado->dependencia_id);
        $this->assertTrue($creado->activo);
        $this->assertTrue(Hash::check('clave12345', $creado->password));
        $this->assertEquals(1, Log::where('accion', 'usuario.creado')->where('entidad_id', $creado->id)->count());
    }

    public function test_no_se_puede_crear_usuario_con_correo_duplicado(): void
    {
        $admin = $this->adminConPermisos(['usuarios.gestionar']);
        $rolDestino = Rol::create(['nombre' => 'Usuario UIP']);
        User::factory()->create(['email' => 'existente@example.com']);

        $response = $this->actingAs($admin)->post('/admin/usuarios', [
            'name' => 'Duplicado',
            'email' => 'existente@example.com',
            'password' => 'clave12345',
            'role_id' => $rolDestino->id,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_editar_usuario_sin_cambiar_contrasena(): void
    {
        $admin = $this->adminConPermisos(['usuarios.gestionar']);
        $rolOriginal = Rol::create(['nombre' => 'Enlace']);
        $rolNuevo = Rol::create(['nombre' => 'Usuario UIP']);
        $usuario = User::factory()->create(['role_id' => $rolOriginal->id, 'password' => Hash::make('original123')]);

        $response = $this->actingAs($admin)->post("/admin/usuarios/{$usuario->id}", [
            'name' => 'Nombre Actualizado',
            'email' => $usuario->email,
            'password' => '',
            'role_id' => $rolNuevo->id,
        ]);

        $response->assertRedirect(route('admin.usuarios.index'));
        $usuario->refresh();
        $this->assertEquals('Nombre Actualizado', $usuario->name);
        $this->assertEquals($rolNuevo->id, $usuario->role_id);
        $this->assertTrue(Hash::check('original123', $usuario->password));
    }

    public function test_editar_usuario_cambiando_contrasena(): void
    {
        $admin = $this->adminConPermisos(['usuarios.gestionar']);
        $rol = Rol::create(['nombre' => 'Usuario UIP']);
        $usuario = User::factory()->create(['role_id' => $rol->id]);

        $this->actingAs($admin)->post("/admin/usuarios/{$usuario->id}", [
            'name' => $usuario->name,
            'email' => $usuario->email,
            'password' => 'nuevaclave123',
            'role_id' => $rol->id,
        ]);

        $usuario->refresh();
        $this->assertTrue(Hash::check('nuevaclave123', $usuario->password));
    }

    public function test_activar_y_desactivar_usuario(): void
    {
        $admin = $this->adminConPermisos(['usuarios.gestionar']);
        $rol = Rol::create(['nombre' => 'Usuario UIP']);
        $usuario = User::factory()->create(['role_id' => $rol->id, 'activo' => true]);

        $this->actingAs($admin)->post("/admin/usuarios/{$usuario->id}/estado");
        $usuario->refresh();
        $this->assertFalse($usuario->activo);

        $this->actingAs($admin)->post("/admin/usuarios/{$usuario->id}/estado");
        $usuario->refresh();
        $this->assertTrue($usuario->activo);
    }

    public function test_un_admin_no_puede_desactivar_su_propia_cuenta(): void
    {
        $admin = $this->adminConPermisos(['usuarios.gestionar']);

        $response = $this->actingAs($admin)->post("/admin/usuarios/{$admin->id}/estado");

        $response->assertRedirect();
        $admin->refresh();
        $this->assertTrue($admin->activo);
    }

    public function test_usuario_desactivado_no_puede_seguir_usando_el_panel(): void
    {
        // El toggle de estado ya lo cubre test_activar_y_desactivar_usuario;
        // aquí se prueba, por separado, que el middleware EnsureUserIsActive
        // efectivamente cierra la sesión de una cuenta con activo=false —
        // simulando directamente el estado post-desactivación, ya que
        // actingAs() reutiliza el modelo en memoria y no refleja un cambio
        // de "activo" hecho en una petición HTTP previa dentro del mismo test.
        $rol = Rol::create(['nombre' => 'Usuario UIP']);
        $usuario = User::factory()->create(['role_id' => $rol->id, 'activo' => false]);

        $response = $this->actingAs($usuario)->get('/admin');

        $response->assertRedirect(route('login'));
    }
}
