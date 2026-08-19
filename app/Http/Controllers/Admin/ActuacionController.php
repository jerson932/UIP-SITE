<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aclaracion;
use App\Models\Actuacion;
use App\Models\Ampliacion;
use App\Models\Configuracion;
use App\Models\Feriado;
use App\Models\Prorroga;
use App\Models\RecursoRevision;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\SolicitudHistorial;
use App\Services\NotificacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

// Formularios reales para registrar actuaciones sobre un expediente ya
// aceptado: prórroga, aclaración al ciudadano, ampliación y recurso de
// revisión (spec tabla 7 "Registro formal de cada actuación" + tabla 13,
// "Acción manual -> El sistema debe hacer automáticamente"). Cada acción
// crea su propio registro (prorrogas/aclaraciones/ampliaciones/recursos_
// revision), una fila en "actuaciones" (el registro formal, spec tabla 7)
// y una entrada en solicitud_historial — mismo patrón que
// SolicitudActionController (Fase 6).
//
// El envío real de correo (SMTP, Fase 11) lo resuelve NotificacionService a
// partir de las plantillas ya sembradas en "plantillas_correo".
class ActuacionController extends Controller
{
    public function __construct(private NotificacionService $notificaciones)
    {
    }

    private function historial(Solicitud $solicitud, string $texto, ?int $estadoAnteriorId = null, ?int $estadoNuevoId = null): void
    {
        SolicitudHistorial::create([
            'solicitud_id' => $solicitud->id,
            'user_id' => auth()->id(),
            'tipo_actor' => 'administrador',
            'descripcion' => $texto,
            'estado_anterior_id' => $estadoAnteriorId,
            'estado_nuevo_id' => $estadoNuevoId,
        ]);
    }

    private function registrarActuacion(Solicitud $solicitud, string $tipo, string $descripcion): Actuacion
    {
        return Actuacion::create([
            'solicitud_id' => $solicitud->id,
            'tipo' => $tipo,
            'iniciado_por' => 'uip',
            'user_id' => auth()->id(),
            'fecha' => now()->toDateString(),
            'descripcion' => $descripcion,
        ]);
    }

    private function cambiarEstado(Solicitud $solicitud, string $clave): void
    {
        $nuevoEstado = SolicitudEstado::where('clave', $clave)->first();
        if ($nuevoEstado && $solicitud->estado_id !== $nuevoEstado->id) {
            $solicitud->update(['estado_id' => $nuevoEstado->id]);
        }
    }

    public function crearProrroga(Request $request, Solicitud $solicitud): RedirectResponse
    {
        if (in_array($solicitud->estado?->clave, ['pendiente_validacion', 'finalizada', 'rechazada'], true)) {
            return back()->with('error', 'Solo se puede registrar una prórroga sobre un expediente en trámite.');
        }
        if (! $solicitud->fecha_vencimiento) {
            return back()->with('error', 'Debe asignarse la contraseña (y su plazo) antes de registrar una prórroga.');
        }

        $data = $request->validate([
            'fecha_nueva' => ['required', 'date', 'after:'.$solicitud->fecha_vencimiento->toDateString()],
            'motivo' => ['required', 'string', 'max:2000'],
        ]);

        $estadoAnterior = $solicitud->estado_id;
        $fechaAnterior = $solicitud->fecha_vencimiento->toDateString();

        $actuacion = $this->registrarActuacion($solicitud, 'prorroga', $data['motivo']);

        Prorroga::create([
            'solicitud_id' => $solicitud->id,
            'actuacion_id' => $actuacion->id,
            'user_id' => auth()->id(),
            'fecha_anterior' => $fechaAnterior,
            'fecha_nueva' => $data['fecha_nueva'],
            'solicitada_en' => now()->toDateString(),
            'motivo' => $data['motivo'],
        ]);

        $solicitud->update(['fecha_vencimiento' => $data['fecha_nueva']]);
        $this->cambiarEstado($solicitud, 'prorroga');

        $correo = $this->notificaciones->enviar($solicitud, 'prorroga', [], auth()->id());

        $this->historial(
            $solicitud,
            "Prórroga registrada: plazo extendido de {$fechaAnterior} a {$data['fecha_nueva']}. Motivo: {$data['motivo']}",
            $estadoAnterior,
            $solicitud->estado_id
        );

        return back()->with('status', 'Prórroga registrada. '.$this->notificaciones->describirResultado($correo));
    }

