<?php

namespace Tests\Feature;

use App\Mail\PlantillaCorreoMail;
use App\Models\Actuacion;
use App\Models\Ampliacion;
use App\Models\Configuracion;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\Enlace;
use App\Models\Permission;
use App\Models\PlantillaCorreo;
use App\Models\RecursoRevision;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\SolicitudHistorial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Fase 22b, a pedido del usuario en un solo mensaje con varios cambios:
// (1) ya no se expone a qué dependencia/enlace se asignó el expediente en
// el portal público del ciudadano; (2) el formulario público pide País;
// (3) el panel del enlace solo muestra lo que el propio enlace subió;
// (4) Ampliación puede adjuntar PDF y enviar correo igual que las demás
// actuaciones; (5) cada solicitud nueva por el portal avisa a un correo
// interno configurable; (6) el ciudadano puede pedir un recurso de
// revisión o una ampliación él mismo desde su portal de seguimiento.
class Fase22bTest extends TestCase
{
    use RefreshDatabase;

    private function seedEstados(): void
    {
        foreach ([
            ['clave' => 'pendiente_validacion', 'etiqueta' => 'Pendiente de validación', 'color' => '#eda100', 'orden' => 1],
            ['clave' => 'en_seguimiento', 'etiqueta' => 'En seguimiento', 'color' => '#1baf7a', 'orden' => 2],
            ['clave' => 'recurso_revision', 'etiqueta' => 'Recurso de revisión', 'color' => '#e87ba4', 'orden' => 3],
            ['clave' => 'finalizada', 'etiqueta' => 'Finalizada', 'color' => '#52514e', 'orden' => 4, 'es_final' => true],
        ] as $e) {
            SolicitudEstado::firstOrCreate(['clave' => $e['clave']], $e);
        }
    }

    private function adminConPermisos(array $claves): User
    {
        foreach ($claves as $clave) {
            Permission::firstOrCreate(['clave' => $clave], ['nombre' => $clave]);
        }

        $rol = Rol::create(['nombre' => 'TestRol'.uniqid()]);
        $rol->permissions()->sync(Permission::whereIn('clave', $claves)->pluck('id'));

        return User::factory()->create([
            'password' => Hash::make('password123'),
            'role_id' => $rol->id,
            'activo' => true,
        ]);
    }

    private function solicitud(array $overrides = []): Solicitud
    {
        $estado = SolicitudEstado::where('clave', $overrides['estado_clave'] ?? 'en_seguimiento')->firstOrFail();
        $solicitante = Solicitante::create(['nombre' => 'Interesado de Prueba', 'correo' => 'interesado@example.com']);

        return Solicitud::create(array_merge([
            'codigo_ns' => 'NS_'.uniqid(),
            'codigo_acceso' => 'ACC-'.strtoupper(uniqid()),
            'solicitante_id' => $solicitante->id,
            'asunto' => 'Asunto de prueba.',
            'medio_recepcion' => 'electronica',
            'estado_id' => $estado->id,
            'contrasena' => '10-2026',
            'fecha_ingreso' => now()->toDateString(),
        ], collect($overrides)->except('estado_clave')->all()));
    }

    // --- (1) El historial de asignación interna no debe verse en el portal público ---

    public function test_asignar_dependencia_marca_el_historial_como_no_visible_al_ciudadano(): void
    {
        $this->seedEstados();
        $admin = $this->adminConPermisos(['solicitudes.ver', 'solicitudes.asignar_dependencia']);
        $dependencia = Dependencia::create(['nombre' => 'Dirección de Planificación', 'activa' => true]);
        $solicitud = $this->solicitud();

        $this->actingAs($admin)->post(route('admin.solicitudes.dependencia', $solicitud), [
            'dependencia_id' => $dependencia->id,
        ]);

        $entrada = SolicitudHistorial::where('solicitud_id', $solicitud->id)->latest('id')->first();
        $this->assertNotNull($entrada);
        $this->assertStringContainsString('Dirección de Planificación', $entrada->descripcion);
        $this->assertFalse((bool) $entrada->visible_ciudadano);
    }

    public function test_portal_publico_no_muestra_la_asignacion_de_dependencia_en_el_seguimiento(): void
    {
        $this->seedEstados();
        $solicitud = $this->solicitud();

        // Entrada visible (hito normal) + entrada interna (no debe verse).
        SolicitudHistorial::create([
            'solicitud_id' => $solicitud->id,
            'tipo_actor' => 'administrador',
            'descripcion' => 'Contraseña asignada: No. 10-2026.',
            'visible_ciudadano' => true,
        ]);
        SolicitudHistorial::create([
            'solicitud_id' => $solicitud->id,
            'tipo_actor' => 'administrador',
            'descripcion' => 'Asignada a Dirección de Planificación de este Ministerio, enlace Juan Pérez.',
            'visible_ciudadano' => false,
        ]);

        RateLimiter::clear('seguimiento|127.0.0.1');
        $response = $this->post(route('ciudadano.seguimiento.consultar'), [
            'codigo_acceso' => $solicitud->codigo_acceso,
        ]);

        $response->assertOk();
        $response->assertSee('Contraseña asignada');
        $response->assertDontSee('Dirección de Planificación');
        $response->assertDontSee('Juan Pérez');
    }

