<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\Enlace;
use App\Models\Permission;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\SolicitudHistorial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Panel del enlace (Fase 20): un contacto de dependencia con cuenta propia
// (User + Enlace vinculados por user_id) solo debe ver/actuar sobre
// expedientes asignados a SU dependencia (solicitud.dependencia_id), nunca
// sobre los de otra — a diferencia del rol "Enlace" antes de esta fase, que
// tenía 'solicitudes.ver' y veía TODO el sistema sin filtro.
class EnlaceControllerTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioEnlace(Dependencia $dependencia): User
    {
        foreach (['enlace.ver_asignadas', 'documentos.subir'] as $clave) {
            Permission::firstOrCreate(['clave' => $clave], ['nombre' => $clave]);
        }

        $rol = Rol::firstOrCreate(['nombre' => 'EnlaceTest']);
        $rol->permissions()->sync(Permission::whereIn('clave', ['enlace.ver_asignadas', 'documentos.subir'])->pluck('id'));

        $user = User::factory()->create(['password' => Hash::make('password123'), 'role_id' => $rol->id, 'activo' => true]);

        Enlace::create(['dependencia_id' => $dependencia->id, 'user_id' => $user->id, 'nombre' => $user->name, 'activo' => true]);

        return $user->fresh();
    }

    private function solicitudPara(?int $dependenciaId, string $codigo): Solicitud
    {
        $estado = SolicitudEstado::firstOrCreate(['clave' => 'en_seguimiento'], ['etiqueta' => 'En seguimiento', 'color' => '#1baf7a', 'orden' => 1, 'es_final' => false]);
        $solicitante = Solicitante::create(['nombre' => 'Interesado de Prueba', 'correo' => 'i@example.com']);

        return Solicitud::create([
            'codigo_ns' => $codigo,
            'codigo_acceso' => strtoupper($codigo).'-ACC',
            'solicitante_id' => $solicitante->id,
            'asunto' => 'Asunto de prueba para '.$codigo,
            'medio_recepcion' => 'electronica',
            'estado_id' => $estado->id,
            'dependencia_id' => $dependenciaId,
            'fecha_ingreso' => now()->toDateString(),
        ]);
    }

    public function test_el_indice_solo_muestra_solicitudes_de_su_propia_dependencia(): void
    {
        $depA = Dependencia::create(['nombre' => 'Dependencia A', 'activa' => true]);
        $depB = Dependencia::create(['nombre' => 'Dependencia B', 'activa' => true]);
        $enlaceA = $this->usuarioEnlace($depA);

        $this->solicitudPara($depA->id, 'NS_A-2026');
        $this->solicitudPara($depB->id, 'NS_B-2026');

        $response = $this->actingAs($enlaceA)->get(route('admin.enlace.index'));

        $response->assertOk();
        $response->assertSee('NS_A-2026');
        $response->assertDontSee('NS_B-2026');
    }

    public function test_show_devuelve_403_si_el_expediente_no_es_de_su_dependencia(): void
    {
        $depA = Dependencia::create(['nombre' => 'Dependencia A', 'activa' => true]);
        $depB = Dependencia::create(['nombre' => 'Dependencia B', 'activa' => true]);
        $enlaceA = $this->usuarioEnlace($depA);
        $solicitudB = $this->solicitudPara($depB->id, 'NS_B-2026');

        $response = $this->actingAs($enlaceA)->get(route('admin.enlace.show', $solicitudB));

        $response->assertForbidden();
    }

    public function test_guardar_observacion_crea_entrada_en_el_historial(): void
    {
        $dep = Dependencia::create(['nombre' => 'Dependencia A', 'activa' => true]);
        $enlace = $this->usuarioEnlace($dep);
        $solicitud = $this->solicitudPara($dep->id, 'NS_A-2026');

        $response = $this->actingAs($enlace)->post(route('admin.enlace.observacion', $solicitud), [
            'observacion' => 'Ya estamos recopilando la información solicitada.',
        ]);

        $response->assertRedirect();
        $this->assertEquals(
            1,
            SolicitudHistorial::where('solicitud_id', $solicitud->id)
                ->where('descripcion', 'like', '%Ya estamos recopilando%')
                ->count()
        );
    }

    public function test_no_puede_dejar_observacion_en_un_expediente_ajeno(): void
    {
        $depA = Dependencia::create(['nombre' => 'Dependencia A', 'activa' => true]);
        $depB = Dependencia::create(['nombre' => 'Dependencia B', 'activa' => true]);
        $enlaceA = $this->usuarioEnlace($depA);
        $solicitudB = $this->solicitudPara($depB->id, 'NS_B-2026');

        $response = $this->actingAs($enlaceA)->post(route('admin.enlace.observacion', $solicitudB), [
            'observacion' => 'Intento no autorizado.',
        ]);

        $response->assertForbidden();
        $this->assertEquals(0, SolicitudHistorial::where('solicitud_id', $solicitudB->id)->count());
    }

    public function test_sin_el_permiso_enlace_ver_asignadas_no_puede_entrar(): void
    {
        $rol = Rol::create(['nombre' => 'SinPermisos']);
        $user = User::factory()->create(['password' => Hash::make('password123'), 'role_id' => $rol->id, 'activo' => true]);

        $response = $this->actingAs($user)->get(route('admin.enlace.index'));

        $response->assertForbidden();
    }

    public function test_el_enlace_puede_adjuntar_un_documento_a_su_propia_solicitud(): void
    {
        Storage::fake('local');
        $dep = Dependencia::create(['nombre' => 'Dependencia A', 'activa' => true]);
        $enlace = $this->usuarioEnlace($dep);
        $solicitud = $this->solicitudPara($dep->id, 'NS_A-2026');

        $response = $this->actingAs($enlace)->post(route('admin.solicitudes.documentos.store', $solicitud), [
            'archivo' => UploadedFile::fake()->create('respuesta.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $this->assertEquals(1, Documento::where('solicitud_id', $solicitud->id)->count());
    }

    public function test_el_enlace_no_puede_adjuntar_documentos_a_un_expediente_ajeno(): void
    {
        Storage::fake('local');
        $depA = Dependencia::create(['nombre' => 'Dependencia A', 'activa' => true]);
        $depB = Dependencia::create(['nombre' => 'Dependencia B', 'activa' => true]);
        $enlaceA = $this->usuarioEnlace($depA);
        $solicitudB = $this->solicitudPara($depB->id, 'NS_B-2026');

        $response = $this->actingAs($enlaceA)->post(route('admin.solicitudes.documentos.store', $solicitudB), [
            'archivo' => UploadedFile::fake()->create('respuesta.pdf', 100, 'application/pdf'),
        ]);

        $response->assertForbidden();
        $this->assertEquals(0, Documento::where('solicitud_id', $solicitudB->id)->count());
    }

    public function test_el_enlace_no_puede_descargar_documentos_de_un_expediente_ajeno(): void
    {
        Storage::fake('local');
        $depA = Dependencia::create(['nombre' => 'Dependencia A', 'activa' => true]);
        $depB = Dependencia::create(['nombre' => 'Dependencia B', 'activa' => true]);
        $enlaceA = $this->usuarioEnlace($depA);
        $solicitudB = $this->solicitudPara($depB->id, 'NS_B-2026');
        $documento = Documento::create([
            'solicitud_id' => $solicitudB->id,
            'nombre' => 'archivo.pdf',
            'ruta_archivo' => 'documentos/solicitud_'.$solicitudB->id.'/archivo.pdf',
            'tipo' => 'pdf',
            'visible_ciudadano' => false,
            'subido_por_ciudadano' => false,
        ]);
        Storage::disk('local')->put($documento->ruta_archivo, 'contenido');

        $response = $this->actingAs($enlaceA)->get(route('admin.solicitudes.documentos.descargar', [$solicitudB, $documento]));

        $response->assertForbidden();
    }

    public function test_el_dashboard_redirige_al_enlace_directo_a_su_panel(): void
    {
        $dep = Dependencia::create(['nombre' => 'Dependencia A', 'activa' => true]);
        $enlace = $this->usuarioEnlace($dep);

        $response = $this->actingAs($enlace)->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.enlace.index'));
    }
}
