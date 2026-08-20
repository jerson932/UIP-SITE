<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Enlace;
use App\Models\Permission;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SolicitudFlowTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalog(): void
    {
        foreach ([
            ['clave' => 'pendiente_validacion', 'etiqueta' => 'Pendiente de validación', 'color' => '#eda100', 'orden' => 1],
            ['clave' => 'en_seguimiento', 'etiqueta' => 'En seguimiento', 'color' => '#1baf7a', 'orden' => 2],
            ['clave' => 'finalizada', 'etiqueta' => 'Finalizada', 'color' => '#52514e', 'orden' => 3, 'es_final' => true],
            ['clave' => 'rechazada', 'etiqueta' => 'Rechazada', 'color' => '#d03b3b', 'orden' => 4, 'es_final' => true],
        ] as $e) {
            SolicitudEstado::create($e);
        }

        foreach ([
            'solicitudes.ver', 'solicitudes.validar', 'solicitudes.asignar_contrasena',
            'solicitudes.asignar_dependencia', 'solicitudes.finalizar', 'solicitudes.ajustar_vencimiento',
        ] as $clave) {
            Permission::create(['clave' => $clave, 'nombre' => $clave]);
        }
    }

    private function adminConPermisos(array $claves): User
    {
        $rol = Rol::create(['nombre' => 'TestRol']);
        $rol->permissions()->sync(Permission::whereIn('clave', $claves)->pluck('id'));

        return User::factory()->create([
            'password' => Hash::make('password123'),
            'role_id' => $rol->id,
            'activo' => true,
        ]);
    }

    private function solicitudPendiente(): Solicitud
    {
        $estado = SolicitudEstado::where('clave', 'pendiente_validacion')->first();
        $solicitante = Solicitante::create(['nombre' => 'Ciudadano de Prueba', 'correo' => 'prueba@example.com']);

        return Solicitud::create([
            'codigo_ns' => 'NS_99-2026',
            'codigo_acceso' => 'TEST-0001',
            'solicitante_id' => $solicitante->id,
            'asunto' => 'Solicitud de prueba automatizada.',
            'medio_recepcion' => 'electronica',
            'estado_id' => $estado->id,
            'fecha_ingreso' => now()->toDateString(),
        ]);
    }

    public function test_listado_requiere_permiso_solicitudes_ver(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos([]);

        $this->actingAs($user)->get('/admin/solicitudes')->assertStatus(403);
    }

    public function test_listado_muestra_solicitudes_reales(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver']);
        $solicitud = $this->solicitudPendiente();

        $response = $this->actingAs($user)->get('/admin/solicitudes');

        $response->assertStatus(200);
        $response->assertSee($solicitud->codigo_ns);
        $response->assertSee('Ciudadano de Prueba');
    }

    public function test_busqueda_filtra_por_nombre(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver']);
        $this->solicitudPendiente();

        $response = $this->actingAs($user)->get('/admin/solicitudes?q=NoExisteNadie');
        $response->assertStatus(200);
        $response->assertDontSee('NS_99-2026');

        $response = $this->actingAs($user)->get('/admin/solicitudes?q=Ciudadano');
        $response->assertSee('NS_99-2026');
    }

    public function test_aceptar_solicitud_cambia_estado_y_registra_historial(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver', 'solicitudes.validar']);
        $solicitud = $this->solicitudPendiente();

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/aceptar");

        $response->assertRedirect();
        $solicitud->refresh();
        $this->assertEquals('en_seguimiento', $solicitud->estado->clave);
        $this->assertEquals('si', $solicitud->es_informacion_publica);
        $this->assertEquals(1, $solicitud->solicitud_historial()->count());
    }

    public function test_no_se_puede_asignar_contrasena_antes_de_aceptar(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver', 'solicitudes.asignar_contrasena']);
        $solicitud = $this->solicitudPendiente();

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/contrasena", [
            'contrasena' => '99-2026',
        ]);

        $response->assertRedirect();
        $solicitud->refresh();
        $this->assertNull($solicitud->contrasena);
        $response->assertSessionHas('error');
    }

    public function test_asignar_contrasena_despues_de_aceptar(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver', 'solicitudes.validar', 'solicitudes.asignar_contrasena']);
        $solicitud = $this->solicitudPendiente();

        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/aceptar");
        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/contrasena", [
            'contrasena' => '99-2026',
        ]);

        $response->assertRedirect();
        $solicitud->refresh();
        $this->assertEquals('99-2026', $solicitud->contrasena);
        $this->assertNotNull($solicitud->fecha_vencimiento);
    }

    public function test_no_se_puede_finalizar_sin_contrasena(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver', 'solicitudes.validar', 'solicitudes.finalizar']);
        $solicitud = $this->solicitudPendiente();
        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/aceptar");

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/finalizar");

        $response->assertRedirect();
        $solicitud->refresh();
        $this->assertNotEquals('finalizada', $solicitud->estado->clave);
    }

    public function test_asignar_dependencia_y_enlace(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver', 'solicitudes.asignar_dependencia']);
        $solicitud = $this->solicitudPendiente();
        $dependencia = Dependencia::create(['nombre' => 'Dirección de Pruebas', 'activa' => true]);
        $enlace = Enlace::create(['dependencia_id' => $dependencia->id, 'nombre' => 'Enlace de Pruebas', 'activo' => true]);

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/dependencia", [
            'dependencia_id' => $dependencia->id,
            'enlace_id' => $enlace->id,
        ]);

        $response->assertRedirect();
        $solicitud->refresh();
        $this->assertEquals($dependencia->id, $solicitud->dependencia_id);
        $this->assertEquals($enlace->id, $solicitud->enlace_id);
    }

    public function test_flujo_completo_aceptar_contrasena_finalizar(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos([
            'solicitudes.ver', 'solicitudes.validar', 'solicitudes.asignar_contrasena', 'solicitudes.finalizar',
        ]);
        $solicitud = $this->solicitudPendiente();

        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/aceptar");
        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/contrasena", ['contrasena' => '99-2026']);
        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/finalizar");

        $response->assertRedirect();
        $solicitud->refresh();
        $this->assertEquals('finalizada', $solicitud->estado->clave);
        $this->assertNotNull($solicitud->fecha_finalizacion);
        $this->assertEquals(3, $solicitud->solicitud_historial()->count());
    }

    public function test_ajustar_vencimiento_requiere_permiso(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver', 'solicitudes.validar']);
        $solicitud = $this->solicitudPendiente();
        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/aceptar");

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/vencimiento", [
            'fecha_vencimiento' => now()->addDays(15)->toDateString(),
            'motivo' => 'Recurso de revisión aprobado.',
        ]);

        $response->assertStatus(403);
    }

    public function test_ajustar_vencimiento_actualiza_la_fecha_y_registra_historial(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos([
            'solicitudes.ver', 'solicitudes.validar', 'solicitudes.asignar_contrasena', 'solicitudes.ajustar_vencimiento',
        ]);
        $solicitud = $this->solicitudPendiente();
        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/aceptar");
        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/contrasena", ['contrasena' => '99-2026']);

        $nuevaFecha = now()->addDays(20)->toDateString();
        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/vencimiento", [
            'fecha_vencimiento' => $nuevaFecha,
            'motivo' => 'Recurso de revisión aprobado: +5 días hábiles adicionales.',
        ]);

        $response->assertRedirect();
        $solicitud->refresh();
        $this->assertEquals($nuevaFecha, $solicitud->fecha_vencimiento->toDateString());
        $ultimo = $solicitud->solicitud_historial()->latest('id')->first();
        $this->assertStringContainsString('ajustada manualmente', $ultimo->descripcion);
        $this->assertStringContainsString('Recurso de revisión aprobado', $ultimo->descripcion);
    }

    public function test_ajustar_vencimiento_bloqueado_en_pendiente_validacion(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver', 'solicitudes.ajustar_vencimiento']);
        $solicitud = $this->solicitudPendiente();

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/vencimiento", [
            'fecha_vencimiento' => now()->addDays(10)->toDateString(),
            'motivo' => 'Intento antes de validar.',
        ]);

        $response->assertRedirect();
        $solicitud->refresh();
        $this->assertNull($solicitud->fecha_vencimiento);
    }

    public function test_detalle_muestra_datos_del_solicitante(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver']);
        $solicitud = $this->solicitudPendiente();

        $response = $this->actingAs($user)->get("/admin/solicitudes/{$solicitud->id}");
        $response->assertStatus(200);
        $response->assertSee('Ciudadano de Prueba');
        $response->assertSee('Solicitud de prueba automatizada.');
    }
}