    // --- (2) País en el formulario público ---

    public function test_formulario_publico_requiere_pais(): void
    {
        $this->seedEstados();
        RateLimiter::clear('nueva_solicitud|127.0.0.1');

        $response = $this->post('/solicitudes/nueva', [
            'nombre' => 'Sin País',
            'correo' => 'sinpais@example.com',
            'asunto' => 'Solicitud de prueba sin especificar país.',
        ]);

        $response->assertSessionHasErrors('pais');
    }

    public function test_formulario_publico_guarda_el_pais_elegido(): void
    {
        $this->seedEstados();
        Mail::fake();
        RateLimiter::clear('nueva_solicitud|127.0.0.1');

        $this->post('/solicitudes/nueva', [
            'nombre' => 'Persona Extranjera',
            'correo' => 'extranjera@example.com',
            'pais' => 'México',
            'asunto' => 'Solicitud de prueba desde el extranjero.',
        ]);

        $solicitud = Solicitud::first();
        $this->assertNotNull($solicitud);
        $this->assertEquals('México', $solicitud->solicitante->pais);
    }

    // --- (3) El panel del enlace solo muestra lo que él mismo subió ---

    public function test_enlace_solo_ve_los_documentos_que_el_mismo_cargo(): void
    {
        $this->seedEstados();
        foreach (['enlace.ver_asignadas', 'documentos.subir'] as $clave) {
            Permission::firstOrCreate(['clave' => $clave], ['nombre' => $clave]);
        }
        $rol = Rol::create(['nombre' => 'EnlaceTest'.uniqid()]);
        $rol->permissions()->sync(Permission::whereIn('clave', ['enlace.ver_asignadas', 'documentos.subir'])->pluck('id'));
        $userEnlace = User::factory()->create(['password' => Hash::make('password123'), 'role_id' => $rol->id, 'activo' => true]);

        $dependencia = Dependencia::create(['nombre' => 'Dependencia A', 'activa' => true]);
        Enlace::create(['dependencia_id' => $dependencia->id, 'user_id' => $userEnlace->id, 'nombre' => $userEnlace->name, 'activo' => true]);

        $solicitud = $this->solicitud(['dependencia_id' => $dependencia->id]);

        Documento::create([
            'solicitud_id' => $solicitud->id,
            'nombre' => 'subido_por_el_enlace.pdf',
            'ruta_archivo' => 'documentos/x/subido_por_el_enlace.pdf',
            'tipo' => 'pdf',
            'visible_ciudadano' => true,
            'subido_por_ciudadano' => false,
            'subido_por_user_id' => $userEnlace->id,
        ]);
        Documento::create([
            'solicitud_id' => $solicitud->id,
            'nombre' => 'expediente_completo_ajeno.pdf',
            'ruta_archivo' => 'documentos/x/expediente_completo_ajeno.pdf',
            'tipo' => 'pdf',
            'visible_ciudadano' => false,
            'subido_por_ciudadano' => false,
            'subido_por_user_id' => null,
        ]);

        $response = $this->actingAs($userEnlace)->get(route('admin.enlace.show', $solicitud));

        $response->assertOk();
        $response->assertSee('subido_por_el_enlace.pdf');
        $response->assertDontSee('expediente_completo_ajeno.pdf');
    }

    // --- (4) Ampliación: adjuntar PDF ---

    public function test_ampliacion_admite_documento_adjunto(): void
    {
        $this->seedEstados();
        Mail::fake();
        Storage::fake('local');
        $admin = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.ampliacion']);
        $solicitud = $this->solicitud();

        $this->actingAs($admin)->post(route('admin.solicitudes.ampliacion', $solicitud), [
            'descripcion' => 'Pide información adicional con respaldo.',
            'documento' => UploadedFile::fake()->create('respuesta_ampliacion.pdf', 100, 'application/pdf'),
        ]);

        $ampliacion = Ampliacion::where('solicitud_id', $solicitud->id)->first();
        $this->assertNotNull($ampliacion);
        $this->assertNotNull($ampliacion->documento_id);
    }

    // --- (5) Aviso interno al presentar una solicitud por el portal ---

    public function test_se_envia_aviso_interno_al_correo_uip_configurado_al_presentar_solicitud(): void
    {
        $this->seedEstados();
        Configuracion::updateOrCreate(['clave' => 'correo_uip'], ['valor' => 'aviso-interno@mingob.gob.gt', 'descripcion' => 'test']);
        Mail::fake();
        RateLimiter::clear('nueva_solicitud|127.0.0.1');

        $this->post('/solicitudes/nueva', [
            'nombre' => 'Persona Avisada',
            'correo' => 'avisada@example.com',
            'pais' => 'Guatemala',
            'asunto' => 'Solicitud de prueba para verificar el aviso interno.',
        ]);

        $solicitud = Solicitud::first();
        $this->assertNotNull($solicitud);
        $this->assertEquals(
            1,
            $solicitud->correos_enviados()->where('destinatario', 'aviso-interno@mingob.gob.gt')->count()
        );
    }

