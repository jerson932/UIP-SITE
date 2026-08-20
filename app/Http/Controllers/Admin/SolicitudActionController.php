<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\Enlace;
use App\Models\Solicitud;
use App\Models\SolicitudEstado;
use App\Models\SolicitudHistorial;
use App\Services\NotificacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

// Acciones administrativas manuales sobre un expediente (spec tabla 13,
// "Acción manual -> El sistema debe hacer automáticamente"). Cada método
// aquí es la contraparte real de un botón del prototipo HTML
// (aceptarSolicitud(), asignarNumero(), etc.) y deja su propia entrada en
// solicitud_historial, tal como exige la spec ("...registrar historial").
//
// El envío real de correo (SMTP, Fase 11) lo resuelve NotificacionService a
// partir de las plantillas ya sembradas en "plantillas_correo".
class SolicitudActionController extends Controller
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

    public function aceptar(Solicitud $solicitud): RedirectResponse
    {
        if ($solicitud->estado?->clave !== 'pendiente_validacion') {
            return back()->with('error', 'Esta solicitud ya fue validada.');
        }

        $estadoAnterior = $solicitud->estado_id;
        $nuevoEstado = SolicitudEstado::where('clave', 'en_seguimiento')->firstOrFail();

        $solicitud->update([
            'es_informacion_publica' => 'si',
            'es_competencia' => 'si',
            'estado_id' => $nuevoEstado->id,
        ]);

        $this->historial(
            $solicitud,
            'Solicitud aceptada como información pública y de competencia de la institución. Ya puede asignarse contraseña.',
            $estadoAnterior,
            $nuevoEstado->id
        );

        return back()->with('status', 'Solicitud aceptada. Ahora puede asignar la contraseña.');
    }

    public function rechazar(Solicitud $solicitud): RedirectResponse
    {
        if ($solicitud->estado?->clave !== 'pendiente_validacion') {
            return back()->with('error', 'Esta solicitud ya fue validada.');
        }

        $estadoAnterior = $solicitud->estado_id;
        $nuevoEstado = SolicitudEstado::where('clave', 'rechazada')->firstOrFail();

        $solicitud->update([
            'es_informacion_publica' => 'no',
            'estado_id' => $nuevoEstado->id,
            'fecha_finalizacion' => now()->toDateString(),
        ]);

        $this->historial(
            $solicitud,
            'Solicitud rechazada: no constituye información pública o no es competencia de la institución.',
            $estadoAnterior,
            $nuevoEstado->id
        );

        return back()->with('status', 'Solicitud rechazada.');
    }

    public function asignarContrasena(Request $request, Solicitud $solicitud): RedirectResponse
    {
        if (! $solicitud->puedeAsignarContrasena()) {
            return back()->with('error', 'Debe aceptarse la solicitud antes de asignar contraseña.');
        }

        if ($solicitud->contrasena) {
            return back()->with('error', 'Esta solicitud ya tiene una contraseña asignada.');
        }

        $data = $request->validate([
            'contrasena' => ['required', 'string', 'max:255', 'unique:solicitudes,contrasena'],
        ], [
            'contrasena.unique' => 'Esa contraseña ya está asignada a otro expediente.',
        ]);

        $solicitud->update([
            'contrasena' => $data['contrasena'],
            'fecha_vencimiento' => $solicitud->fecha_vencimiento ?? now()->addWeekdays(10)->toDateString(),
        ]);

        $correo = $this->notificaciones->enviar($solicitud, 'solicitud_recibida', [], auth()->id());

        $this->historial(
            $solicitud,
            "Contraseña asignada: No. {$data['contrasena']}. Plazo de 10 días hábiles notificado al interesado (prórroga posible hasta el 8vo día)."
        );

        return back()->with('status', 'Contraseña guardada. '.$this->notificaciones->describirResultado($correo));
    }

    public function asignarDependencia(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $data = $request->validate([
            'dependencia_id' => ['required', 'exists:dependencias,id'],
            'enlace_id' => ['nullable', 'exists:enlaces,id'],
        ]);

        $solicitud->update($data);

        $dependencia = Dependencia::find($data['dependencia_id']);
        $enlace = $data['enlace_id'] ?? null ? Enlace::find($data['enlace_id']) : null;

        $this->historial(
            $solicitud,
            "Asignada a {$dependencia->nombre}".($enlace ? ", enlace {$enlace->nombre}." : '.')
        );

        // No hay una plantilla de correo sembrada para notificar al enlace
        // interno (las plantillas de "plantillas_correo" son todas hacia el
        // ciudadano) — enviar una aquí requeriría inventar un texto que no
        // se ha validado contra los correos reales de la UIP.
        return back()->with('status', 'Dependencia asignada.');
    }

    public function finalizar(Request $request, Solicitud $solicitud): RedirectResponse
    {
        if (! $solicitud->contrasena) {
            return back()->with('error', 'No se puede finalizar un expediente sin contraseña asignada.');
        }

        $estadoAnterior = $solicitud->estado_id;
        $nuevoEstado = SolicitudEstado::where('clave', 'finalizada')->firstOrFail();

        $solicitud->update([
            'estado_id' => $nuevoEstado->id,
            'fecha_respuesta' => now()->toDateString(),
            'fecha_finalizacion' => now()->toDateString(),
        ]);

        $correo = $this->notificaciones->enviar($solicitud, 'finalizacion', [], auth()->id());

        $this->historial(
            $solicitud,
            'Solicitud finalizada. Información entregada al interesado.',
            $estadoAnterior,
            $nuevoEstado->id
        );

        return back()->with('status', 'Expediente finalizado. '.$this->notificaciones->describirResultado($correo));
    }

    public function ajustarVencimiento(Request $request, Solicitud $solicitud): RedirectResponse
    {
        if ($solicitud->estado?->clave === 'pendiente_validacion') {
            return back()->with('error', 'Debe aceptarse la solicitud antes de fijar una fecha de vencimiento.');
        }

        $data = $request->validate([
            'fecha_vencimiento' => ['required', 'date'],
            'motivo' => ['required', 'string', 'max:2000'],
        ]);

        $anterior = $solicitud->fecha_vencimiento?->toDateString() ?? 'sin fecha asignada';

        $solicitud->update(['fecha_vencimiento' => $data['fecha_vencimiento']]);

        $this->historial(
            $solicitud,
            "Fecha de vencimiento ajustada manualmente de {$anterior} a {$data['fecha_vencimiento']}. Motivo: {$data['motivo']}"
        );

        return back()->with('status', 'Fecha de vencimiento actualizada.');
    }
}
