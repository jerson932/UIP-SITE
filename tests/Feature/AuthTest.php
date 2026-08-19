<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Permission;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        $rol = Rol::firstOrCreate(['nombre' => 'Administrador']);

        return User::factory()->create(array_merge([
            'password' => Hash::make('password123'),
            'role_id' => $rol->id,
            'activo' => true,
        ], $overrides));
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Iniciar sesión', false);
    }

    public function test_users_can_authenticate_with_valid_credentials(): void
    {
        $user = $this->makeUser();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = $this->makeUser();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'contraseña-incorrecta',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
    }

    public function test_inactive_user_cannot_stay_authenticated(): void
    {
        $user = $this->makeUser(['activo' => false]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_guest_is_redirected_to_login_from_admin_area(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_see_dashboard(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(200);
        $response->assertSee($user->name);
    }

    public function test_user_without_permission_gets_403_on_usuarios(): void
    {
        $rolLimitado = Rol::firstOrCreate(['nombre' => 'Consulta']);
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'role_id' => $rolLimitado->id,
            'activo' => true,
        ]);

        $response = $this->actingAs($user)->get('/admin/usuarios');
        $response->assertStatus(403);
    }

    public function test_user_with_permission_can_see_usuarios(): void
    {
        $permiso = Permission::firstOrCreate(
            ['clave' => 'usuarios.gestionar'],
            ['nombre' => 'Crear/editar usuarios, roles y permisos']
        );
        $rol = Rol::firstOrCreate(['nombre' => 'Administrador']);
        $rol->permissions()->syncWithoutDetaching([$permiso->id]);

        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'role_id' => $rol->id,
            'activo' => true,
        ]);

        $response = $this->actingAs($user)->get('/admin/usuarios');
        $response->assertStatus(200);
        $response->assertSee($user->email);
    }

    public function test_authenticated_user_visiting_login_is_sent_to_dashboard_not_looped(): void
    {
        // Regresión: antes, un usuario ya autenticado que visitaba /login
        // (middleware "guest") era redirigido a "/", y "/" a su vez
        // redirigía siempre a /login -> bucle infinito (ERR_TOO_MANY_REDIRECTS).
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get('/login');
        $response->assertRedirect(route('admin.dashboard'));

        $response = $this->actingAs($user)->get('/');
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_deactivating_a_user_mid_session_logs_them_out(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get('/admin')->assertStatus(200);

        $user->update(['activo' => false]);

        $response = $this->actingAs($user)->get('/admin');
        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
