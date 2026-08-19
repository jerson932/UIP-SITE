<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use Illuminate\Database\Seeder;

// Parametros generales, sobre todo los plazos legales reales confirmados
// por el usuario a partir de los correos reales de la UIP.
class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        $config = [
            'plazo_solicitud_dias_habiles' => ['valor' => '10', 'descripcion' => 'Plazo para resolver una solicitud, en días hábiles'],
            'plazo_prorroga_notificar_dia' => ['valor' => '8', 'descripcion' => 'Día hábil límite para notificar prórroga'],
            'plazo_aclaracion_dias_habiles' => ['valor' => '2', 'descripcion' => 'Plazo del ciudadano para responder una aclaración, en días hábiles'],
            'ampliacion_permite_post_resolucion' => ['valor' => 'false', 'descripcion' => 'La Ley de Acceso a la Información Pública no regula ampliaciones después de la resolución'],
            'nombre_institucion' => ['valor' => 'Ministerio de Gobernación (MINGOB)', 'descripcion' => 'Nombre de la institución'],
            'correo_uip' => ['valor' => 'uip@mingob.gob.gt', 'descripcion' => 'Correo institucional de la UIP'],
        ];

        foreach ($config as $clave => $c) {
            Configuracion::updateOrCreate(['clave' => $clave], $c);
        }
    }
}