    public function test_no_falla_si_no_hay_correo_uip_configurado(): void
    {
        $this->seedEstados();
        Mail::fake();
        RateLimiter::clear('nueva_solicitud|127.0.0.1');

        $response = $this->post('/solicitudes/nueva', [
            'nombre' => 'Persona Sin Config',
            'correo' => 'sinconfig@example.com',
            'pais' => 'Guatemala',
            'asunto' => 'Solicitud de prueba sin correo_uip configurado.',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(1, Solicitud::count());
    }

    // --- (6) Autoservicio del ciudadano: recurso de revisión ---

    public function test_ciudadano_puede_presentar_recurso_de_revision_sin_correlativo(): void
    {
        $this->seedEstados();
        RateLimiter::clear('seguimiento_recurso|127.0.0.1');
        $solicitud = $this->solicitud(['estado_clave' => 'finalizada']);

        $response = $this->post(route('ciudadano.recurso.solicitar'), [
            'codigo_acceso' => $solicitud->codigo_acceso,
            'motivo' => 'No estoy de acuerdo con la resolución recibida.',
        ]);

        $response->assertOk();
        $recurso = RecursoRevision::where('solicitud_id', $solicitud->id)->first();
        $this->assertNotNull($recurso);
        $this->assertNull($recurso->correlativo);
        $this->assertEquals(
            1,
            Actuacion::where('solicitud_id', $solicitud->id)->where('tipo', 'recurso_revision')->where('iniciado_por', 'ciudadano')->count()
        );
    }

    public function test_ciudadano_no_puede_presentar_recurso_si_la_solicitud_sigue_pendiente_de_validacion(): void
    {
        $this->seedEstados();
        RateLimiter::clear('seguimiento_recurso|127.0.0.1');
        $solicitud = $this->solicitud(['estado_clave' => 'pendiente_validacion', 'contrasena' => null]);

        $this->post(route('ciudadano.recurso.solicitar'), [
            'codigo_acceso' => $solicitud->codigo_acceso,
            'motivo' => 'Intento antes de tiempo.',
        ]);

        $this->assertEquals(0, RecursoRevision::where('solicitud_id', $solicitud->id)->count());
    }

    public function test_admin_asigna_correlativo_a_recurso_presentado_por_el_ciudadano_y_envia_correo(): void
    {
        $this->seedEstados();
        Mail::fake();
        PlantillaCorreo::create([
            'clave' => 'recurso_recibido',
            'evento' => 'test',
            'asunto_template' => 'Recurso Revisión No. {{correlativo_recurso}}',
            'cuerpo_template' => 'Se registró el recurso {{correlativo_recurso}}.',
            'activa' => true,
        ]);
        $admin = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.recurso']);
        $solicitud = $this->solicitud(['estado_clave' => 'finalizada']);
        $recurso = RecursoRevision::create([
            'solicitud_id' => $solicitud->id,
            'correlativo' => null,
            'fecha_presentacion' => now()->toDateString(),
            'motivo' => 'Motivo original del ciudadano.',
            'estado' => 'recibido',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.solicitudes.recurso.correlativo', [$solicitud, $recurso]), [
            'correlativo' => '55-2026',
        ]);

        $response->assertRedirect();
        $recurso->refresh();
        $this->assertEquals('55-2026', $recurso->correlativo);
        $correo = $solicitud->correos_enviados()->first();
        $this->assertNotNull($correo);
        $this->assertStringContainsString('55-2026', $correo->asunto);
        Mail::assertSent(PlantillaCorreoMail::class);
    }

    // --- (6) Autoservicio del ciudadano: ampliación ---

    public function test_ciudadano_puede_solicitar_ampliacion_desde_su_portal_de_seguimiento(): void
    {
        $this->seedEstados();
        PlantillaCorreo::create([
            'clave' => 'ampliacion_recibida',
            'evento' => 'test',
            'asunto_template' => 'Ampliación recibida - Contraseña No. {{contrasena}}',
            'cuerpo_template' => 'Estimado {{nombre}}, se registró su ampliación.',
            'activa' => true,
        ]);
        Mail::fake();
        RateLimiter::clear('seguimiento_ampliacion|127.0.0.1');
        $solicitud = $this->solicitud();

        $response = $this->post(route('ciudadano.ampliacion.solicitar'), [
            'codigo_acceso' => $solicitud->codigo_acceso,
            'descripcion' => 'Necesito información adicional sobre el mismo tema.',
        ]);

        $response->assertOk();
        $ampliacion = Ampliacion::where('solicitud_id', $solicitud->id)->first();
        $this->assertNotNull($ampliacion);
        $this->assertEquals('recibida', $ampliacion->estado);
        $this->assertEquals(
            1,
            Actuacion::where('solicitud_id', $solicitud->id)->where('tipo', 'ampliacion')->where('iniciado_por', 'ciudadano')->count()
        );
        Mail::assertSent(PlantillaCorreoMail::class);
    }
}
