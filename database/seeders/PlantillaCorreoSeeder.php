<?php

namespace Database\Seeders;

use App\Models\PlantillaCorreo;
use Illuminate\Database\Seeder;

// Plantillas con el tono/formato REAL de la UIP-MINGOB, tal como se
// verificaron contra los correos reales que el usuario compartió
// (bandeja de Gmail de la UIP) y ya quedaron validadas en el prototipo HTML.
// Placeholders: {{nombre}}, {{contrasena}}, {{correlativo_recurso}}, {{asunto}}
class PlantillaCorreoSeeder extends Seeder
{
    private const FOOTER = "Unidad de Información Pública\n6a. Avenida 13-71 Zona 1, Primer Nivel\nPBX: 2413-8888 Extensión: 1621 / 1541\nCorreo electrónico: uip@mingob.gob.gt";

    public function run(): void
    {
        $plantillas = [
            [
                'clave' => 'solicitud_recibida',
                'evento' => 'Nueva solicitud / notificación de recepción y Contraseña asignada',
                'asunto_template' => 'Notificación de recepción - Contraseña No. {{contrasena}}',
                'cuerpo_template' => "Estimado (a) Usuario (a):\n\nAtentamente, acusamos recepción de su solicitud de información y en atención a ello, se le ha asignado la CONTRASEÑA No. {{contrasena}}, asimismo; manifestarle que esta Unidad, cuenta con un plazo de diez (10) días hábiles, para emitir la resolución de respuesta a su solicitud de información o bien para solicitarle prórroga dentro del octavo (8) día para entrega de la misma.\n\nSin otro particular, nos suscribimos de usted\n\n" . self::FOOTER,
            ],
            [
                'clave' => 'aclaracion_solicitada',
                'evento' => 'UIP solicita aclaración (plazo real: 2 días hábiles)',
                'asunto_template' => 'Solicitud de aclaración - Contraseña No. {{contrasena}}',
                'cuerpo_template' => "Estimado (a) Usuario (a):\n\nCon un atento saludo nos dirigimos a usted y de conformidad con la aclaración requerida por la Dependencia responsable del trámite de su solicitud, se fija un plazo de DOS DÍAS HÁBILES, para su pronunciamiento, con la observación que de no contar con el mismo al vencimiento del plazo, se emitirá la resolución que corresponda. Sin que esto implique, que se pueda presentar nuevamente la solicitud de información y requerir con claridad la información que es de su interés.\n\nSin otro particular, nos suscribimos de usted,\n\nAtentamente,\n\n" . self::FOOTER,
            ],
            [
                'clave' => 'aclaracion_recibida',
                'evento' => 'Ciudadano responde la aclaración',
                'asunto_template' => 'Respuesta de aclaración recibida - Contraseña No. {{contrasena}}',
                'cuerpo_template' => "Estimado (a) Usuario (a):\n\nSe ha registrado su respuesta de aclaración correspondiente a la Contraseña No. {{contrasena}}. Continuaremos con el trámite de su solicitud.\n\nSin otro particular, nos suscribimos de usted,\n\n" . self::FOOTER,
            ],
            [
                'clave' => 'prorroga',
                'evento' => 'Se registra prórroga (a más tardar el 8vo día hábil)',
                'asunto_template' => 'Notificación de Prórroga - Contraseña No. {{contrasena}}',
                'cuerpo_template' => "Señor(a)\n{{nombre}}\nPresente\n\nCon un atento saludo nos dirigimos a usted, con el objeto de remitirle resolución que contiene Solicitud de Prórroga, en virtud que esta Unidad, aún no cuenta con la información solicitada y al momento de disponer de la misma, de forma inmediata se le estará haciendo llegar por esta misma vía mediante resolución correspondiente, mucho apreciaríamos se sirva acusar recepción de la presente notificación.\n\n" . self::FOOTER,
            ],
            [
                'clave' => 'ampliacion_no_procedente',
                'evento' => 'Ciudadano solicita ampliación después de la resolución (no regulada por la ley)',
                'asunto_template' => 'Respuesta a solicitud de ampliación - Contraseña No. {{contrasena}}',
                'cuerpo_template' => "Estimado (a) señor(a) {{nombre}}:\n\nCon un atento saludo nos dirigimos a usted, con el objeto de manifestarle que la Ley de Acceso a la Información Pública no contempla ni regula lo relacionado a ampliaciones una vez ha sido emitida la resolución de respuesta correspondiente, por lo que agradecemos remitir una nueva solicitud de información.\n\nSaludos cordiales,\n\n" . self::FOOTER,
            ],
            [
                'clave' => 'recurso_recibido',
                'evento' => 'Ciudadano presenta Recurso de Revisión (correlativo propio)',
                'asunto_template' => 'Acuse de recibo - Recurso de Revisión No. {{correlativo_recurso}}',
                'cuerpo_template' => "Estimado (a) Usuario (a):\n\nCon un atento saludo nos dirigimos a usted, con el objeto de acusar recepción del Recurso de Revisión interpuesto por su persona y de conformidad con lo dispuesto en la Ley de Acceso a la Información Pública y el Manual de Procedimientos, se ha registrado como Recurso de Revisión No. {{correlativo_recurso}}, por lo que, de forma inmediata, se están acompañando los antecedentes y remitiendo para el trámite correspondiente.\n\nSin otro particular, nos suscribimos de usted,\n\nAtentamente,\n\n" . self::FOOTER,
            ],
            [
                'clave' => 'documentos_disponibles',
                'evento' => 'Se publica documentación en el panel ciudadano',
                'asunto_template' => 'Documentos disponibles - Contraseña No. {{contrasena}}',
                'cuerpo_template' => "Estimado (a) Usuario (a):\n\nLe informamos que hay documentación disponible para su consulta en el panel de seguimiento, correspondiente a la Contraseña No. {{contrasena}}.\n\nSin otro particular, nos suscribimos de usted,\n\n" . self::FOOTER,
            ],
            [
                'clave' => 'resolucion_respuesta',
                'evento' => 'Se notifica la resolución/respuesta final',
                'asunto_template' => 'Notificación de respuesta - Contraseña No. {{contrasena}}',
                'cuerpo_template' => "Señor(a)\n{{nombre}}\nPresente\n\nCon un atento saludo nos dirigimos a usted, con el objeto de remitirle resolución que contiene respuesta a su solicitud de información, mucho apreciaríamos, se sirva acusar recepción de la presente notificación.\n\nSin otro particular, nos suscribimos de usted,\n\nAtentamente,\n\n" . self::FOOTER,
            ],
            [
                'clave' => 'finalizacion',
                'evento' => 'Expediente finalizado',
                'asunto_template' => 'Expediente finalizado - Contraseña No. {{contrasena}}',
                'cuerpo_template' => "Estimado (a) Usuario (a):\n\nLe informamos que el expediente correspondiente a la Contraseña No. {{contrasena}} ha sido finalizado.\n\nSin otro particular, nos suscribimos de usted,\n\n" . self::FOOTER,
            ],
        ];

        foreach ($plantillas as $p) {
            PlantillaCorreo::updateOrCreate(['clave' => $p['clave']], $p + ['activa' => true]);
        }
    }
}
