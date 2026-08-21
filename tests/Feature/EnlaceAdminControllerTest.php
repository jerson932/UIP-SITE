<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Enlace;
use App\Models\Permission;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// Panel "Gestión de enlaces" (Fase 22), a pedido del usuario: "quisiera
// tener un panel para administrar a mis enlaces, darle la url y que
// puedan iniciar sesion en su perfil". Antes de este panel, crear un
// usuario con rol "Enlace" desde el panel general de Usuarios no lo
// vinculaba a un registro Enlace real (Enlace.user_id se quedaba null),
// así que el enlace nuevo recibía 403 al entrar a su portal
// (EnlaceController lee $request->user()->enlace). Este panel resuelve
// eso de raíz: crear el acceso desde aquí siempre deja Enlace.user_id
// correctamente enlazado.
class EnlaceAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Permission::firstOrCreate(['clave' => 'usuarios.gestionar'], ['nombre' => 'x']);
        $rol = Rol::firstOrCreate(['nombre' => 'AdminTest']);
        $rol->permissions()->sync(Permission::whereIn('clave', ['usuarios.gestionar'])->pluck('id'));

        return User::factory()->create(['password' => Hash::make('password123'), 'role_id' => $rol->id, 'activo' => true]);
    }

    private function dependencia(): Dependencia
    {
        return Dependencia::create(['codigo' => 'TEST', 'nombre' => 'Dependencia de Prueba', 'activa' => true]);
    }

    public function test_requiere_permiso_usuarios_gestionar(): void
    {
        $rol = Rol::create(['nombre' => 'SinPermiso']);
        $user = User::factory()->create(['password' => Hash::make('password123'), 'role_id' => $rol->id, 'activo' => true]);

        $response = $this->actingAs($user)->get('/admin/enlaces');

        $response->assertStatus(403);
    }

    public function test_crea_un_enlace_sin_acceso_de_inicio_de_sesion(): void
    {
        $admin = $this->admin();
        $dep = $this->dependencia();

        $response = $this->actingAs($admin)->post('/admin/enlaces', [
            'dependencia_id' => $dep->id,
            'nombre' => 'Lic. Contacto de Prueba',
            'correo' => 'contacto@example.com',
        ]);

        $response->assertRedirect(route('admin.enlaces.index'));
        $enlace = Enlace::where('nombre', 'Lic. Contacto de Prueba')->first();
        $this->assertNotNull($enlace);
        $this->assertNull($enlace->user_id);
    }

    public function test_crear_acceso_crea_usuario_vinculado_y_puede_iniciar_sesion(): void
    {
        Rol::create(['nombre' => 'Enlace']);
        Permission::firstOrCreate(['clave' => 'enlace.ver_asignadas'], ['nombre' => 'x']);
        Rol::where('nombre', 'Enlace')->first()->permissions()->sync(Permission::where('clave', 'enlace.ver_asignadas')->pluck('id'));

        $admin = $this->admin();
        $dep = $this->dependencia();
        $enlace = Enlace::create(['dependencia_id' => $dep->id, 'nombre' => 'Lic. Nuevo Enlace', 'activo' => true]);

        $response = $this->actingAs($admin)->post("/admin/enlaces/{$enlace->id}/acceso", [
            'email' => 'nuevo.enlace@example.com',
        ]);

        $response->assertRedirect(route('admin.enlaces.edit', $enlace));
        $response->assertSessionHas('acceso_generado');

        $enlace->refresh();
        $this->assertNotNull($enlace->user_id);
        $this->assertEquals('nuevo.enlace@example.com', $enlace->user->email);
        $this->assertEquals('Enlace', $enlace->user->rol->nombre);
        $this->assertEquals($dep->id, $enlace->user->dependencia_id);

        $password = session('acceso_generado')['password'];

        // El acceso recién creado debe poder iniciar sesión de verdad y
        // llegar a su portal sin el 403 que reportó el usuario.
        $this->post('/logout');
        $login = $this->post('/login', ['email' => 'nuevo.enlace@example.com', 'password' => $password]);
        $login->assertRedirect();

        $portal = $this->get('/admin/enlace');
        $portal->assertOk();
    }

    public function test_crear_acceso_sobre_un_enlace_que_ya_tiene_cuenta_reinicia_la_contrasena(): void
    {
        Rol::create(['nombre' => 'Enlace']);
        $admin = $this->admin();
        $dep = $this->dependencia();

        $usuarioExistente = User::factory()->create(['email' => 'ya.tiene@example.com', 'password' => Hash::make('viejapass'), 'activo' => true]);
        $enlace = Enlace::create(['dependencia_id' => $dep->id, 'user_id' => $usuarioExistente->id, 'nombre' => 'Enlace Existente', 'activo' => true]);

        $response = $this->actingAs($admin)->post("/admin/enlaces/{$enlace->id}/acceso", [
            'email' => 'ya.tiene@example.com',
        ]);

        $response->assertRedirect(route('admin.enlaces.edit', $enlace));
        $this->assertEquals(1, User::where('email', 'ya.tiene@example.com')->count());
        $enlace->refresh();
        $this->assertEquals($usuarioExistente->id, $enlace->user_id);
    }

    public function test_editar_enlace_actualiza_dependencia_y_datos(): void
    {
        $admin = $this->admin();
        $dep1 = $this->dependencia();
        $dep2 = Dependencia::create(['codigo' => 'TEST2', 'nombre' => 'Otra Dependencia', 'activa' => true]);
        $enlace = Enlace::create(['dependencia_id' => $dep1->id, 'nombre' => 'Nombre Original', 'activo' => true]);

        $response = $this->actingAs($admin)->post("/admin/enlaces/{$enlace->id}", [
            'dependencia_id' => $dep2->id,
            'nombre' => 'Nombre Actualizado',
            'correo' => 'actualizado@example.com',
            'activo' => '1',
        ]);

        $response->assertRedirect(route('admin.enlaces.index'));
        $enlace->refresh();
        $this->assertEquals($dep2->id, $enlace->dependencia_id);
        $this->assertEquals('Nombre Actualizado', $enlace->nombre);
    }
}
