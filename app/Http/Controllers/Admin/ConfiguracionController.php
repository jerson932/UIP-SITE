<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\Feriado;
use App\Models\Log;
use App\Models\PlantillaCorreo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Configuración general (permiso 'configuracion.gestionar' — Administrador
// y Coordinador, según PermissionSeeder): plantillas de correo (Fase 11) y
// calendario de días feriados (usado por Feriado::sumarDiasHabiles() /
// Solicitud::diasHabilesRestantes() en todo el sistema). Antes de esta
// pantalla, ambas solo se podían tocar editando los seeders directamente.
class ConfiguracionController extends Controller
{
    private function log(string $accion, string $entidad, ?int $entidadId, array $detalle = []): void
    {
        Log::create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'ip' => request()->ip(),
            'detalle' => $detalle,
        ]);
    }

    public function index(): View
    {
        return view('admin.configuracion.index', [
            'plantillas' => PlantillaCorreo::orderBy('evento')->get(),
            'feriados' => Feriado::orderBy('fecha')->get(),
            'correoUip' => Configuracion::where('clave', 'correo_uip')->value('valor'),
        ]);
    }

    // Correo que recibe el aviso interno de "nueva solicitud" (Fase 22b) —
    // reutiliza la clave "correo_uip" ya sembrada (Correo institucional de
    // la UIP) en vez de agregar una tabla/pantalla nueva de configuración
    // genérica clave-valor, que hoy no existe en este panel.
    public function actualizarCorreoUip(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'correo_uip' => ['required', 'email', 'max:255'],
        ]);

        Configuracion::updateOrCreate(
            ['clave' => 'correo_uip'],
            ['valor' => $data['correo_uip'], 'descripcion' => 'Correo institucional de la UIP']
        );

        $this->log('configuracion.correo_uip_actualizado', 'configuracion', null, ['valor' => $data['correo_uip']]);

        return redirect()->route('admin.configuracion.index')->with('status', 'Correo de avisos internos actualizado.');
    }

    public function editarPlantilla(PlantillaCorreo $plantilla): View
    {
        return view('admin.configuracion.plantilla-editar', ['plantilla' => $plantilla]);
    }

    public function actualizarPlantilla(Request $request, PlantillaCorreo $plantilla): RedirectResponse
    {
        $data = $request->validate([
            'asunto_template' => ['required', 'string', 'max:255'],
            'cuerpo_template' => ['required', 'string', 'max:10000'],
            'activa' => ['nullable', 'boolean'],
        ]);

        $plantilla->update([
            'asunto_template' => $data['asunto_template'],
            'cuerpo_template' => $data['cuerpo_template'],
            'activa' => $request->boolean('activa'),
        ]);

        $this->log('plantilla.editada', 'plantilla_correo', $plantilla->id, ['clave' => $plantilla->clave, 'activa' => $plantilla->activa]);

        return redirect()->route('admin.configuracion.index')->with('status', "Plantilla \"{$plantilla->evento}\" actualizada.");
    }

    public function guardarFeriado(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date', 'unique:feriados,fecha'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ], [
            'fecha.unique' => 'Esa fecha ya está registrada como feriado.',
        ]);

        $feriado = Feriado::create($data);

        $this->log('feriado.creado', 'feriado', $feriado->id, ['fecha' => $feriado->fecha->toDateString(), 'descripcion' => $feriado->descripcion]);

        return redirect()->route('admin.configuracion.index')->with('status', 'Feriado agregado. Ya se toma en cuenta en el cálculo de días hábiles.');
    }

    public function eliminarFeriado(Feriado $feriado): RedirectResponse
    {
        $this->log('feriado.eliminado', 'feriado', $feriado->id, ['fecha' => $feriado->fecha->toDateString(), 'descripcion' => $feriado->descripcion]);

        $feriado->delete();

        return redirect()->route('admin.configuracion.index')->with('status', 'Feriado eliminado.');
    }
}
