<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Permission;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\SolicitudHistorial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// Prueba de humo del rediseño visual (sidebar + barra superior nuevos en
// resources/views/layouts/admin.blade.php): confirma que cada pantalla del
// panel administrativo sigue compilando y respondiendo 200 con la nueva
// plantilla — un error de sintaxis Blade en el layout compartido rompería
// TODAS las pantallas a la vez, así que vale la pena cubrirlas juntas aquí
// en lugar de depender de que cada test de funcionalidad, por separado,
// visite cada ruta.
class AdminPanelViewsTest extends TestCase
{
    use RefreshDatabase;

    private function adminConTodosLosPermisos(): User
    {
        $claves = [
            'solicitudes.ver', 'solicitudes.crear', 'solicitudes.validar',
            'reportes.exportar', 'usuarios.gestionar', 'configuracion.gestionar',
        ];
        foreach ($claves as $clave) {
            Permission::firstOrCreate(['clave' => $clave], ['nombre' => $clave]);
        }

        $rol = Rol::create(['nombre' => 'TestAdmin']);
        $rol->permissions()->sync(Permission::whereIn('clave', $claves)->pluck('id'));

        return User::factory()->create([
            'password' => Hash::make('password123'),
            'role_id' => $rol->id,
            'activo' => true,
        ]);
    }

    private function solicitudDeEjemplo(): Solicitud
    {
        $estado = SolicitudEstado::create(['clave' => 'en_seguimiento', 'etiqueta' => 'En seguimiento', 'color' => '#1baf7a', 'orden' => 1, 'es_final' => false]);
        $solicitante = Solicitante::create(['nombre' => 'Ana López García', 'correo' => 'ana@example.com']);
        $solicitud = Solicitud::create([
            'codigo_ns' => 'NS_SMOKE-2026',
            'contrasena' => '1-2026',
            'codigo_acceso' => 'SMOKE-0001',
            'solicitante_id' => $solicitante->id,
            'asunto' => 'Prueba de humo del panel administrativo',
            'medio_recepcion' => 'electronica',
            'estado_id' => $estado->id,
            'fecha_ingreso' => now()->toDateString(),
        ]);

        SolicitudHistorial::create([
            'solicitud_id' => $solicitud->id,
            'tipo_actor' => 'sistema',
            'descripcion' => 'Solicitud recibida y código NS_SMOKE-2026 generado.',
        ]);

        return $solicitud;
    }

    public function test_el_dashboard_renderiza_con_el_nuevo_sidebar(): void
    {
        $admin = $this->adminConTodosLosPermisos();
        $this->solicitudDeEjemplo();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('UIP · MINGOB', false);
        $response->assertSee('Total solicitudes');
        $response->assertSee('Actividad reciente');
    }

    public function test_el_listado_de_solicitudes_renderiza(): void
    {
        $admin = $this->adminConTodosLosPermisos();
        $this->solicitudDeEjemplo();

        $response = $this->actingAs($admin)->get(route('admin.solicitudes.index'));

        $response->assertOk();
        $response->assertSee('NS_SMOKE-2026');
    }

    public function test_el_detalle_de_una_solicitud_renderiza(): void
    {
        $admin = $this->adminConTodosLosPermisos();
        $solicitud = $this->solicitudDeEjemplo();

        $response = $this->actingAs($admin)->get(route('admin.solicitudes.show', $solicitud));

        $response->assertOk();
        $response->assertSee('Expediente NS_SMOKE-2026');
    }

    public function test_el_formulario_de_registro_interno_renderiza(): void
    {
        $admin = $this->adminConTodosLosPermisos();

        $response = $this->actingAs($admin)->get(route('admin.solicitudes.create'));

        $response->assertOk();
    }

    public function test_reportes_renderiza(): void
    {
        $admin = $this->adminConTodosLosPermisos();
        $this->solicitudDeEjemplo();
        Dependencia::create(['codigo' => 'TEST', 'nombre' => 'Dependencia de prueba', 'activa' => true]);

        $response = $this->actingAs($admin)->get(route('admin.reportes.index'));

        $response->assertOk();
        $response->assertSee('Por género');
    }

    public function test_usuarios_renderiza(): void
    {
        $admin = $this->adminConTodosLosPermisos();

        $response = $this->actingAs($admin)->get(route('admin.usuarios.index'));

        $response->assertOk();
    }

    public function test_configuracion_renderiza(): void
    {
        $admin = $this->adminConTodosLosPermisos();

        $response = $this->actingAs($admin)->get(route('admin.configuracion.index'));

        $response->assertOk();
    }

    public function test_el_sidebar_solo_muestra_secciones_permitidas(): void
    {
        // Un usuario sin ningún permiso administrativo debe seguir viendo el
        // dashboard (no requiere permiso), pero sin los enlaces a
        // Solicitudes/Administración en el sidebar.
        $rol = Rol::create(['nombre' => 'SinPermisos']);
        $user = User::factory()->create(['password' => Hash::make('password123'), 'role_id' => $rol->id, 'activo' => true]);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Todas las solicitudes');
        $response->assertDontSee('Usuarios y permisos');
    }
}
