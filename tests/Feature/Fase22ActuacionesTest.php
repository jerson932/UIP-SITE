<?php

namespace Tests\Feature;

use App\Mail\PlantillaCorreoMail;
use App\Models\Configuracion;
use App\Models\Permission;
use App\Models\PlantillaCorreo;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Fase 22: adjuntar PDF y controlar el envío de correo en cada actuación
// (Prórroga, Aclaración, Recurso de Revisión, Finalizar/resolución) + el
// espacio de correo libre en Seguimiento — a pedido del usuario:
// "en prorroga poder adjuntar el pdf... y cada uno que tenga opcion de
// Enviar correo... tambien un panel de notificacion de resolucion".
class Fase22ActuacionesTest extends TestCase
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
            'solicitudes.ver', 'solicitudes.finalizar', 'correos.enviar',
            'actuaciones.prorroga', 'actuaciones.aclaracion', 'actuaciones.recurso',
        ] as $clave) {
            Permission::create(['clave' => $clave, 'nombre' => $clave]);
        }

        Configuracion::create(['clave' => 'plazo_aclaracion_dias_habiles', 'valor' => '2', 'descripcion' => 'test']);

        foreach ([
            ['clave' => 'prorroga', 'evento' => 'test', 'asunto_template' => 'Prórroga Solicitud {{contrasena}}', 'cuerpo_template' => 'Hola {{nombre}}.'],
            ['clave' => 'aclaracion_solicitada', 'evento' => 'test', 'asunto_template' => 'Aclaración Solicitud No. {{contrasena}}', 'cuerpo_template' => 'Hola {{nombre}}.'],
            ['clave' => 'recurso_recibido', 'evento' => 'test', 'asunto_template' => 'Recurso Revisión No. {{correlativo_recurso}}', 'cuerpo_template' => 'Hola {{nombre}}.'],
            ['clave' => 'resolucion_respuesta', 'evento' => 'test', 'asunto_template' => 'RESPUESTA SOLICITUD No. {{contrasena}}', 'cuerpo_template' => 'Hola {{nombre}}.'],
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

    public function test_prorroga_con_pdf_adjunto_lo_enlaza_y_lo_envia_por_correo(): void
    {
        $this->seedCatalog();
        Storage::fake('local');
        Mail::fake();
        $user = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.prorroga']);
        $solicitud = $this->solicitud(['contrasena' => '1524-2026', 'fecha_vencimiento' => now()->addDays(10)->toDateString()]);

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/prorroga", [
            'fecha_nueva' => now()->addDays(17)->toDateString(),
            'motivo' => 'Se requiere más tiempo.',
            'documento' => UploadedFile::fake()->create('prorroga.pdf', 200, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $prorroga = $solicitud->prorrogas()->first();
        $this->assertNotNull($prorroga->documento_id);
        $this->assertTrue($prorroga->documento->visible_ciudadano);

        $correo = $solicitud->correos_enviados()->first();
        $this->assertNotNull($correo);
        // Con coma de miles ("1,524-2026"), igual que en los oficios/providencias.
        $this->assertStringContainsString('1,524-2026', $correo->asunto);
        $this->assertEquals(1, $correo->correo_adjuntos()->count());

        Mail::assertSent(PlantillaCorreoMail::class, fn (PlantillaCorreoMail $mail) => $mail->rutaAdjunto !== null);
    }

    public function test_prorroga_sin_marcar_enviar_correo_no_envia_pero_si_registra(): void
    {
        $this->seedCatalog();
        Mail::fake();
        $user = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.prorroga']);
        $solicitud = $this->solicitud(['contrasena' => '77-2026', 'fecha_vencimiento' => now()->addDays(10)->toDateString()]);

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/prorroga", [
            'fecha_nueva' => now()->addDays(17)->toDateString(),
            'motivo' => 'Se requiere más tiempo.',
            'enviar_correo' => '0',
        ]);

        $response->assertRedirect();
        $this->assertEquals(1, $solicitud->prorrogas()->count());
        $this->assertEquals(0, $solicitud->correos_enviados()->count());
        Mail::assertNothingSent();
    }

    public function test_prorroga_sin_mandar_el_campo_enviar_correo_sigue_enviando_por_defecto(): void
    {
        // Compatibilidad hacia atrás: un formulario/test viejo que no manda
        // "enviar_correo" en absoluto (como ActuacionesTest) debe seguir
        // enviando el correo automático, igual que antes de esta fase.
        $this->seedCatalog();
        Mail::fake();
        $user = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.prorroga']);
        $solicitud = $this->solicitud(['contrasena' => '77-2026', 'fecha_vencimiento' => now()->addDays(10)->toDateString()]);

        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/prorroga", [
            'fecha_nueva' => now()->addDays(17)->toDateString(),
            'motivo' => 'Se requiere más tiempo.',
        ]);

        $this->assertEquals(1, $solicitud->correos_enviados()->count());
        Mail::assertSent(PlantillaCorreoMail::class, 1);
    }

    public function test_aclaracion_asunto_lleva_el_formato_pedido(): void
    {
        $this->seedCatalog();
        Mail::fake();
        $user = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.aclaracion']);
        $solicitud = $this->solicitud(['contrasena' => '1524-2026']);

        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/aclaracion", [
            'motivo' => 'Precisar el período de interés.',
        ]);

        $correo = $solicitud->correos_enviados()->first();
        $this->assertNotNull($correo);
        $this->assertEquals('Aclaración Solicitud No. 1,524-2026', $correo->asunto);
    }

    public function test_recurso_con_pdf_adjunto_lo_enlaza_y_asunto_lleva_el_correlativo(): void
    {
        $this->seedCatalog();
        Storage::fake('local');
        Mail::fake();
        $user = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.recurso']);
        $solicitud = $this->solicitud(['contrasena' => '77-2026']);

        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/recurso", [
            'correlativo' => '30-2026',
            'motivo' => 'La información entregada fue incompleta.',
            'documento' => UploadedFile::fake()->create('recurso.pdf', 200, 'application/pdf'),
        ]);

        $recurso = $solicitud->recursos_revision()->first();
        $this->assertNotNull($recurso->documento_id);

        $correo = $solicitud->correos_enviados()->first();
        $this->assertEquals('Recurso Revisión No. 30-2026', $correo->asunto);
        $this->assertEquals(1, $correo->correo_adjuntos()->count());
    }

    public function test_finalizar_usa_plantilla_de_resolucion_con_adjunto_opcional(): void
    {
        $this->seedCatalog();
        Storage::fake('local');
        Mail::fake();
        $user = $this->adminConPermisos(['solicitudes.ver', 'solicitudes.finalizar']);
        $solicitud = $this->solicitud(['contrasena' => '1524-2026']);

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/finalizar", [
            'documento' => UploadedFile::fake()->create('resolucion.pdf', 200, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $solicitud->refresh();
        $this->assertEquals('finalizada', $solicitud->estado->clave);

        $correo = $solicitud->correos_enviados()->first();
        $this->assertNotNull($correo);
        $this->assertEquals('RESPUESTA SOLICITUD No. 1,524-2026', $correo->asunto);
        $this->assertEquals(1, $correo->correo_adjuntos()->count());

        $documento = $solicitud->documentos()->latest()->first();
        $this->assertTrue($documento->visible_ciudadano);
    }

    public function test_finalizar_sin_marcar_enviar_correo_no_envia(): void
    {
        $this->seedCatalog();
        Mail::fake();
        $user = $this->adminConPermisos(['solicitudes.ver', 'solicitudes.finalizar']);
        $solicitud = $this->solicitud(['contrasena' => '77-2026']);

        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/finalizar", [
            'enviar_correo' => '0',
        ]);

        $solicitud->refresh();
        $this->assertEquals('finalizada', $solicitud->estado->clave);
        $this->assertEquals(0, $solicitud->correos_enviados()->count());
        Mail::assertNothingSent();
    }

    public function test_correo_libre_requiere_permiso(): void
    {
        $this->seedCatalog();
        $user = $this->adminConPermisos(['solicitudes.ver']);
        $solicitud = $this->solicitud();

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/correo", [
            'destinatario' => 'ciudadano@example.com',
            'asunto' => 'Aviso',
            'cuerpo' => 'Mensaje de prueba.',
        ]);

        $response->assertStatus(403);
    }

    public function test_correo_libre_se_envia_y_queda_registrado(): void
    {
        $this->seedCatalog();
        Mail::fake();
        $user = $this->adminConPermisos(['solicitudes.ver', 'correos.enviar']);
        $solicitud = $this->solicitud();

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/correo", [
            'destinatario' => 'otro@example.com',
            'asunto' => 'Seguimiento de su expediente',
            'cuerpo' => 'Mensaje libre de prueba.',
        ]);

        $response->assertRedirect();
        $correo = $solicitud->correos_enviados()->first();
        $this->assertNotNull($correo);
        $this->assertNull($correo->plantilla_id);
        $this->assertEquals('otro@example.com', $correo->destinatario);
        $this->assertEquals('enviado', $correo->estado_entrega);
        Mail::assertSent(PlantillaCorreoMail::class, 1);
    }

    public function test_dependencia_y_enlace_ya_no_muestra_el_formulario_manual_de_asignacion(): void
    {
        // Fase 22, confirmado por el usuario vía AskUserQuestion: el
        // formulario manual "Asignar dependencia/enlace" se quita de la
        // vista porque la asignación ahora es automática al generar el
        // Oficio/Providencia (Fase 21).
        $this->seedCatalog();
        Permission::create(['clave' => 'solicitudes.asignar_dependencia', 'nombre' => 'x']);
        $user = $this->adminConPermisos(['solicitudes.ver', 'solicitudes.asignar_dependencia']);
        $solicitud = $this->solicitud();

        $response = $this->actingAs($user)->get("/admin/solicitudes/{$solicitud->id}");

        $response->assertOk();
        // La ruta del formulario manual (antes usada por un <form action="...dependencia">)
        // ya no debe aparecer en la página.
        $response->assertDontSee(route('admin.solicitudes.dependencia', $solicitud), false);
        $response->assertSee('automáticamente al generar', false);
    }
}
