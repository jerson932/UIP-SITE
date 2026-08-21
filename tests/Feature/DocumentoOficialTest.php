<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\Enlace;
use App\Models\Permission;
use App\Models\PlantillaDocumento;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\SolicitudHistorial;
use App\Models\User;
use App\Services\DocumentoOficialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Generación de Oficios/Providencias de traslado (Fase 19), a partir de las
// plantillas .docx reales en resources/plantillas_oficiales/
// (App\Services\DocumentoOficialService). Un mismo expediente puede generar
// varios, cada uno hacia SU PROPIA dependencia elegida al generar — por
// eso la dependencia se manda en el POST y no se lee de
// solicitud.dependencia_id (que es solo la asignación "actual").
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

    private function dependencia(string $codigo, ?string $plantillaClave, ?string $nombre = null): Dependencia
    {
        return Dependencia::create([
            'codigo' => $codigo,
            'nombre' => $nombre ?? $codigo,
            'activa' => true,
            'plantilla_clave' => $plantillaClave,
        ]);
    }

    /**
     * Texto plano de un .docx generado (concatena todos los <w:t> de
     * word/document.xml, sin las etiquetas XML) — suficiente para verificar
     * con assertStringContainsString que un valor terminó en el documento,
     * sin depender de en qué "run" exacto de PhpWord quedó cada palabra.
     */
    private function textoPlano(string $rutaAbsoluta): string
    {
        $zip = new \ZipArchive();
        $zip->open($rutaAbsoluta);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return strip_tags($xml);
    }

    /**
     * Invoca el conversor privado numeroEnLetras() del servicio, para
     * calcular el valor esperado sin repetir la tabla de números en el
     * test (y sin depender de una fecha fija que se vuelva vieja).
     */
    private function numeroEnLetras(int $n): string
    {
        $servicio = new DocumentoOficialService();
        $metodo = new \ReflectionMethod($servicio, 'numeroEnLetras');
        $metodo->setAccessible(true);

        return $metodo->invoke($servicio, $n);
    }

    private function solicitudBase(): Solicitud
    {
        $estado = SolicitudEstado::create(['clave' => 'en_seguimiento', 'etiqueta' => 'En seguimiento', 'color' => '#2a78d6', 'orden' => 2]);
        $solicitante = Solicitante::create(['nombre' => 'Juan Carlos Pérez López', 'correo' => 'interesado@example.com']);

        return Solicitud::create([
            'codigo_ns' => 'NS_TEST-2026',
            'contrasena' => '1631-2026',
            'codigo_acceso' => 'TEST-0001',
            'solicitante_id' => $solicitante->id,
            'asunto' => 'información sobre el presupuesto ejecutado del año 2025',
            'medio_recepcion' => 'electronica',
            'estado_id' => $estado->id,
            'fecha_ingreso' => now()->toDateString(),
        ]);
    }

    public function test_requiere_permiso_solicitudes_generar_documento(): void
    {
        $user = $this->adminConPermisos([]);
        $solicitud = $this->solicitudBase();
        $dep = $this->dependencia('DESPACHO', 'oficio_despacho');

        $this->actingAs($user)
            ->post(route('admin.solicitudes.documento_oficial', $solicitud), ['dependencia_id' => $dep->id, 'no_oficio' => '1-2026'])
            ->assertStatus(403);
    }

    public function test_genera_un_oficio_real_para_dependencia_mapeada_a_oficio(): void
    {
        Storage::fake('local');
        $this->plantilla('oficio_despacho', 'Oficio — Despacho Superior');
        $admin = $this->adminConPermisos(['solicitudes.generar_documento']);
        $solicitud = $this->solicitudBase();
        $dep = $this->dependencia('DESPACHO', 'oficio_despacho', 'Despacho Superior');

        $response = $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'dependencia_id' => $dep->id,
            'rc' => '4455',
            'folio' => '12',
            'no_oficio' => '99-2026',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $documento = Documento::where('solicitud_id', $solicitud->id)->first();
        $this->assertNotNull($documento);
        $this->assertEquals('docx', $documento->tipo);
        $this->assertEquals($dep->id, $documento->dependencia_id);
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
        $solicitud = $this->solicitudBase();
        $dep = $this->dependencia('PLANIF', null, 'Dirección de Planificación de este Ministerio');

        $response = $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'dependencia_id' => $dep->id,
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
        $solicitud = $this->solicitudBase();
        $dep = $this->dependencia('DESPACHO', 'oficio_despacho', 'Despacho Superior');

        // Falta no_oficio (es tipo oficio) — debe fallar la validación.
        $response = $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'dependencia_id' => $dep->id,
            'no_providencia' => '1-2026',
        ]);

        $response->assertSessionHasErrors('no_oficio');
        $this->assertEquals(0, Documento::where('solicitud_id', $solicitud->id)->count());
    }

    public function test_no_permite_generar_sin_elegir_dependencia(): void
    {
        $admin = $this->adminConPermisos(['solicitudes.generar_documento']);
        $solicitud = $this->solicitudBase();

        $response = $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'no_providencia' => '1-2026',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals(0, Documento::where('solicitud_id', $solicitud->id)->count());
    }

    public function test_una_solicitud_puede_generar_varios_documentos_hacia_dependencias_distintas(): void
    {
        Storage::fake('local');
        $this->plantilla('oficio_despacho', 'Oficio — Despacho Superior');
        $this->plantilla('providencia_generica', 'Providencia genérica');
        $this->plantilla('providencia_pnc', 'Providencia — PNC');
        $admin = $this->adminConPermisos(['solicitudes.generar_documento']);
        $solicitud = $this->solicitudBase();

        $despacho = $this->dependencia('DESPACHO', 'oficio_despacho', 'Despacho Superior');
        $planif = $this->dependencia('PLANIF', null, 'Dirección de Planificación de este Ministerio');
        $pnc = $this->dependencia('PNC', 'providencia_pnc', 'Dirección General de la Policía Nacional Civil');

        $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'dependencia_id' => $despacho->id, 'no_oficio' => '1-2026',
        ]);
        $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'dependencia_id' => $planif->id, 'no_providencia' => '2-2026',
        ]);
        $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'dependencia_id' => $pnc->id, 'no_providencia' => '3-2026',
        ]);

        $documentos = Documento::where('solicitud_id', $solicitud->id)->orderBy('id')->get();
        $this->assertCount(3, $documentos);
        $this->assertEquals([$despacho->id, $planif->id, $pnc->id], $documentos->pluck('dependencia_id')->all());

        // Ninguno se pisa con otro — cada uno tiene su propio archivo.
        $rutas = $documentos->pluck('ruta_archivo')->unique();
        $this->assertCount(3, $rutas);
        foreach ($documentos as $doc) {
            Storage::disk('local')->assertExists($doc->ruta_archivo);
        }
    }

    public function test_el_documento_generado_se_descarga_con_extension_docx(): void
    {
        // Bug reportado: "no se descargó el word". El archivo en el disco
        // SÍ se genera bien, pero el nombre de descarga ("Providencia — X")
        // no traía extensión, así que el navegador guardaba el archivo sin
        // ".docx" y Windows no sabía abrirlo con Word. Este test cubre que
        // el Content-Disposition de la descarga sí incluya la extensión.
        Storage::fake('local');
        $this->plantilla('providencia_generica', 'Providencia genérica');
        $admin = $this->adminConPermisos(['solicitudes.generar_documento', 'solicitudes.ver']);
        $solicitud = $this->solicitudBase();
        $dep = $this->dependencia('DIGCI', null, 'Dirección General de Inteligencia Civil');

        $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'dependencia_id' => $dep->id,
            'no_providencia' => '2154-2026',
        ]);

        $documento = Documento::where('solicitud_id', $solicitud->id)->first();
        $this->assertNotNull($documento);
        $this->assertStringNotContainsString('.docx', $documento->nombre);

        $response = $this->actingAs($admin)->get(route('admin.solicitudes.documentos.descargar', [$solicitud, $documento]));

        $response->assertOk();
        $disposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('.docx', $disposition);
    }

    public function test_generar_dos_veces_no_sobrescribe_el_archivo_anterior(): void
    {
        Storage::fake('local');
        $this->plantilla('providencia_generica', 'Providencia genérica');
        $admin = $this->adminConPermisos(['solicitudes.generar_documento']);
        $solicitud = $this->solicitudBase();
        $dep = $this->dependencia('PLANIF', null, 'Dirección de Planificación de este Ministerio');

        $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), ['dependencia_id' => $dep->id, 'no_providencia' => '1-2026']);
        $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), ['dependencia_id' => $dep->id, 'no_providencia' => '2-2026']);

        $documentos = Documento::where('solicitud_id', $solicitud->id)->get();
        $this->assertCount(2, $documentos);
        $this->assertNotEquals($documentos[0]->ruta_archivo, $documentos[1]->ruta_archivo);
        Storage::disk('local')->assertExists($documentos[0]->ruta_archivo);
        Storage::disk('local')->assertExists($documentos[1]->ruta_archivo);
    }

    public function test_los_numeros_de_mil_en_adelante_llevan_coma(): void
    {
        Storage::fake('local');
        $this->plantilla('providencia_generica', 'Providencia genérica');
        $admin = $this->adminConPermisos(['solicitudes.generar_documento']);
        $solicitud = $this->solicitudBase(); // contrasena '1631-2026'
        $dep = $this->dependencia('PLANIF', null, 'Dirección de Planificación de este Ministerio');

        $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'dependencia_id' => $dep->id,
            'no_providencia' => '3000-2025',
        ]);

        $documento = Documento::where('solicitud_id', $solicitud->id)->first();
        $texto = $this->textoPlano(Storage::disk('local')->path($documento->ruta_archivo));

        $this->assertStringContainsString('1,631-2026', $texto); // no_solicitud (contraseña)
        $this->assertStringContainsString('3,000-2025', $texto); // no_providencia / titulo_numero
    }

    public function test_los_numeros_menores_a_mil_no_llevan_coma(): void
    {
        Storage::fake('local');
        $this->plantilla('providencia_generica', 'Providencia genérica');
        $admin = $this->adminConPermisos(['solicitudes.generar_documento']);
        $solicitud = $this->solicitudBase();
        $solicitud->update(['contrasena' => '99-2026']);
        $dep = $this->dependencia('PLANIF', null, 'Dirección de Planificación de este Ministerio');

        $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'dependencia_id' => $dep->id,
            'no_providencia' => '5-2026',
        ]);

        $documento = Documento::where('solicitud_id', $solicitud->id)->first();
        $texto = $this->textoPlano(Storage::disk('local')->path($documento->ruta_archivo));

        $this->assertStringContainsString('99-2026', $texto);
        $this->assertStringNotContainsString('9,9-2026', $texto);
        $this->assertStringContainsString('5-2026', $texto);
    }

    public function test_la_fecha_del_documento_queda_completamente_en_letras(): void
    {
        Storage::fake('local');
        $this->plantilla('providencia_generica', 'Providencia genérica');
        $admin = $this->adminConPermisos(['solicitudes.generar_documento']);
        $solicitud = $this->solicitudBase();
        $dep = $this->dependencia('PLANIF', null, 'Dirección de Planificación de este Ministerio');

        $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'dependencia_id' => $dep->id,
            'no_providencia' => '1-2026',
        ]);

        $documento = Documento::where('solicitud_id', $solicitud->id)->first();
        $texto = mb_strtoupper($this->textoPlano(Storage::disk('local')->path($documento->ruta_archivo)), 'UTF-8');

        $hoy = now();
        $diaEnLetras = mb_strtoupper($this->numeroEnLetras($hoy->day), 'UTF-8');
        $anioEnLetras = mb_strtoupper($this->numeroEnLetras($hoy->year), 'UTF-8');

        $this->assertStringContainsString($diaEnLetras.' DE', $texto);
        $this->assertStringContainsString($anioEnLetras, $texto);
        // La fecha numérica ya no debe aparecer sola (día y año en letras).
        $this->assertStringNotContainsString((string) $hoy->day.' DE', $texto);
        $this->assertStringNotContainsString('DE '.$hoy->year, $texto);
    }

    public function test_el_folio_solo_se_asigna_en_el_primer_documento_generado(): void
    {
        Storage::fake('local');
        $this->plantilla('providencia_generica', 'Providencia genérica');
        $admin = $this->adminConPermisos(['solicitudes.generar_documento']);
        $solicitud = $this->solicitudBase();
        $dep = $this->dependencia('PLANIF', null, 'Dirección de Planificación de este Ministerio');

        $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'dependencia_id' => $dep->id, 'folio' => '25', 'no_providencia' => '1-2026',
        ]);
        $solicitud->refresh();
        $this->assertEquals('25', $solicitud->folio);

        $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'dependencia_id' => $dep->id, 'folio' => '99', 'no_providencia' => '2-2026',
        ]);
        $solicitud->refresh();
        $this->assertEquals('25', $solicitud->folio); // el segundo folio ('99') se ignora
    }

    public function test_generar_un_documento_asigna_automaticamente_la_dependencia_y_su_unico_enlace(): void
    {
        Storage::fake('local');
        $this->plantilla('providencia_generica', 'Providencia genérica');
        $admin = $this->adminConPermisos(['solicitudes.generar_documento']);
        $solicitud = $this->solicitudBase();
        $this->assertNull($solicitud->dependencia_id);

        $dep = $this->dependencia('PLANIF', null, 'Dirección de Planificación de este Ministerio');
        $enlace = Enlace::create(['dependencia_id' => $dep->id, 'nombre' => 'Lic. Marta Solís', 'activo' => true]);

        $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'dependencia_id' => $dep->id, 'no_providencia' => '1-2026',
        ]);

        $solicitud->refresh();
        $this->assertEquals($dep->id, $solicitud->dependencia_id);
        $this->assertEquals($enlace->id, $solicitud->enlace_id);
        $this->assertEquals(
            1,
            SolicitudHistorial::where('solicitud_id', $solicitud->id)
                ->where('descripcion', 'like', '%asignado automáticamente%')
                ->count()
        );
    }

    public function test_no_asigna_enlace_automatico_si_la_dependencia_tiene_varios_activos(): void
    {
        Storage::fake('local');
        $this->plantilla('providencia_generica', 'Providencia genérica');
        $admin = $this->adminConPermisos(['solicitudes.generar_documento']);
        $solicitud = $this->solicitudBase();
        $dep = $this->dependencia('PLANIF', null, 'Dirección de Planificación de este Ministerio');
        Enlace::create(['dependencia_id' => $dep->id, 'nombre' => 'Enlace Uno', 'activo' => true]);
        Enlace::create(['dependencia_id' => $dep->id, 'nombre' => 'Enlace Dos', 'activo' => true]);

        $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'dependencia_id' => $dep->id, 'no_providencia' => '1-2026',
        ]);

        $solicitud->refresh();
        $this->assertEquals($dep->id, $solicitud->dependencia_id);
        $this->assertNull($solicitud->enlace_id);
    }

    public function test_generar_hacia_la_misma_dependencia_otra_vez_no_repite_el_mensaje_de_asignacion(): void
    {
        Storage::fake('local');
        $this->plantilla('providencia_generica', 'Providencia genérica');
        $admin = $this->adminConPermisos(['solicitudes.generar_documento']);
        $solicitud = $this->solicitudBase();
        $dep = $this->dependencia('PLANIF', null, 'Dirección de Planificación de este Ministerio');

        $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'dependencia_id' => $dep->id, 'no_providencia' => '1-2026',
        ]);
        $this->actingAs($admin)->post(route('admin.solicitudes.documento_oficial', $solicitud), [
            'dependencia_id' => $dep->id, 'no_providencia' => '2-2026',
        ]);

        $this->assertEquals(
            1,
            SolicitudHistorial::where('solicitud_id', $solicitud->id)
                ->where('descripcion', 'like', '%asignado automáticamente%')
                ->count()
        );
    }
}
