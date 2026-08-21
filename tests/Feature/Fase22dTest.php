<?php

namespace Tests\Feature;

use App\Mail\PlantillaCorreoMail;
use App\Models\Ampliacion;
use App\Models\Documento;
use App\Models\Permission;
use App\Models\PlantillaCorreo;
use App\Models\RecursoRevision;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Fase 22d: siguiente ronda de ajustes sobre Fase 22c, a pedido del usuario:
// (1) poder "quitar visibilidad" a un documento ya publicado; (2) publicar
// ya NO envía correo automáticamente — es opcional (checkbox), el envío
// obligatorio real sigue ocurriendo al notificar/finalizar el expediente;
// (3) el recurso de revisión solo se puede presentar una vez notificada/
// finalizada la resolución (flag es_final), no en cualquier estado; (4) el
// ciudadano puede adjuntar un documento de soporte tanto al presentar un
// recurso de revisión como al pedir una ampliación desde su portal.
class Fase22dTest extends TestCase
{
    use RefreshDatabase;

    private function seedEstados(): void
    {
        foreach ([
            ['clave' => 'pendiente_validacion', 'etiqueta' => 'Pendiente de validación', 'color' => '#eda100', 'orden' => 1, 'es_final' => false],
            ['clave' => 'en_seguimiento', 'etiqueta' => 'En seguimiento', 'color' => '#1baf7a', 'orden' => 2, 'es_final' => false],
            ['clave' => 'finalizada', 'etiqueta' => 'Finalizada', 'color' => '#52514e', 'orden' => 3, 'es_final' => true],
            ['clave' => 'rechazada', 'etiqueta' => 'Rechazada / no procede', 'color' => '#d03b3b', 'orden' => 4, 'es_final' => true],
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

    // --- (1) Quitar visibilidad ---

    public function test_ocultar_documento_le_quita_la_visibilidad_al_ciudadano(): void
    {
        $this->seedEstados();
        $admin = $this->adminConPermisos(['solicitudes.ver', 'documentos.publicar']);
        $solicitud = $this->solicitud();
        $documento = Documento::create([
            'solicitud_id' => $solicitud->id,
            'nombre' => 'publicado.pdf',
            'ruta_archivo' => 'documentos/x/publicado.pdf',
            'tipo' => 'pdf',
            'visible_ciudadano' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.solicitudes.documentos.ocultar', [$solicitud, $documento]));

        $response->assertRedirect();
        $documento->refresh();
        $this->assertFalse((bool) $documento->visible_ciudadano);

        // La entrada de historial queda interna (no debe verse en el portal del ciudadano).
        $entrada = $solicitud->solicitud_historial()->latest('id')->first();
        $this->assertStringContainsString('oculto', $entrada->descripcion);
        $this->assertFalse((bool) $entrada->visible_ciudadano);
    }

    public function test_no_se_puede_ocultar_un_documento_que_ya_esta_oculto(): void
    {
        $this->seedEstados();
        $admin = $this->adminConPermisos(['solicitudes.ver', 'documentos.publicar']);
        $solicitud = $this->solicitud();
        $documento = Documento::create([
            'solicitud_id' => $solicitud->id,
            'nombre' => 'interno.pdf',
            'ruta_archivo' => 'documentos/x/interno.pdf',
            'tipo' => 'pdf',
            'visible_ciudadano' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.solicitudes.documentos.ocultar', [$solicitud, $documento]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_ocultar_requiere_permiso_documentos_publicar(): void
    {
        $this->seedEstados();
        $admin = $this->adminConPermisos(['solicitudes.ver']);
        $solicitud = $this->solicitud();
        $documento = Documento::create([
            'solicitud_id' => $solicitud->id,
            'nombre' => 'publicado.pdf',
            'ruta_archivo' => 'documentos/x/publicado.pdf',
            'tipo' => 'pdf',
            'visible_ciudadano' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.solicitudes.documentos.ocultar', [$solicitud, $documento]));

        $response->assertStatus(403);
    }

    // --- (2) Publicar: el correo ahora es opcional ---

    public function test_publicar_no_envia_correo_si_se_desmarca_la_opcion(): void
    {
        $this->seedEstados();
        Mail::fake();
        PlantillaCorreo::create([
            'clave' => 'documentos_disponibles',
            'evento' => 'test',
            'asunto_template' => 'Documentos disponibles',
            'cuerpo_template' => 'Hay documentos disponibles.',
            'activa' => true,
        ]);
        $admin = $this->adminConPermisos(['solicitudes.ver', 'documentos.publicar']);
        $solicitud = $this->solicitud();
        $documento = Documento::create([
            'solicitud_id' => $solicitud->id,
            'nombre' => 'sin_correo.pdf',
            'ruta_archivo' => 'documentos/x/sin_correo.pdf',
            'tipo' => 'pdf',
            'visible_ciudadano' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.solicitudes.documentos.publicar', [$solicitud, $documento]), [
            'enviar_correo' => '0',
        ]);

        $response->assertRedirect();
        $documento->refresh();
        $this->assertTrue((bool) $documento->visible_ciudadano);
        Mail::assertNothingSent();
        $this->assertEquals(0, $solicitud->correos_enviados()->count());
    }

    public function test_publicar_sigue_enviando_correo_por_defecto(): void
    {
        $this->seedEstados();
        Mail::fake();
        Storage::fake('local');
        PlantillaCorreo::create([
            'clave' => 'documentos_disponibles',
            'evento' => 'test',
            'asunto_template' => 'Documentos disponibles',
            'cuerpo_template' => 'Hay documentos disponibles.',
            'activa' => true,
        ]);
        $admin = $this->adminConPermisos(['solicitudes.ver', 'documentos.publicar']);
        $solicitud = $this->solicitud();
        Storage::disk('local')->put('documentos/x/con_correo.pdf', 'contenido');
        $documento = Documento::create([
            'solicitud_id' => $solicitud->id,
            'nombre' => 'con_correo.pdf',
            'ruta_archivo' => 'documentos/x/con_correo.pdf',
            'tipo' => 'pdf',
            'visible_ciudadano' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.solicitudes.documentos.publicar', [$solicitud, $documento]));

        $response->assertRedirect();
        Mail::assertSent(PlantillaCorreoMail::class);
    }

    // --- (3) Recurso de revisión: solo procede tras notificar/finalizar ---

    public function test_ciudadano_no_puede_presentar_recurso_mientras_el_expediente_sigue_en_tramite(): void
    {
        $this->seedEstados();
        RateLimiter::clear('seguimiento_recurso|127.0.0.1');
        $solicitud = $this->solicitud(['estado_clave' => 'en_seguimiento']);

        $response = $this->post(route('ciudadano.recurso.solicitar'), [
            'codigo_acceso' => $solicitud->codigo_acceso,
            'motivo' => 'Todavía no hay resolución que recurrir.',
        ]);

        $response->assertOk();
        $this->assertEquals(0, RecursoRevision::where('solicitud_id', $solicitud->id)->count());
    }

    public function test_ciudadano_puede_presentar_recurso_una_vez_rechazada_la_solicitud(): void
    {
        $this->seedEstados();
        RateLimiter::clear('seguimiento_recurso|127.0.0.1');
        $solicitud = $this->solicitud(['estado_clave' => 'rechazada']);

        $response = $this->post(route('ciudadano.recurso.solicitar'), [
            'codigo_acceso' => $solicitud->codigo_acceso,
            'motivo' => 'No estoy de acuerdo con el rechazo.',
        ]);

        $response->assertOk();
        $this->assertEquals(1, RecursoRevision::where('solicitud_id', $solicitud->id)->count());
    }

    // --- (4) Documento de soporte adjunto por el ciudadano ---

    public function test_ciudadano_puede_adjuntar_documento_al_presentar_recurso(): void
    {
        $this->seedEstados();
        Storage::fake('local');
        RateLimiter::clear('seguimiento_recurso|127.0.0.1');
        $solicitud = $this->solicitud(['estado_clave' => 'finalizada']);

        $response = $this->post(route('ciudadano.recurso.solicitar'), [
            'codigo_acceso' => $solicitud->codigo_acceso,
            'motivo' => 'Adjunto evidencia de mi reclamo.',
            'archivo' => UploadedFile::fake()->create('evidencia.pdf', 200, 'application/pdf'),
        ]);

        $response->assertOk();
        $recurso = RecursoRevision::where('solicitud_id', $solicitud->id)->first();
        $this->assertNotNull($recurso);
        $this->assertNotNull($recurso->documento_id);
        // El documento del ciudadano queda interno: la UIP decide si lo publica.
        $this->assertFalse((bool) $recurso->documento->visible_ciudadano);
        $this->assertTrue((bool) $recurso->documento->subido_por_ciudadano);
    }

    public function test_ciudadano_puede_adjuntar_documento_al_pedir_ampliacion(): void
    {
        $this->seedEstados();
        Storage::fake('local');
        Mail::fake();
        PlantillaCorreo::create([
            'clave' => 'ampliacion_recibida',
            'evento' => 'test',
            'asunto_template' => 'Ampliación recibida',
            'cuerpo_template' => 'Se registró su ampliación.',
            'activa' => true,
        ]);
        RateLimiter::clear('seguimiento_ampliacion|127.0.0.1');
        $solicitud = $this->solicitud(['estado_clave' => 'en_seguimiento']);

        $response = $this->post(route('ciudadano.ampliacion.solicitar'), [
            'codigo_acceso' => $solicitud->codigo_acceso,
            'descripcion' => 'Adjunto un documento de respaldo para mi ampliación.',
            'archivo' => UploadedFile::fake()->create('respaldo.pdf', 150, 'application/pdf'),
        ]);

        $response->assertOk();
        $ampliacion = Ampliacion::where('solicitud_id', $solicitud->id)->first();
        $this->assertNotNull($ampliacion);
        $this->assertNotNull($ampliacion->documento_id);
        $this->assertFalse((bool) $ampliacion->documento->visible_ciudadano);
    }

    // --- Renderizado (smoke test de las vistas tocadas en esta ronda) ---

    public function test_pestana_documentos_muestra_publicar_y_quitar_visibilidad_segun_corresponda(): void
    {
        $this->seedEstados();
        $admin = $this->adminConPermisos(['solicitudes.ver', 'documentos.publicar', 'documentos.subir']);
        $solicitud = $this->solicitud();
        Documento::create([
            'solicitud_id' => $solicitud->id,
            'nombre' => 'oculto.pdf',
            'ruta_archivo' => 'documentos/x/oculto.pdf',
            'tipo' => 'pdf',
            'visible_ciudadano' => false,
        ]);
        Documento::create([
            'solicitud_id' => $solicitud->id,
            'nombre' => 'visible.pdf',
            'ruta_archivo' => 'documentos/x/visible.pdf',
            'tipo' => 'pdf',
            'visible_ciudadano' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.solicitudes.show', [$solicitud, 'tab' => 'documentos']));

        $response->assertOk();
        $response->assertSee('Publicar');
        $response->assertSee('Quitar visibilidad');
    }

    public function test_pestana_recurso_muestra_el_documento_adjunto_por_el_ciudadano(): void
    {
        $this->seedEstados();
        Storage::fake('local');
        $admin = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.recurso']);
        $solicitud = $this->solicitud(['estado_clave' => 'finalizada']);
        RateLimiter::clear('seguimiento_recurso|127.0.0.1');

        $this->post(route('ciudadano.recurso.solicitar'), [
            'codigo_acceso' => $solicitud->codigo_acceso,
            'motivo' => 'Adjunto evidencia.',
            'archivo' => UploadedFile::fake()->create('evidencia_ciudadano.pdf', 100, 'application/pdf'),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.solicitudes.show', [$solicitud, 'tab' => 'recurso']));

        $response->assertOk();
        $response->assertSee('evidencia_ciudadano.pdf');
    }

    public function test_portal_publico_oculta_el_recurso_de_revision_si_no_es_final(): void
    {
        $this->seedEstados();
        RateLimiter::clear('seguimiento|127.0.0.1');
        $solicitud = $this->solicitud(['estado_clave' => 'en_seguimiento']);

        $response = $this->post(route('ciudadano.seguimiento.consultar'), [
            'codigo_acceso' => $solicitud->codigo_acceso,
        ]);

        $response->assertOk();
        $response->assertDontSee('Presenta un recurso de revisión');
        $response->assertSee('pedir una ampliación');
    }

    public function test_portal_publico_muestra_el_recurso_de_revision_si_ya_es_final(): void
    {
        $this->seedEstados();
        RateLimiter::clear('seguimiento|127.0.0.1');
        $solicitud = $this->solicitud(['estado_clave' => 'finalizada']);

        $response = $this->post(route('ciudadano.seguimiento.consultar'), [
            'codigo_acceso' => $solicitud->codigo_acceso,
        ]);

        $response->assertOk();
        $response->assertSee('Presenta un recurso de revisión');
    }
}
