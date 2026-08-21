<?php

namespace App\Services;

use App\Mail\PlantillaCorreoMail;
use App\Models\CorreoAdjunto;
use App\Models\CorreoEnviado;
use App\Models\PlantillaCorreo;
use App\Models\Solicitud;
use App\Support\FormatoOficial;
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
     * @param  array{ruta_absoluta: string, nombre: string}|null  $adjunto  PDF de la prórroga/recurso/aclaración/resolución a adjuntar (Fase 22) — opcional, la mayoría de correos no llevan adjunto.
     */
    public function enviar(Solicitud $solicitud, string $plantillaClave, array $variables = [], ?int $enviadoPorUserId = null, ?array $adjunto = null): ?CorreoEnviado
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
            // Con coma de miles (p. ej. "1,524-2026"), igual que en los
            // .docx de Oficio/Providencia — así el asunto del correo
            // coincide con el formato oficial ("RESPUESTA SOLICITUD No.
            // {{contrasena}}") que ya usa la UIP.
            'contrasena' => FormatoOficial::conComas($solicitud->contrasena),
            'asunto' => $solicitud->asunto,
            'codigo_ns' => $solicitud->codigo_ns,
            'codigo_acceso' => $solicitud->codigo_acceso,
        ], $variables);

        $asuntoCorreo = $this->reemplazarPlaceholders($plantilla->asunto_template, $placeholders);
        $cuerpoCorreo = $this->reemplazarPlaceholders($plantilla->cuerpo_template, $placeholders);

        return $this->enviarYRegistrar($solicitud, $plantilla->id, $destinatario, $asuntoCorreo, $cuerpoCorreo, $enviadoPorUserId, $adjunto);
    }

    /**
     * Correo libre (sin plantilla), para el espacio de "Enviar correo" de la
     * pestaña Seguimiento (Fase 22) — un administrador puede escribir un
     * asunto/cuerpo ad-hoc en vez de usar una de las plantillas ya
     * validadas. Igual que enviar(), deja constancia en "correos_enviados"
     * (con plantilla_id = null) sin importar si el envío tuvo éxito o falló.
     *
     * @param  array{ruta_absoluta: string, nombre: string}|null  $adjunto
     */
    public function enviarLibre(Solicitud $solicitud, string $destinatario, string $asunto, string $cuerpo, ?int $enviadoPorUserId = null, ?array $adjunto = null): CorreoEnviado
    {
        return $this->enviarYRegistrar($solicitud, null, $destinatario, $asunto, $cuerpo, $enviadoPorUserId, $adjunto);
    }

    /**
     * @param  array{ruta_absoluta: string, nombre: string}|null  $adjunto
     */
    private function enviarYRegistrar(Solicitud $solicitud, ?int $plantillaId, string $destinatario, string $asuntoCorreo, string $cuerpoCorreo, ?int $enviadoPorUserId, ?array $adjunto): CorreoEnviado
    {
        $correo = CorreoEnviado::create([
            'solicitud_id' => $solicitud->id,
            'plantilla_id' => $plantillaId,
            'enviado_por_user_id' => $enviadoPorUserId,
            'destinatario' => $destinatario,
            'asunto' => $asuntoCorreo,
            'cuerpo' => $cuerpoCorreo,
            'estado_entrega' => 'pendiente',
        ]);

        try {
            Mail::to($destinatario)->send(new PlantillaCorreoMail(
                $asuntoCorreo,
                $cuerpoCorreo,
                $adjunto['ruta_absoluta'] ?? null,
                $adjunto['nombre'] ?? null,
            ));
            $correo->update(['estado_entrega' => 'enviado', 'enviado_en' => now()]);

            if ($adjunto) {
                CorreoAdjunto::create([
                    'correo_enviado_id' => $correo->id,
                    'nombre_archivo' => $adjunto['nombre'],
                    'ruta_archivo' => $adjunto['ruta_absoluta'],
                    'tamano_bytes' => file_exists($adjunto['ruta_absoluta']) ? filesize($adjunto['ruta_absoluta']) : null,
                ]);
            }
        } catch (Throwable $e) {
            $correo->update(['estado_entrega' => 'fallido']);
            Log::error("NotificacionService: fallo al enviar correo (solicitud #{$solicitud->id}): ".$e->getMessage());
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
