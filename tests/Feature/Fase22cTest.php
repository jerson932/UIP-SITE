<?php

namespace Tests\Feature;

use App\Mail\PlantillaCorreoMail;
use App\Models\Permission;
use App\Models\PlantillaCorreo;
use App\Models\RecursoRevision;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// Fase 22c: seguimiento a la revisión de Fase 22b — un recurso de revisión
// queda "visible" (abierto) hasta que la UIP registra y notifica su
// resolución ("el recurso es visible hasta que se notifique la
// resolucion").
class Fase22cTest extends TestCase
{
    use RefreshDatabase;

    private function seedEstados(): void
    {
        foreach ([
            ['clave' => 'en_seguimiento', 'etiqueta' => 'En seguimiento', 'color' => '#1baf7a', 'orden' => 1],
            ['clave' => 'recurso_revision', 'etiqueta' => 'Recurso de revisión', 'color' => '#e87ba4', 'orden' => 2],
            ['clave' => 'finalizada', 'etiqueta' => 'Finalizada', 'color' => '#52514e', 'orden' => 3, 'es_final' => true],
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

    private function solicitud(): Solicitud
    {
        $estado = SolicitudEstado::where('clave', 'en_seguimiento')->firstOrFail();
        $solicitante = Solicitante::create(['nombre' => 'Interesado de Prueba', 'correo' => 'interesado@example.com']);

        return Solicitud::create([
            'codigo_ns' => 'NS_'.uniqid(),
            'codigo_acceso' => 'ACC-'.strtoupper(uniqid()),
            'solicitante_id' => $solicitante->id,
            'asunto' => 'Asunto de prueba.',
            'medio_recepcion' => 'electronica',
            'estado_id' => $estado->id,
            'contrasena' => '10-2026',
            'fecha_ingreso' => now()->toDateString(),
        ]);
    }

    private function plantillaRecursoResuelto(): void
    {
        PlantillaCorreo::create([
            'clave' => 'recurso_resuelto',
            'evento' => 'test',
            'asunto_template' => 'Resolución del Recurso de Revisión No. {{correlativo_recurso}}',
            'cuerpo_template' => 'Se resolvió el recurso {{correlativo_recurso}}.',
            'activa' => true,
        ]);
    }

    public function test_no_se_puede_resolver_un_recurso_sin_correlativo(): void
    {
        $this->seedEstados();
        $admin = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.recurso']);
        $solicitud = $this->solicitud();
        $recurso = RecursoRevision::create([
            'solicitud_id' => $solicitud->id,
            'correlativo' => null,
            'fecha_presentacion' => now()->toDateString(),
            'motivo' => 'Motivo del ciudadano.',
            'estado' => 'recibido',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.solicitudes.recurso.resolver', [$solicitud, $recurso]), [
            'resolucion' => 'Se confirma la resolución original.',
        ]);

        $response->assertRedirect();
        $recurso->refresh();
        $this->assertNotEquals('resuelto', $recurso->estado);
        $this->assertNull($recurso->fecha_resolucion);
    }

    public function test_resolver_recurso_marca_estado_fecha_y_envia_correo(): void
    {
        $this->seedEstados();
        $this->plantillaRecursoResuelto();
        Mail::fake();
        $admin = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.recurso']);
        $solicitud = $this->solicitud();
        $recurso = RecursoRevision::create([
            'solicitud_id' => $solicitud->id,
            'correlativo' => '40-2026',
            'fecha_presentacion' => now()->toDateString(),
            'motivo' => 'Motivo del ciudadano.',
            'estado' => 'recibido',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.solicitudes.recurso.resolver', [$solicitud, $recurso]), [
            'resolucion' => 'Se confirma la resolución original, la información entregada fue completa.',
        ]);

        $response->assertRedirect();
        $recurso->refresh();
        $this->assertEquals('resuelto', $recurso->estado);
        $this->assertNotNull($recurso->fecha_resolucion);

        $correo = $solicitud->correos_enviados()->first();
        $this->assertNotNull($correo);
        $this->assertStringContainsString('40-2026', $correo->asunto);
        Mail::assertSent(PlantillaCorreoMail::class);

        $this->assertEquals(
            1,
            $solicitud->solicitud_historial()->where('descripcion', 'like', 'Recurso de revisión No. 40-2026 resuelto%')->count()
        );
    }

    public function test_no_se_puede_resolver_dos_veces(): void
    {
        $this->seedEstados();
        $this->plantillaRecursoResuelto();
        Mail::fake();
        $admin = $this->adminConPermisos(['solicitudes.ver', 'actuaciones.recurso']);
        $solicitud = $this->solicitud();
        $recurso = RecursoRevision::create([
            'solicitud_id' => $solicitud->id,
            'correlativo' => '41-2026',
            'fecha_presentacion' => now()->toDateString(),
            'motivo' => 'Motivo del ciudadano.',
            'estado' => 'resuelto',
            'fecha_resolucion' => now()->toDateString(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.solicitudes.recurso.resolver', [$solicitud, $recurso]), [
            'resolucion' => 'Intento de resolver otra vez.',
        ]);

        $response->assertRedirect();
        $this->assertEquals(0, $solicitud->correos_enviados()->count());
    }

    public function test_requiere_permiso_actuaciones_recurso(): void
    {
        $this->seedEstados();
        $admin = $this->adminConPermisos(['solicitudes.ver']);
        $solicitud = $this->solicitud();
        $recurso = RecursoRevision::create([
            'solicitud_id' => $solicitud->id,
            'correlativo' => '42-2026',
            'fecha_presentacion' => now()->toDateString(),
            'motivo' => 'Motivo del ciudadano.',
            'estado' => 'recibido',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.solicitudes.recurso.resolver', [$solicitud, $recurso]), [
            'resolucion' => 'Intento sin permiso.',
        ]);

        $response->assertStatus(403);
    }
}
