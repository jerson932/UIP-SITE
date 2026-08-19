<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Permission;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// Fase 9: formularios reales para registrar prórroga, aclaración,
// ampliación y recurso de revisión desde el panel administrativo.
class ActuacionesTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalog(): void
    {
        foreach ([
            ['clave' => 'pendiente_validacion', 'etiqueta' => 'Pendiente de validación', 'color' => '#eda100', 'orden' => 1],
            ['clave' => 'en_seguimiento', 'etiqueta' => 'En seguimiento', 'color' => '#1baf7a', 'orden' => 2],
            ['clave' => 'aclaracion_solicitada', 'etiqueta' => 'Se requiere aclaración', 'color' => '#fab219', 'orden' => 3],
            ['clave' => 'prorroga', 'etiqueta' => 'Prórroga registrada', 'color' => '#4a3aa7', 'orden' => 4],
            ['clave' => 'recurso_revision', 'etiqueta' => 'Recurso de revisión', 'color' => '#e87ba4', 'orden' => 5],
            ['clave' => 'finalizada', 'etiqueta' => 'Finalizada', 'color' => '#52514e', 'orden' => 6, 'es_final' => true],
            ['clave' => 'rechazada', 'etiqueta' => 'Rechazada', 'color' => '#d03b3b', 'orden' => 7, 'es_final' => true],
        ] as $e) {
            SolicitudEstado::create($e);
        }

        foreach ([
            'solicitudes.ver', 'actuaciones.prorroga', 'actuaciones.aclaracion',
            'actuaciones.ampliacion', 'actuaciones.recurso',
        ] as $clave) {
            Permission::create(['clave' => $clave, 'nombre' => $clave]);
        }

        Configuracion::create(['clave' => 'plazo_aclaracion_dias_habiles', 'valor' => '2', 'descripcion' => 'test']);
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

    private function solicitudEnSeguimiento(?string $fechaVencimiento = null): Solicitud
    {
        $estado = SolicitudEstado::where('clave', 'en_seguimiento')->first();
        $solicitante = Solicitante::create(['nombre' => 'Ciudadano de Prueba', 'correo' => 'prueba@example.com']);

        return Solicitud::create([
            'codigo_ns' => 'NS_99-2026',
            'codigo_acceso' => 'TEST-0001',
            'contrasena' => $fechaVencimiento ? '99-2026' : null,
            'solicitante_id' => $solicitante->id,
            'asunto' => 'Solicitud de prueba automatizada.',
            'medio_recepcion' => 'electronica',
            'estado_id' => $estado->id,
            'fecha_ingreso' => now()->toDateString(),
            'fecha_vencimiento' => $fechaVencimiento,
        ]);
    }

    // --- Prórroga ---

    public function test_prorroga_requiere_permiso(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver']);
        $solicitud = $this->solicitudEnSeguimiento(now()->addDays(10)->toDateString());

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/prorroga", [
            'fecha_nueva' => now()->addDays(17)->toDateString(),
            'motivo' => 'Se requiere más tiempo.',
        ]);

        $response->assertStatus(403);
    }

    public function test_prorroga_no_se_puede_registrar_sin_contrasena(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.prorroga']);
        $solicitud = $this->solicitudEnSeguimiento(null);

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/prorroga", [
            'fecha_nueva' => now()->addDays(17)->toDateString(),
            'motivo' => 'Se requiere más tiempo.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals(0, $solicitud->prorrogas()->count());
    }

    public function test_prorroga_extiende_fecha_y_registra_historial(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.prorroga']);
        $fechaOriginal = now()->addDays(10)->toDateString();
        $solicitud = $this->solicitudEnSeguimiento($fechaOriginal);
        $fechaNueva = now()->addDays(17)->toDateString();

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/prorroga", [
            'fecha_nueva' => $fechaNueva,
            'motivo' => 'Se requiere más tiempo para recopilar la información.',
        ]);

        $response->assertRedirect();
        $solicitud->refresh();
        $this->assertEquals($fechaNueva, $solicitud->fecha_vencimiento->toDateString());
        $this->assertEquals('prorroga', $solicitud->estado->clave);
        $this->assertEquals(1, $solicitud->prorrogas()->count());
        $this->assertEquals($fechaOriginal, $solicitud->prorrogas()->first()->fecha_anterior->toDateString());
        $this->assertEquals(1, $solicitud->actuaciones()->where('tipo', 'prorroga')->count());
        $this->assertEquals(1, $solicitud->solicitud_historial()->count());
    }

    public function test_prorroga_rechaza_fecha_anterior_o_igual_a_la_actual(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.prorroga']);
        $fechaOriginal = now()->addDays(10)->toDateString();
        $solicitud = $this->solicitudEnSeguimiento($fechaOriginal);

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/prorroga", [
            'fecha_nueva' => $fechaOriginal,
            'motivo' => 'Motivo cualquiera.',
        ]);

        $response->assertSessionHasErrors('fecha_nueva');
        $solicitud->refresh();
        $this->assertEquals($fechaOriginal, $solicitud->fecha_vencimiento->toDateString());
    }

    // --- Aclaración ---

    public function test_aclaracion_calcula_plazo_en_dias_habiles_y_cambia_estado(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.aclaracion']);
        $solicitud = $this->solicitudEnSeguimiento(now()->addDays(10)->toDateString());

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/aclaracion", [
            'motivo' => 'Precisar el período y los programas de interés.',
        ]);

        $response->assertRedirect();
        $solicitud->refresh();
        $this->assertEquals('aclaracion_solicitada', $solicitud->estado->clave);
        $this->assertTrue((bool) $solicitud->requiere_aclaracion);

        $aclaracion = $solicitud->aclaraciones()->first();
        $this->assertNotNull($aclaracion);
        $this->assertEquals(2, $aclaracion->plazo_dias_habiles);
        $this->assertEquals('pendiente', $aclaracion->estado);
        $this->assertTrue($aclaracion->fecha_limite_respuesta->gt($aclaracion->fecha_solicitud));
        $this->assertEquals(1, $solicitud->actuaciones()->where('tipo', 'aclaracion')->count());
    }

    public function test_aclaracion_no_se_puede_pedir_antes_de_validar(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.aclaracion']);
        $estado = SolicitudEstado::where('clave', 'pendiente_validacion')->first();
        $solicitante = Solicitante::create(['nombre' => 'Ciudadano', 'correo' => 'c@example.com']);
        $solicitud = Solicitud::create([
            'codigo_ns' => 'NS_98-2026', 'codigo_acceso' => 'TEST-0002',
            'solicitante_id' => $solicitante->id, 'asunto' => 'Prueba',
            'medio_recepcion' => 'electronica', 'estado_id' => $estado->id,
            'fecha_ingreso' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/aclaracion", [
            'motivo' => 'Cualquier motivo.',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(0, $solicitud->aclaraciones()->count());
    }

    // --- Ampliación ---

    public function test_ampliacion_se_registra_como_recibida_en_expediente_activo(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.ampliacion']);
        $solicitud = $this->solicitudEnSeguimiento(now()->addDays(10)->toDateString());

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/ampliacion", [
            'descripcion' => 'Solicita información adicional sobre el mismo tema.',
        ]);

        $response->assertRedirect();
        $ampliacion = $solicitud->ampliaciones()->first();
        $this->assertEquals('recibida', $ampliacion->estado);
    }

    public function test_ampliacion_despues_de_finalizada_se_marca_no_regulada(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.ampliacion']);
        $solicitud = $this->solicitudEnSeguimiento(now()->addDays(10)->toDateString());
        $solicitud->update(['estado_id' => SolicitudEstado::where('clave', 'finalizada')->first()->id]);

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/ampliacion", [
            'descripcion' => 'Pide más información después de la resolución.',
        ]);

        $response->assertRedirect();
        $ampliacion = $solicitud->ampliaciones()->first();
        $this->assertEquals('rechazada_no_regulada', $ampliacion->estado);
        // El estado principal del expediente no debe tocarse por una ampliación.
        $solicitud->refresh();
        $this->assertEquals('finalizada', $solicitud->estado->clave);
    }

    // --- Recurso de revisión ---

    public function test_recurso_de_revision_requiere_correlativo_unico(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.recurso']);
        $solicitud = $this->solicitudEnSeguimiento(now()->addDays(10)->toDateString());

        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/recurso", [
            'correlativo' => '30-2026',
            'motivo' => 'La información entregada fue incompleta.',
        ]);

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/recurso", [
            'correlativo' => '30-2026',
            'motivo' => 'Otro motivo.',
        ]);

        $response->assertSessionHasErrors('correlativo');
        $this->assertEquals(1, $solicitud->recursos_revision()->count());
    }

    public function test_recurso_de_revision_cambia_estado_y_registra_historial(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.recurso']);
        $solicitud = $this->solicitudEnSeguimiento(now()->addDays(10)->toDateString());

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/recurso", [
            'correlativo' => '31-2026',
            'motivo' => 'La información entregada fue incompleta.',
        ]);

        $response->assertRedirect();
        $solicitud->refresh();
        $this->assertEquals('recurso_revision', $solicitud->estado->clave);
        $this->assertEquals('recibido', $solicitud->recursos_revision()->first()->estado);
        $this->assertEquals(1, $solicitud->actuaciones()->where('tipo', 'recurso_revision')->count());
    }
}
