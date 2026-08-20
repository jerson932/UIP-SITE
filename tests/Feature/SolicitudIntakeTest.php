<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

// Los dos puntos de entrada para CREAR una solicitud: el formulario
// público (sin sesión) y el registro interno de la UIP (con permiso
// 'solicitudes.crear').
class SolicitudIntakeTest extends TestCase
{
    use RefreshDatabase;

    private function seedEstadoInicial(): SolicitudEstado
    {
        return SolicitudEstado::create(['clave' => 'pendiente_validacion', 'etiqueta' => 'Pendiente de validación', 'color' => '#eda100', 'orden' => 1]);
    }

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

    // --- Formulario público ---

    public function test_formulario_publico_es_accesible_sin_sesion(): void
    {
        $response = $this->get('/solicitudes/nueva');

        $response->assertStatus(200);
        $response->assertSee('Presentar una solicitud');
    }

    public function test_formulario_publico_crea_solicitud_solicitante_e_historial(): void
    {
        Mail::fake();
        RateLimiter::clear('nueva_solicitud|127.0.0.1');
        $this->seedEstadoInicial();

        $response = $this->post('/solicitudes/nueva', [
            'nombre' => 'Juana Pérez',
            'correo' => 'juana@example.com',
            'telefono' => '5555-1234',
            'genero' => 'Femenino',
            'rango_edad' => '26-35',
            'departamento' => 'Guatemala',
            'asunto' => 'Solicito el presupuesto ejecutado del año 2025.',
        ]);

        $response->assertStatus(200);
        $response->assertSee('Tu solicitud fue registrada');

        $solicitud = Solicitud::first();
        $this->assertNotNull($solicitud);
        $this->assertMatchesRegularExpression('/^NS_\d{2}-\d{4}$/', $solicitud->codigo_ns);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $solicitud->codigo_acceso);
        $this->assertEquals('electronica', $solicitud->medio_recepcion);
        $this->assertEquals('pendiente_validacion', $solicitud->estado->clave);
        $this->assertNull($solicitud->creado_por_user_id);
        $this->assertEquals('Juana Pérez', $solicitud->solicitante->nombre);
        $this->assertEquals(1, $solicitud->solicitud_historial()->where('tipo_actor', 'ciudadano')->count());
    }

    public function test_formulario_publico_exige_correo_y_asunto_minimo(): void
    {
        RateLimiter::clear('nueva_solicitud|127.0.0.1');
        $this->seedEstadoInicial();

        $response = $this->post('/solicitudes/nueva', [
            'nombre' => 'Juana Pérez',
            'asunto' => 'muy corto',
        ]);

        $response->assertSessionHasErrors(['correo', 'asunto']);
    }

    public function test_formulario_publico_reutiliza_solicitante_existente_por_correo(): void
    {
        Mail::fake();
        RateLimiter::clear('nueva_solicitud|127.0.0.1');
        $this->seedEstadoInicial();
        $existente = Solicitante::create(['nombre' => 'Nombre Viejo', 'correo' => 'repetido@example.com']);

        $this->post('/solicitudes/nueva', [
            'nombre' => 'Nombre Nuevo',
            'correo' => 'repetido@example.com',
            'asunto' => 'Solicito copia del contrato de mantenimiento vehicular.',
        ]);

        $this->assertEquals(1, Solicitante::where('correo', 'repetido@example.com')->count());
        $existente->refresh();
        $this->assertEquals('Nombre Nuevo', $existente->nombre);
    }

    public function test_formulario_publico_respeta_el_limite_de_intentos(): void
    {
        Mail::fake();
        RateLimiter::clear('nueva_solicitud|127.0.0.1');
        $this->seedEstadoInicial();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/solicitudes/nueva', [
                'nombre' => "Persona {$i}",
                'correo' => "persona{$i}@example.com",
                'asunto' => 'Solicito información pública de prueba número '.$i.'.',
            ]);
        }

        $response = $this->post('/solicitudes/nueva', [
            'nombre' => 'Persona extra',
            'correo' => 'extra@example.com',
            'asunto' => 'Esta solicitud debería quedar bloqueada por el límite de envíos.',
        ]);

        $response->assertSessionHasErrors('asunto');
        $this->assertEquals(5, Solicitud::count());
    }

    // --- Registro interno (UIP) ---

    public function test_registro_interno_requiere_permiso_solicitudes_crear(): void
    {
        $user = $this->adminConPermisos([]);

        $this->actingAs($user)->get('/admin/solicitudes/nueva')->assertStatus(403);
        $this->actingAs($user)->post('/admin/solicitudes', ['nombre' => 'X'])->assertStatus(403);
    }

    public function test_registro_interno_crea_solicitud_con_medio_seleccionado_y_creado_por(): void
    {
        Mail::fake();
        $this->seedEstadoInicial();
        $admin = $this->adminConPermisos(['solicitudes.crear', 'solicitudes.ver']);

        $response = $this->actingAs($admin)->post('/admin/solicitudes', [
            'nombre' => 'Carlos López',
            'correo' => 'carlos@example.com',
            'medio_recepcion' => 'fisica',
            'asunto' => 'Solicita copia de un contrato de arrendamiento vigente.',
        ]);

        $solicitud = Solicitud::first();
        $response->assertRedirect(route('admin.solicitudes.show', $solicitud));
        $this->assertEquals('fisica', $solicitud->medio_recepcion);
        $this->assertEquals($admin->id, $solicitud->creado_por_user_id);
        $this->assertEquals(1, $solicitud->solicitud_historial()->where('tipo_actor', 'administrador')->count());
    }

    public function test_registro_interno_permite_correo_vacio(): void
    {
        $this->seedEstadoInicial();
        $admin = $this->adminConPermisos(['solicitudes.crear']);

        $response = $this->actingAs($admin)->post('/admin/solicitudes', [
            'nombre' => 'Sin Correo',
            'medio_recepcion' => 'correo',
            'asunto' => 'Solicitud registrada sin correo electrónico del interesado.',
        ]);

        $response->assertRedirect();
        $this->assertEquals(1, Solicitud::count());
    }

    public function test_codigos_generados_son_unicos_entre_varias_solicitudes(): void
    {
        Mail::fake();
        RateLimiter::clear('nueva_solicitud|127.0.0.1');
        $this->seedEstadoInicial();

        for ($i = 0; $i < 4; $i++) {
            $this->post('/solicitudes/nueva', [
                'nombre' => "Persona {$i}",
                'correo' => "unico{$i}@example.com",
                'asunto' => 'Solicitud de prueba para verificar unicidad de código '.$i.'.',
            ]);
        }

        $this->assertEquals(4, Solicitud::distinct('codigo_ns')->count('codigo_ns'));
        $this->assertEquals(4, Solicitud::distinct('codigo_acceso')->count('codigo_acceso'));
    }
}
