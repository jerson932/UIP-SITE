<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Solicitante;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\SolicitudHistorial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

// Fase 12: portal del ciudadano — consulta pública de solo lectura, sin
// sesión de administrador.
class PortalCiudadanoTest extends TestCase
{
    use RefreshDatabase;

    private function solicitud(array $overrides = []): Solicitud
    {
        $estado = SolicitudEstado::create(['clave' => 'en_seguimiento', 'etiqueta' => 'En seguimiento', 'color' => '#1baf7a', 'orden' => 1]);
        $solicitante = Solicitante::create(['nombre' => 'Ciudadano de Prueba', 'correo' => 'prueba@example.com']);

        return Solicitud::create(array_merge([
            'codigo_ns' => 'NS_99-2026',
            'codigo_acceso' => 'A8K4-XP29',
            'solicitante_id' => $solicitante->id,
            'asunto' => 'Solicitud de prueba automatizada.',
            'medio_recepcion' => 'electronica',
            'estado_id' => $estado->id,
            'fecha_ingreso' => now()->toDateString(),
        ], $overrides));
    }

    public function test_form_de_consulta_es_publico(): void
    {
        $response = $this->get('/seguimiento');
        $response->assertStatus(200);
        $response->assertSee('Código de acceso');
    }

    public function test_consulta_con_codigo_valido_muestra_el_expediente(): void
    {
        $solicitud = $this->solicitud();

        $response = $this->post('/seguimiento', ['codigo_acceso' => 'A8K4-XP29']);

        $response->assertStatus(200);
        $response->assertSee('NS_99-2026');
    }

    public function test_consulta_es_insensible_a_mayusculas_y_espacios(): void
    {
        $solicitud = $this->solicitud();

        $response = $this->post('/seguimiento', ['codigo_acceso' => '  a8k4-xp29  ']);

        $response->assertStatus(200);
        $response->assertSee('NS_99-2026');
    }

    public function test_consulta_con_codigo_inexistente_muestra_error_sin_filtrar_datos(): void
    {
        $this->solicitud();

        $response = $this->post('/seguimiento', ['codigo_acceso' => 'NO-EXISTE']);

        $response->assertSessionHasErrors('codigo_acceso');
        $response->assertDontSee('NS_99-2026');
    }

    public function test_consulta_se_limita_por_intentos(): void
    {
        RateLimiter::clear('seguimiento|127.0.0.1');
        $this->solicitud();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/seguimiento', ['codigo_acceso' => 'CODIGO-INCORRECTO']);
        }

        $response = $this->post('/seguimiento', ['codigo_acceso' => 'CODIGO-INCORRECTO']);
        $response->assertSessionHasErrors('codigo_acceso');
        $errors = session('errors');
        $this->assertStringContainsString('Demasiados intentos', $errors->first('codigo_acceso'));
    }

    public function test_documentos_no_visibles_no_aparecen_en_el_portal(): void
    {
        $solicitud = $this->solicitud();
        Documento::create([
            'solicitud_id' => $solicitud->id,
            'nombre' => 'Nota interna.pdf',
            'ruta_archivo' => 'documentos/x.pdf',
            'tipo' => 'pdf',
            'visible_ciudadano' => false,
        ]);
        Documento::create([
            'solicitud_id' => $solicitud->id,
            'nombre' => 'Resolución final.pdf',
            'ruta_archivo' => 'documentos/y.pdf',
            'tipo' => 'pdf',
            'visible_ciudadano' => true,
        ]);

        $response = $this->post('/seguimiento', ['codigo_acceso' => 'A8K4-XP29']);

        $response->assertSee('Resolución final.pdf');
        $response->assertDontSee('Nota interna.pdf');
    }

    public function test_historial_se_muestra_en_el_portal(): void
    {
        $solicitud = $this->solicitud();
        SolicitudHistorial::create([
            'solicitud_id' => $solicitud->id,
            'tipo_actor' => 'administrador',
            'descripcion' => 'Solicitud aceptada como información pública.',
        ]);

        $response = $this->post('/seguimiento', ['codigo_acceso' => 'A8K4-XP29']);

        $response->assertSee('Solicitud aceptada como información pública.');
    }

    public function test_descarga_de_documento_visible_con_enlace_firmado(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documentos/y.pdf', 'contenido de prueba');
        $solicitud = $this->solicitud();
        $documento = Documento::create([
            'solicitud_id' => $solicitud->id,
            'nombre' => 'Resolución.pdf',
            'ruta_archivo' => 'documentos/y.pdf',
            'tipo' => 'pdf',
            'visible_ciudadano' => true,
        ]);

        $url = URL::temporarySignedRoute('ciudadano.documentos.descargar', now()->addMinutes(30), ['documento' => $documento->id]);

        $response = $this->get($url);
        $response->assertOk();
    }

    public function test_descarga_sin_firma_valida_es_rechazada(): void
    {
        Storage::fake('local');
        $solicitud = $this->solicitud();
        $documento = Documento::create([
            'solicitud_id' => $solicitud->id,
            'nombre' => 'Resolución.pdf',
            'ruta_archivo' => 'documentos/y.pdf',
            'tipo' => 'pdf',
            'visible_ciudadano' => true,
        ]);

        $response = $this->get("/seguimiento/documentos/{$documento->id}/descargar");

        $response->assertStatus(403);
    }

    public function test_descarga_de_documento_no_visible_da_404_aunque_la_firma_sea_valida(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documentos/z.pdf', 'contenido interno');
        $solicitud = $this->solicitud();
        $documento = Documento::create([
            'solicitud_id' => $solicitud->id,
            'nombre' => 'Nota interna.pdf',
            'ruta_archivo' => 'documentos/z.pdf',
            'tipo' => 'pdf',
            'visible_ciudadano' => false,
        ]);

        $url = URL::temporarySignedRoute('ciudadano.documentos.descargar', now()->addMinutes(30), ['documento' => $documento->id]);

        $response = $this->get($url);
        $response->assertStatus(404);
    }
}
