<?php

namespace App\Services;

use App\Mail\PlantillaCorreoMail;
use App\Models\CorreoEnviado;
use App\Models\PlantillaCorreo;
use App\Models\Solicitud;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

// Envío real de correo por SMTP (Fase 11): resuelve una plantilla de
// "plantillas_correo" (con el tono/formato ya validado contra los correos
// reales de la UIP-MINGOB), sustituye sus placeholders {{nombre}},
// {{contrasena}}, {{correlativo_recurso}}, {{asunto}}, la envía y deja
// constancia en "correos_enviados" (bandeja de enviados) sin importar si
// el envío tuvo éxito o falló, para que quede auditable desde la pestaña
// "Correos" del detalle del expediente.
//
// El envío es síncrono (no usa una cola): así el resultado (enviado/fallido)
// queda disponible de inmediato para el mensaje de confirmación en el panel,
// sin depender de que el usuario tenga corriendo "php artisan queue:work".
class NotificacionService
{
    /**
     * @param  array<string, string|null>  $variables  Placeholders adicionales, además de los que ya se derivan de $solicitud (nombre, contrasena, asunto).
     */
    public function enviar(Solicitud $solicitud, string $plantillaClave, array $variables = [], ?int $enviadoPorUserId = null): ?CorreoEnviado
    {
        $plantilla = PlantillaCorreo::where('clave', $plantillaClave)->where('activa', true)->first();
        if (! $plantilla) {
            Log::warning("NotificacionService: la plantilla '{$plantillaClave}' no existe o está inactiva; no se envió correo.");

            return null;
        }

        $destinatario = $solicitud->solicitante?->correo;
        if (! $destinatario) {
            return null;
        }

        $placeholders = array_merge([
            'nombre' => $solicitud->solicitante->nombre,
            'contrasena' => $solicitud->contrasena,
            'asunto' => $solicitud->asunto,
        ], $variables);

        $asuntoCorreo = $this->reemplazarPlaceholders($plantilla->asunto_template, $placeholders);
        $cuerpoCorreo = $this->reemplazarPlaceholders($plantilla->cuerpo_template, $placeholders);

        $correo = CorreoEnviado::create([
            'solicitud_id' => $solicitud->id,
            'plantilla_id' => $plantilla->id,
            'enviado_por_user_id' => $enviadoPorUserId,
            'destinatario' => $destinatario,
            'asunto' => $asuntoCorreo,
            'cuerpo' => $cuerpoCorreo,
            'estado_entrega' => 'pendiente',
        ]);

        try {
            Mail::to($destinatario)->send(new PlantillaCorreoMail($asuntoCorreo, $cuerpoCorreo));
            $correo->update(['estado_entrega' => 'enviado', 'enviado_en' => now()]);
        } catch (Throwable $e) {
            $correo->update(['estado_entrega' => 'fallido']);
            Log::error("NotificacionService: fallo al enviar correo (solicitud #{$solicitud->id}, plantilla '{$plantillaClave}'): ".$e->getMessage());
        }

        return $correo->fresh();
    }

    /**
     * Texto legible para mostrar junto al mensaje de confirmación de una
     * acción, según el resultado real del envío (o la ausencia de correo
     * del interesado).
     */
    public function describirResultado(?CorreoEnviado $correo): string
    {
        if (! $correo) {
            return 'El interesado no tiene correo electrónico registrado; no se envió notificación automática.';
        }

        return match ($correo->estado_entrega) {
            'enviado' => 'Correo enviado al interesado.',
            'fallido' => 'No se pudo enviar el correo (revisa la configuración SMTP en .env); queda registrado como fallido en la bandeja de Correos.',
            default => 'Correo en proceso de envío.',
        };
    }

    private function reemplazarPlaceholders(string $template, array $placeholders): string
    {
        $buscar = [];
        $reemplazo = [];
        foreach ($placeholders as $clave => $valor) {
            $buscar[] = '{{'.$clave.'}}';
            $reemplazo[] = (string) ($valor ?? '');
        }

        return str_replace($buscar, $reemplazo, $template);
    }
}
