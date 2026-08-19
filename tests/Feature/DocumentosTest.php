<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Permission;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Fase 10: carga y publicación de documentos en el detalle de un expediente.
class DocumentosTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalog(): void
    {
        SolicitudEstado::create(['clave' => 'en_seguimiento', 'etiqueta' => 'En seguimiento', 'color' => '#1baf7a', 'orden' => 1]);

        foreach (['solicitudes.ver', 'documentos.subir', 'documentos.publicar'] as $clave) {
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

    private function solicitud(): Solicitud
    {
        $estado = SolicitudEstado::where('clave', 'en_seguimiento')->first();
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

    public function test_subir_documento_requiere_permiso(): void
    {
        $this->seedCatalog();
        Storage::fake('local');
        $user = $this->adminConPermisos(['solicitudes.ver']);
        $solicitud = $this->solicitud();

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/documentos", [
            'archivo' => UploadedFile::fake()->create('resolucion.pdf', 200, 'application/pdf'),
        ]);

        $response->assertStatus(403);
    }

    public function test_subir_documento_lo_guarda_como_interno_por_defecto(): void
    {
        $this->seedCatalog();
        Storage::fake('local');
        $user = $this->adminConPermisos(['solicitudes.ver', 'documentos.subir']);
        $solicitud = $this->solicitud();

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/documentos", [
            'archivo' => UploadedFile::fake()->create('resolucion.pdf', 200, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $documento = $solicitud->documentos()->first();
        $this->assertNotNull($documento);
        $this->assertEquals('resolucion.pdf', $documento->nombre);
        $this->assertEquals('pdf', $documento->tipo);
        $this->assertFalse($documento->visible_ciudadano);
        $this->assertFalse($documento->subido_por_ciudadano);
        $this->assertEquals($user->id, $documento->subido_por_user_id);
        Storage::disk('local')->assertExists($documento->ruta_archivo);
    }

    public function test_visible_ciudadano_se_ignora_sin_permiso_de_publicar(): void
    {
        $this->seedCatalog();
        Storage::fake('local');
        // Solo tiene permiso de subir, NO de publicar.
        $user = $this->adminConPermisos(['solicitudes.ver', 'documentos.subir']);
        $solicitud = $this->solicitud();

        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/documentos", [
            'archivo' => UploadedFile::fake()->create('resolucion.pdf', 200, 'application/pdf'),
            'visible_ciudadano' => '1',
        ]);

        $documento = $solicitud->documentos()->first();
        $this->assertFalse($documento->visible_ciudadano);
    }

    public function test_visible_ciudadano_se_respeta_con_permiso_de_publicar(): void
    {
        $this->seedCatalog();
        Storage::fake('local');
        $user = $this->adminConPermisos(['solicitudes.ver', 'documentos.subir', 'documentos.publicar']);
        $solicitud = $this->solicitud();

        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/documentos", [
            'archivo' => UploadedFile::fake()->create('resolucion.pdf', 200, 'application/pdf'),
            'visible_ciudadano' => '1',
        ]);

        $documento = $solicitud->documentos()->first();
        $this->assertTrue($documento->visible_ciudadano);
    }

    public function test_subir_documento_rechaza_extension_no_permitida(): void
    {
        $this->seedCatalog();
        Storage::fake('local');
        $user = $this->adminConPermisos(['solicitudes.ver', 'documentos.subir']);
        $solicitud = $this->solicitud();

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/documentos", [
            'archivo' => UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream'),
        ]);

        $response->assertSessionHasErrors('archivo');
        $this->assertEquals(0, $solicitud->documentos()->count());
    }

    public function test_subir_documento_rechaza_archivo_demasiado_grande(): void
    {
        $this->seedCatalog();
        Storage::fake('local');
        $user = $this->adminConPermisos(['solicitudes.ver', 'documentos.subir']);
        $solicitud = $this->solicitud();

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/documentos", [
            'archivo' => UploadedFile::fake()->create('grande.pdf', 20000, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('archivo');
        $this->assertEquals(0, $solicitud->documentos()->count());
    }

    public function test_publicar_requiere_permiso(): void
    {
        $this->seedCatalog();
        Storage::fake('local');
        $user = $this->adminConPermisos(['solicitudes.ver']);
        $solicitud = $this->solicitud();
        $documento = Documento::create([
            'solicitud_id' => $solicitud->id,
            'nombre' => 'doc.pdf',
            'ruta_archivo' => 'documentos/solicitud_1/doc.pdf',
            'tipo' => 'pdf',
            'visible_ciudadano' => false,
        ]);

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/documentos/{$documento->id}/publicar");

        $response->assertStatus(403);
    }

    public function test_publicar_marca_el_documento_como_visible(): void
    {
        $this->seedCatalog();
        Storage::fake('local');
        $user = $this->adminConPermisos(['solicitudes.ver', 'documentos.publicar']);
        $solicitud = $this->solicitud();
        $documento = Documento::create([
            'solicitud_id' => $solicitud->id,
            'nombre' => 'doc.pdf',
            'ruta_archivo' => 'documentos/solicitud_1/doc.pdf',
            'tipo' => 'pdf',
            'visible_ciudadano' => false,
        ]);

        $response = $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/documentos/{$documento->id}/publicar");

        $response->assertRedirect();
        $this->assertTrue($documento->fresh()->visible_ciudadano);
        $this->assertEquals(1, $solicitud->solicitud_historial()->count());
    }

    public function test_descargar_documento_devuelve_el_archivo(): void
    {
        $this->seedCatalog();
        Storage::fake('local');
        $user = $this->adminConPermisos(['solicitudes.ver', 'documentos.subir']);
        $solicitud = $this->solicitud();

        $this->actingAs($user)->post("/admin/solicitudes/{$solicitud->id}/documentos", [
            'archivo' => UploadedFile::fake()->create('resolucion.pdf', 200, 'application/pdf'),
        ]);
        $documento = $solicitud->documentos()->first();

        $response = $this->actingAs($user)->get("/admin/solicitudes/{$solicitud->id}/documentos/{$documento->id}/descargar");

        $response->assertOk();
    }

    public function test_descargar_documento_de_otra_solicitud_da_404(): void
    {
        $this->seedCatalog();
        Storage::fake('local');
        $user = $this->adminConPermisos(['solicitudes.ver']);
        $solicitudA = $this->solicitud();
        $solicitante = Solicitante::create(['nombre' => 'Otro', 'correo' => 'otro@example.com']);
        $solicitudB = Solicitud::create([
            'codigo_ns' => 'NS_98-2026', 'codigo_acceso' => 'TEST-0002',
            'solicitante_id' => $solicitante->id, 'asunto' => 'Otra prueba',
            'medio_recepcion' => 'electronica', 'estado_id' => $solicitudA->estado_id,
            'fecha_ingreso' => now()->toDateString(),
        ]);
        $documentoDeB = Documento::create([
            'solicitud_id' => $solicitudB->id,
            'nombre' => 'doc.pdf',
            'ruta_archivo' => 'documentos/solicitud_'.$solicitudB->id.'/doc.pdf',
            'tipo' => 'pdf',
            'visible_ciudadano' => false,
        ]);

        $response = $this->actingAs($user)->get("/admin/solicitudes/{$solicitudA->id}/documentos/{$documentoDeB->id}/descargar");

        $response->assertStatus(404);
    }
}