    public function crearAclaracion(Request $request, Solicitud $solicitud): RedirectResponse
    {
        if (in_array($solicitud->estado?->clave, ['pendiente_validacion', 'finalizada', 'rechazada'], true)) {
            return back()->with('error', 'Solo se puede solicitar aclaración sobre un expediente en trámite.');
        }

        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:2000'],
        ]);

        $plazo = (int) (Configuracion::where('clave', 'plazo_aclaracion_dias_habiles')->value('valor') ?? 2);
        $fechaSolicitud = Carbon::today();
        $fechaLimite = Feriado::sumarDiasHabiles($fechaSolicitud->copy(), $plazo);

        $estadoAnterior = $solicitud->estado_id;
        $actuacion = $this->registrarActuacion($solicitud, 'aclaracion', $data['motivo']);

        Aclaracion::create([
            'solicitud_id' => $solicitud->id,
            'actuacion_id' => $actuacion->id,
            'user_id' => auth()->id(),
            'fecha_solicitud' => $fechaSolicitud->toDateString(),
            'plazo_dias_habiles' => $plazo,
            'fecha_limite_respuesta' => $fechaLimite->toDateString(),
            'estado' => 'pendiente',
        ]);

        $solicitud->update(['requiere_aclaracion' => true]);
        $this->cambiarEstado($solicitud, 'aclaracion_solicitada');

        $correo = $this->notificaciones->enviar($solicitud, 'aclaracion_solicitada', [], auth()->id());

        $this->historial(
            $solicitud,
            "Aclaración solicitada al interesado (plazo: {$plazo} días hábiles, vence {$fechaLimite->toDateString()}). Motivo: {$data['motivo']}",
            $estadoAnterior,
            $solicitud->estado_id
        );

        return back()->with('status', 'Aclaración registrada. '.$this->notificaciones->describirResultado($correo));
    }

    public function crearAmpliacion(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $data = $request->validate([
            'descripcion' => ['required', 'string', 'max:2000'],
        ]);

        $finalizada = $solicitud->estado?->clave === 'finalizada';
        $estado = $finalizada ? 'rechazada_no_regulada' : 'recibida';

        $this->registrarActuacion($solicitud, 'ampliacion', $data['descripcion']);

        Ampliacion::create([
            'solicitud_id' => $solicitud->id,
            'fecha_solicitud' => now()->toDateString(),
            'descripcion' => $data['descripcion'],
            'estado' => $estado,
        ]);

        // Solo existe plantilla de correo (validada contra los correos
        // reales de la UIP) para el caso "no regulada" — una ampliación
        // sobre un expediente todavía en trámite no dispara notificación
        // automática, se resuelve como parte del seguimiento normal.
        $correo = $finalizada
            ? $this->notificaciones->enviar($solicitud, 'ampliacion_no_procedente', [], auth()->id())
            : null;

        $this->historial(
            $solicitud,
            $finalizada
                ? 'Ampliación recibida después de la resolución: no está regulada por la Ley de Acceso a la Información Pública. Se indicó al interesado presentar una solicitud nueva.'
                : 'Ampliación registrada: '.$data['descripcion']
        );

        return back()->with(
            'status',
            $finalizada
                ? 'Ampliación registrada como no regulada (el expediente ya está finalizado). '.$this->notificaciones->describirResultado($correo)
                : 'Ampliación registrada.'
        );
    }

    public function crearRecurso(Request $request, Solicitud $solicitud): RedirectResponse
    {
        if ($solicitud->estado?->clave === 'pendiente_validacion') {
            return back()->with('error', 'La solicitud debe haber sido validada antes de registrar un recurso de revisión.');
        }

        $data = $request->validate([
            'correlativo' => ['required', 'string', 'max:255', 'unique:recursos_revision,correlativo'],
            'motivo' => ['required', 'string', 'max:2000'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            'correlativo.unique' => 'Ese correlativo ya está asignado a otro recurso de revisión.',
        ]);

        $estadoAnterior = $solicitud->estado_id;
        $this->registrarActuacion($solicitud, 'recurso_revision', $data['motivo']);

        RecursoRevision::create([
            'solicitud_id' => $solicitud->id,
            'correlativo' => $data['correlativo'],
            'fecha_presentacion' => now()->toDateString(),
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
            'motivo' => $data['motivo'],
            'estado' => 'recibido',
        ]);

        $this->cambiarEstado($solicitud, 'recurso_revision');

        $correo = $this->notificaciones->enviar($solicitud, 'recurso_recibido', [
            'correlativo_recurso' => $data['correlativo'],
        ], auth()->id());

        $this->historial(
            $solicitud,
            "Recurso de revisión No. {$data['correlativo']} registrado. Motivo: {$data['motivo']}",
            $estadoAnterior,
            $solicitud->estado_id
        );

        return back()->with('status', 'Recurso de revisión registrado. '.$this->notificaciones->describirResultado($correo));
    }
}
