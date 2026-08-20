<?php

namespace Tests\Feature;

use App\Models\Dependencia;
use App\Models\Log;
use App\Models\Permission;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// Reportes y exportación a CSV (permiso 'reportes.exportar').
class ReportesTest extends TestCase
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

    private function crearSolicitud(array $overrides = []): Solicitud
    {
        static $n = 0;
        $n++;

        $solicitante = Solicitante::create(['nombre' => "Ciudadano {$n}", 'correo' => "ciudadano{$n}@example.com"]);

        return Solicitud::create(array_merge([
            'codigo_ns' => "NS_{$n}-2026",
            'codigo_acceso' => "ACC-{$n}",
            'solicitante_id' => $solicitante->id,
            'asunto' => "Solicitud de prueba {$n}.",
            'medio_recepcion' => 'electronica',
            'fecha_ingreso' => now()->toDateString(),
        ], $overrides));
    }

    public function test_index_requiere_permiso_reportes_exportar(): void
    {
        $user = $this->adminConPermisos([]);

        $this->actingAs($user)->get('/admin/reportes')->assertStatus(403);
    }

    public function test_exportar_requiere_permiso(): void
    {
        $user = $this->adminConPermisos([]);

        $this->actingAs($user)->get('/admin/reportes/exportar')->assertStatus(403);
    }

    public function test_index_muestra_conteos_correctos_por_estado_y_dependencia(): void
    {
        $admin = $this->adminConPermisos(['reportes.exportar']);
        $enSeguimiento = SolicitudEstado::create(['clave' => 'en_seguimiento', 'etiqueta' => 'En seguimiento', 'color' => '#1baf7a', 'orden' => 1]);
        $finalizada = SolicitudEstado::create(['clave' => 'finalizada', 'etiqueta' => 'Finalizada', 'color' => '#52514e', 'orden' => 2, 'es_final' => true]);
        $dep = Dependencia::create(['nombre' => 'Dirección de Pruebas', 'activa' => true]);

        $this->crearSolicitud(['estado_id' => $enSeguimiento->id, 'dependencia_id' => $dep->id]);
        $this->crearSolicitud(['estado_id' => $enSeguimiento->id]);
        $this->crearSolicitud(['estado_id' => $finalizada->id, 'dependencia_id' => $dep->id]);

        $response = $this->actingAs($admin)->get('/admin/reportes');

        $response->assertStatus(200);
        $response->assertSee('Total de solicitudes');
        $response->assertSeeInOrder(['En seguimiento']);
        $response->assertSee('Dirección de Pruebas');
    }

    public function test_index_filtra_por_rango_de_fechas_de_ingreso(): void
    {
        $admin = $this->adminConPermisos(['reportes.exportar']);
        $estado = SolicitudEstado::create(['clave' => 'en_seguimiento', 'etiqueta' => 'En seguimiento', 'color' => '#1baf7a', 'orden' => 1]);

        $this->crearSolicitud(['estado_id' => $estado->id, 'fecha_ingreso' => now()->subMonths(2)->toDateString()]);
        $this->crearSolicitud(['estado_id' => $estado->id, 'fecha_ingreso' => now()->toDateString()]);

        $response = $this->actingAs($admin)->get('/admin/reportes?desde='.now()->subDays(3)->toDateString());

        $response->assertStatus(200);
        // El helper del controlador expone $total directamente a la vista.
        $response->assertViewHas('total', 1);
    }

    public function test_exportar_genera_csv_con_las_filas_esperadas_y_respeta_filtros(): void
    {
        $admin = $this->adminConPermisos(['reportes.exportar']);
        $estado = SolicitudEstado::create(['clave' => 'en_seguimiento', 'etiqueta' => 'En seguimiento', 'color' => '#1baf7a', 'orden' => 1]);
        $otroEstado = SolicitudEstado::create(['clave' => 'finalizada', 'etiqueta' => 'Finalizada', 'color' => '#52514e', 'orden' => 2, 'es_final' => true]);

        $this->crearSolicitud(['estado_id' => $estado->id, 'codigo_ns' => 'NS_INCLUIDA-2026']);
        $this->crearSolicitud(['estado_id' => $otroEstado->id, 'codigo_ns' => 'NS_EXCLUIDA-2026']);

        $response = $this->actingAs($admin)->get('/admin/reportes/exportar?estado=en_seguimiento');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $contenido = $response->streamedContent();
        $this->assertStringContainsString('NS_INCLUIDA-2026', $contenido);
        $this->assertStringNotContainsString('NS_EXCLUIDA-2026', $contenido);
        $this->assertStringContainsString('Código NS', $contenido);
    }

    public function test_exportar_registra_auditoria_en_logs(): void
    {
        $admin = $this->adminConPermisos(['reportes.exportar']);
        $estado = SolicitudEstado::create(['clave' => 'en_seguimiento', 'etiqueta' => 'En seguimiento', 'color' => '#1baf7a', 'orden' => 1]);
        $this->crearSolicitud(['estado_id' => $estado->id]);

        $response = $this->actingAs($admin)->get('/admin/reportes/exportar');
        $response->streamedContent();

        $log = Log::where('accion', 'reporte.exportado')->first();
        $this->assertNotNull($log);
        $this->assertEquals($admin->id, $log->user_id);
        $this->assertEquals(1, $log->detalle['total_filas']);
    }
}
