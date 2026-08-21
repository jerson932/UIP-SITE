<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\Permission;
use App\Models\PlantillaDocumento;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Generación del Oficio/Providencia de traslado a la dependencia asignada
// (Fase 19), a partir de las plantillas .docx reales en
// resources/plantillas_oficiales/ (App\Services\DocumentoOficialService).
class DocumentoOficialTest extends TestCase
{
    use RefreshDatabase;

    private function adminConPermisos(array $claves): User
    {
        foreach ($claves as $clave) {
            Permission::firstOrCreate(['clave' => $clave], ['nombre' => $clave]);
        }

        $rol = Rol::create(['nombre' => 'TestRol']);
        $rol->permissions()->sync(Permission::whereIn('clave', $claves)->pluck('id'));

        return User::factory()->create([
            'password' => Hash::make('password123'),
            'role_id' => $rol->id,
            'activo' => true,
        ]);
    }

    private function plantilla(string $clave, string $nombre): PlantillaDocumento
    {
        return PlantillaDocumento::updateOrCreate(['clave' => $clave], [
            'nombre' => $nombre,
            'tipo' => 'docx',
            'contenido' => 'placeholders de prueba',
            'visible_ciudadano_default' => false,
            'activa' => true,
        ]);
    }

    private function solicitudConDependencia(?string $plantillaClave): Solicitud
    {
        $estado = SolicitudEstado::create(['clave' => 'en_seguimiento', 'etiqueta' => 'En seguimiento', 'color' => '#2a78d6', 'orden' => 2]);
        $solicitante = Solicitante::create(['nombre' => 'Juan Carlos Pérez López', 'correo' => 'interesado@example.com']);
        $dependencia = Dependencia::create([
            'codigo' => 'TESTDEP',
            'nombre' => $plantillaClave === 'oficio_despacho' ? 'Despacho Superior' : 'Dirección de Planificación de este Ministerio',
            'activa' => true,
            'plantilla_clave' => $plantillaClave,
        ]);

        return Solicitud::create([
            'codigo_ns' => 'NS_TEST-2026',
            'contrasena' => '1631-2026',
            'codigo_acceso' => 'TEST-0001',
            'solicitante_id' => $solicitante->id,
            'asunto' => 'información sobre el presupuesto ejecutado del año 2025',
            'medio_recepcion' => 'electronica',
            'estado_id' => $estado->id,
            'dependencia_id' => $dependencia->id,
            'fecha_ingreso' => now()->toDateString(),
        ]);
    }

    public function test_requiere_permiso_solicitudes_generar_documento(): void
    {
        $user = $this->adminConPermisos([]);
        $solicitud = $this->solicitudConDependencia('oficio_despacho');

        $this->actingAs($user)
            ->post(route('admin.solicitudes.documento_oficial', $solicitud), ['no_oficio' => '1-2026'])
            ->assertStatus(403);
    }

    public function test_genera_un_oficio_real_para_dependencia_mapeada_a_oficio(): void
    {
        Storage::fake('local');
        $this->plantilla('oficio_despacho', 'Oficio — Despacho Superior');
        $admin = $this->adminConPermisos(['solicitudes.generar_documento']);
        $solicitud = $this->solicitudConDependencia('oficio_despacho');

        $response = $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'rc' => '4455',
            'folio' => '12',
            'no_oficio' => '99-2026',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $documento = Documento::where('solicitud_id', $solicitud->id)->first();
        $this->assertNotNull($documento);
        $this->assertEquals('docx', $documento->tipo);
        $this->assertEquals('99-2026', $documento->no_oficio);
        $this->assertNull($documento->no_providencia);
        $this->assertFalse((bool) $documento->visible_ciudadano);
        Storage::disk('local')->assertExists($documento->ruta_archivo);

        $solicitud->refresh();
        $this->assertEquals('4455', $solicitud->rc);
        $this->assertEquals('12', $solicitud->folio);

        $this->assertEquals(
            1,
            $solicitud->solicitud_historial()->where('descripcion', 'like', 'Oficio generado%')->count()
        );
    }

    public function test_genera_una_providencia_real_para_dependencia_sin_plantilla_especial(): void
    {
        Storage::fake('local');
        $this->plantilla('providencia_generica', 'Providencia genérica');
        $admin = $this->adminConPermisos(['solicitudes.generar_documento']);
        $solicitud = $this->solicitudConDependencia(null);

        $response = $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'no_providencia' => '77-2026',
        ]);

        $response->assertRedirect();

        $documento = Documento::where('solicitud_id', $solicitud->id)->first();
        $this->assertNotNull($documento);
        $this->assertEquals('77-2026', $documento->no_providencia);
        $this->assertNull($documento->no_oficio);
        Storage::disk('local')->assertExists($documento->ruta_archivo);
    }

    public function test_exige_el_numero_correspondiente_segun_el_tipo_de_plantilla(): void
    {
        $this->plantilla('oficio_despacho', 'Oficio — Despacho Superior');
        $admin = $this->adminConPermisos(['solicitudes.generar_documento']);
        $solicitud = $this->solicitudConDependencia('oficio_despacho');

        // Falta no_oficio (es tipo oficio) — debe fallar la validación.
        $response = $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'no_providencia' => '1-2026',
        ]);

        $response->assertSessionHasErrors('no_oficio');
        $this->assertEquals(0, Documento::where('solicitud_id', $solicitud->id)->count());
    }

    public function test_no_permite_generar_sin_dependencia_asignada(): void
    {
        $admin = $this->adminConPermisos(['solicitudes.generar_documento']);
        $estado = SolicitudEstado::create(['clave' => 'pendiente_validacion', 'etiqueta' => 'Pendiente', 'color' => '#eda100', 'orden' => 1]);
        $solicitante = Solicitante::create(['nombre' => 'Sin Dependencia', 'correo' => 'sindep@example.com']);
        $solicitud = Solicitud::create([
            'codigo_ns' => 'NS_TEST2-2026',
            'codigo_acceso' => 'TEST-0002',
            'solicitante_id' => $solicitante->id,
            'asunto' => 'una solicitud sin dependencia asignada todavía',
            'medio_recepcion' => 'electronica',
            'estado_id' => $estado->id,
            'fecha_ingreso' => now()->toDateString(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'no_providencia' => '1-2026',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals(0, Documento::where('solicitud_id', $solicitud->id)->count());
    }

    public function test_generar_dos_veces_no_sobrescribe_el_archivo_anterior(): void
    {
        Storage::fake('local');
        $this->plantilla('providencia_generica', 'Providencia genérica');
        $admin = $this->adminConPermisos(['solicitudes.generar_documento']);
        $solicitud = $this->solicitudConDependencia(null);

        $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), ['no_providencia' => '1-2026']);
        $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), ['no_providencia' => '2-2026']);

        $documentos = Documento::where('solicitud_id', $solicitud->id)->get();
        $this->assertCount(2, $documentos);
        $this->assertNotEquals($documentos[0]->ruta_archivo, $documentos[1]->ruta_archivo);
        Storage::disk('local')->assertExists($documentos[0]->ruta_archivo);
        Storage::disk('local')->assertExists($documentos[1]->ruta_archivo);
    }
}
