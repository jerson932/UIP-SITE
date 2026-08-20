<?php

namespace Tests\Feature;

use App\Models\Feriado;
use App\Models\Log;
use App\Models\Permission;
use App\Models\PlantillaCorreo;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// Configuración general: plantillas de correo y feriados (permiso
// 'configuracion.gestionar').
class ConfiguracionTest extends TestCase
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

    private function plantilla(array $overrides = []): PlantillaCorreo
    {
        return PlantillaCorreo::create(array_merge([
            'clave' => 'solicitud_recibida',
            'evento' => 'Nueva solicitud',
            'asunto_template' => 'Notificación - Contraseña No. {{contrasena}}',
            'cuerpo_template' => 'Estimado {{nombre}}, su contraseña es {{contrasena}}.',
            'activa' => true,
        ], $overrides));
    }

    public function test_index_requiere_permiso_configuracion_gestionar(): void
    {
        $user = $this->adminConPermisos([]);

        $this->actingAs($user)->get('/admin/configuracion')->assertStatus(403);
    }

    public function test_index_muestra_plantillas_y_feriados(): void
    {
        $admin = $this->adminConPermisos(['configuracion.gestionar']);
        $this->plantilla();
        Feriado::create(['fecha' => '2026-12-25', 'descripcion' => 'Navidad']);

        $response = $this->actingAs($admin)->get('/admin/configuracion');

        $response->assertStatus(200);
        $response->assertSee('Nueva solicitud');
        $response->assertSee('Navidad');
    }

    public function test_editar_plantilla_requiere_permiso(): void
    {
        $user = $this->adminConPermisos([]);
        $plantilla = $this->plantilla();

        $this->actingAs($user)->get("/admin/configuracion/plantillas/{$plantilla->id}/editar")->assertStatus(403);
    }

    public function test_editar_plantilla_renderiza_el_formulario_con_los_placeholders(): void
    {
        // A diferencia del 403 de arriba, esta sí fuerza a Blade a compilar
        // y evaluar la vista completa — un 403 nunca llega a renderizarla,
        // así que un error de sintaxis Blade en el formulario (como el
        // literal "{{placeholder}}" mal escapado que se encontró al probar
        // esto en vivo) puede pasar inadvertido si solo se prueba el 403.
        $admin = $this->adminConPermisos(['configuracion.gestionar']);
        $plantilla = $this->plantilla();

        $response = $this->actingAs($admin)->get("/admin/configuracion/plantillas/{$plantilla->id}/editar");

        $response->assertStatus(200);
        $response->assertSee($plantilla->evento);
        $response->assertSee('{{nombre}}', false);
        $response->assertSee('{{contrasena}}', false);
    }

    public function test_actualizar_plantilla_cambia_asunto_y_cuerpo_y_registra_log(): void
    {
        $admin = $this->adminConPermisos(['configuracion.gestionar']);
        $plantilla = $this->plantilla();

        $response = $this->actingAs($admin)->post("/admin/configuracion/plantillas/{$plantilla->id}", [
            'asunto_template' => 'Nuevo asunto - {{contrasena}}',
            'cuerpo_template' => 'Nuevo cuerpo para {{nombre}}.',
            'activa' => '1',
        ]);

        $response->assertRedirect(route('admin.configuracion.index'));
        $plantilla->refresh();
        $this->assertEquals('Nuevo asunto - {{contrasena}}', $plantilla->asunto_template);
        $this->assertEquals('Nuevo cuerpo para {{nombre}}.', $plantilla->cuerpo_template);
        $this->assertTrue($plantilla->activa);
        $this->assertEquals(1, Log::where('accion', 'plantilla.editada')->where('entidad_id', $plantilla->id)->count());
    }

    public function test_desmarcar_activa_desactiva_la_plantilla(): void
    {
        $admin = $this->adminConPermisos(['configuracion.gestionar']);
        $plantilla = $this->plantilla(['activa' => true]);

        // El checkbox "activa" no se envía en el POST cuando está desmarcado
        // (comportamiento estándar de un formulario HTML) — por eso no
        // aparece en el array aquí.
        $this->actingAs($admin)->post("/admin/configuracion/plantillas/{$plantilla->id}", [
            'asunto_template' => $plantilla->asunto_template,
            'cuerpo_template' => $plantilla->cuerpo_template,
        ]);

        $plantilla->refresh();
        $this->assertFalse($plantilla->activa);
    }

    public function test_no_se_puede_actualizar_plantilla_con_campos_vacios(): void
    {
        $admin = $this->adminConPermisos(['configuracion.gestionar']);
        $plantilla = $this->plantilla();

        $response = $this->actingAs($admin)->post("/admin/configuracion/plantillas/{$plantilla->id}", [
            'asunto_template' => '',
            'cuerpo_template' => '',
        ]);

        $response->assertSessionHasErrors(['asunto_template', 'cuerpo_template']);
    }

    public function test_guardar_feriado_requiere_permiso(): void
    {
        $user = $this->adminConPermisos([]);

        $this->actingAs($user)->post('/admin/configuracion/feriados', [
            'fecha' => '2026-12-25',
        ])->assertStatus(403);
    }

    public function test_guardar_feriado_lo_crea_y_registra_log(): void
    {
        $admin = $this->adminConPermisos(['configuracion.gestionar']);

        $response = $this->actingAs($admin)->post('/admin/configuracion/feriados', [
            'fecha' => '2026-12-25',
            'descripcion' => 'Navidad',
        ]);

        $response->assertRedirect(route('admin.configuracion.index'));
        // Se compara vía el modelo (no assertDatabaseHas con la fecha cruda):
        // el cast 'date' de Eloquent persiste con hora "00:00:00" en SQLite
        // (el motor de las pruebas), mientras que en Postgres (producción)
        // la columna "date" normaliza sola y queda solo la fecha — comparar
        // el string crudo de la DB sería una prueba frágil y específica del
        // motor, no del comportamiento real de la aplicación.
        $creado = Feriado::where('descripcion', 'Navidad')->first();
        $this->assertNotNull($creado);
        $this->assertEquals('2026-12-25', $creado->fecha->toDateString());
        $this->assertEquals(1, Log::where('accion', 'feriado.creado')->count());
    }

    public function test_no_se_puede_duplicar_un_feriado_en_la_misma_fecha(): void
    {
        $admin = $this->adminConPermisos(['configuracion.gestionar']);
        // Se inserta directo por query builder (no Feriado::create()) para
        // que la fecha quede guardada como "2026-12-25" puro en SQLite,
        // igual que la comparará la regla unique:feriados,fecha contra el
        // valor crudo del formulario — así la prueba no depende de cómo el
        // cast 'date' de Eloquent formatea al guardar (ver nota arriba).
        \Illuminate\Support\Facades\DB::table('feriados')->insert([
            'fecha' => '2026-12-25',
            'descripcion' => 'Navidad',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post('/admin/configuracion/feriados', [
            'fecha' => '2026-12-25',
            'descripcion' => 'Duplicado',
        ]);

        $response->assertSessionHasErrors('fecha');
    }

    public function test_eliminar_feriado_lo_borra_y_registra_log(): void
    {
        $admin = $this->adminConPermisos(['configuracion.gestionar']);
        $feriado = Feriado::create(['fecha' => '2026-12-25', 'descripcion' => 'Navidad']);

        $response = $this->actingAs($admin)->post("/admin/configuracion/feriados/{$feriado->id}/eliminar");

        $response->assertRedirect(route('admin.configuracion.index'));
        $this->assertDatabaseMissing('feriados', ['id' => $feriado->id]);
        $this->assertEquals(1, Log::where('accion', 'feriado.eliminado')->count());
    }
}
