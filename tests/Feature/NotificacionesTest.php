<?php

namespace Tests\Feature;

use App\Mail\PlantillaCorreoMail;
use App\Models\Permission;
use App\Models\PlantillaCorreo;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// Fase 11: envío real de correo (SMTP) al ocurrir cada acción del panel.
class NotificacionesTest extends TestCase
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
            'solicitudes.ver', 'solicitudes.validar', 'solicitudes.asignar_contrasena', 'solicitudes.finalizar',
            'actuaciones.prorroga', 'actuaciones.aclaracion', 'actuaciones.ampliacion', 'actuaciones.recurso',
        ] as $clave) {
            Permission::create(['clave' => $clave, 'nombre' => $clave]);
        }

        foreach ([
            ['clave' => 'solicitud_recibida', 'evento' => 'test', 'asunto_template' => 'Notificación de recepción - Contraseña No. {{contrasena}}', 'cuerpo_template' => 'Hola {{nombre}}, su contraseña es {{contrasena}}.'],
            ['clave' => 'finalizacion', 'evento' => 'test', 'asunto_template' => 'Expediente finalizado - Contraseña No. {{contrasena}}', 'cuerpo_template' => 'Su expediente {{contrasena}} fue finalizado.'],
            ['clave' => 'resolucion_respuesta', 'evento' => 'test', 'asunto_template' => 'RESPUESTA SOLICITUD No. {{contrasena}}', 'cuerpo_template' => 'Su expediente {{contrasena}} fue resuelto.'],
            ['clave' => 'prorroga', 'evento' => 'test', 'asunto_template' => 'Notificación de Prórroga - Contraseña No. {{contrasena}}', 'cuerpo_template' => 'Señor(a) {{nombre}}, se le notifica prórroga.'],
            ['clave' => 'aclaracion_solicitada', 'evento' => 'test', 'asunto_template' => 'Solicitud de aclaración - Contraseña No. {{contrasena}}', 'cuerpo_template' => 'Se requiere aclaración, {{nombre}}.'],
            ['clave' => 'ampliacion_no_procedente', 'evento' => 'test', 'asunto_template' => 'Respuesta a ampliación - Contraseña No. {{contrasena}}', 'cuerpo_template' => 'Estimado {{nombre}}, no procede la ampliación.'],
            ['clave' => 'recurso_recibido', 'evento' => 'test', 'asunto_template' => 'Acuse de recibo - Recurso de Revisión No. {{correlativo_recurso}}', 'cuerpo_template' => 'Se registró el recurso {{correlativo_recurso}}.'],
        ] as $p) {
            PlantillaCorreo::create($p + ['activa' => true]);
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

    private function solicitud(array $overrides = []): Solicitud
    {
        $estado = SolicitudEstado::where('clave', $overrides['estado_clave'] ?? 'en_seguimiento')->first();
        $solicitante = Solicitante::create([
            'nombre' => 'Ciudadano de Prueba',
            'correo' => array_key_exists('correo', $overrides) ? $overrides['correo'] : 'ciudadano@example.com',
        ]);

        return Solicitud::create([
            'codigo_ns' => 'NS_99-2026',
            'codigo_acceso' => 'TEST-0001',
            'contrasena' => $overrides['contrasena'] ?? null,
            'solicitante_id' => $solicitante->id,
            'asunto' => 'Solicitud de prueba automatizada.',
            'medio_recepcion' => 'electronica',
            'estado_id' => $estado->id,
            'fecha_ingreso' => now()->toDateString(),
            'fecha_vencimiento' => $overrides['fecha_vencimiento'] ?? null,
        ]);
    }

    public function test_asignar_contrasena_envia_correo_real_con_placeholders_sustituidos(): void
    {
        $this->seedCatalog();
        Mail::fake();
        $user = $this->adminConPermisos(['solicitudes.ver', 'solicitudes.asignar_contrasena']);
        $solicitud = $this->solicitud();

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/contrasena", [
            'contrasena' => '77-2026',
        ]);

        $response->assertRedirect();
        $correo = $solicitud->correos_enviados()->first();
        $this->assertNotNull($correo);
        $this->assertEquals('enviado', $correo->estado_entrega);
        $this->assertEquals('ciudadano@example.com', $correo->destinatario);
        $this->assertStringContainsString('77-2026', $correo->asunto);
        $this->assertStringNotContainsString('{{contrasena}}', $correo->cuerpo);
        $this->assertStringContainsString('77-2026', $correo->cuerpo);

        Mail::assertSent(PlantillaCorreoMail::class, function (PlantillaCorreoMail $mail) {
            return str_contains($mail->asuntoCorreo, '77-2026');
        });
    }

    public function test_no_se_envia_correo_si_el_interesado_no_tiene_correo_registrado(): void
    {
        $this->seedCatalog();
        Mail::fake();
        $user = $this->adminConPermisos(['solicitudes.ver', 'solicitudes.asignar_contrasena']);
        $solicitud = $this->solicitud(['correo' => null]);

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/contrasena", [
            'contrasena' => '77-2026',
        ]);

        $response->assertRedirect();
        $this->assertEquals(0, $solicitud->correos_enviados()->count());
        Mail::assertNothingSent();
    }

    public function test_finalizar_envia_correo_de_finalizacion(): void
    {
        $this->seedCatalog();
        Mail::fake();
        $user = $this->adminConPermisos(['solicitudes.ver', 'solicitudes.finalizar']);
        $solicitud = $this->solicitud(['contrasena' => '77-2026']);

        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/finalizar");

        $correo = $solicitud->correos_enviados()->first();
        $this->assertNotNull($correo);
        $this->assertEquals('enviado', $correo->estado_entrega);
        Mail::assertSent(PlantillaCorreoMail::class, 1);
    }

    public function test_prorroga_envia_correo_de_notificacion(): void
    {
        $this->seedCatalog();
        Mail::fake();
        $user = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.prorroga']);
        $solicitud = $this->solicitud(['contrasena' => '77-2026', 'fecha_vencimiento' => now()->addDays(10)->toDateString()]);

        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/prorroga", [
            'fecha_nueva' => now()->addDays(17)->toDateString(),
            'motivo' => 'Se requiere más tiempo.',
        ]);

        $correo = $solicitud->correos_enviados()->first();
        $this->assertNotNull($correo);
        $this->assertEquals('enviado', $correo->estado_entrega);
    }

    public function test_recurso_envia_correo_con_correlativo_en_el_asunto(): void
    {
        $this->seedCatalog();
        Mail::fake();
        $user = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.recurso']);
        $solicitud = $this->solicitud(['contrasena' => '77-2026']);

        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/recurso", [
            'correlativo' => '30-2026',
            'motivo' => 'La información entregada fue incompleta.',
        ]);

        $correo = $solicitud->correos_enviados()->first();
        $this->assertNotNull($correo);
        $this->assertStringContainsString('30-2026', $correo->asunto);
        $this->assertStringContainsString('30-2026', $correo->cuerpo);
    }

    public function test_ampliacion_en_expediente_activo_no_envia_correo(): void
    {
        $this->seedCatalog();
        Mail::fake();
        $user = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.ampliacion']);
        $solicitud = $this->solicitud(['contrasena' => '77-2026', 'fecha_vencimiento' => now()->addDays(10)->toDateString()]);

        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/ampliacion", [
            'descripcion' => 'Pide información adicional.',
        ]);

        $this->assertEquals(0, $solicitud->correos_enviados()->count());
        Mail::assertNothingSent();
    }

    public function test_ampliacion_en_expediente_finalizado_envia_correo_no_regulada(): void
    {
        $this->seedCatalog();
        Mail::fake();
        $user = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.ampliacion']);
        $solicitud = $this->solicitud(['estado_clave' => 'finalizada', 'contrasena' => '77-2026']);

        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/ampliacion", [
            'descripcion' => 'Pide información después de la resolución.',
        ]);

        $correo = $solicitud->correos_enviados()->first();
        $this->assertNotNull($correo);
        $this->assertEquals('enviado', $correo->estado_entrega);
    }
}
