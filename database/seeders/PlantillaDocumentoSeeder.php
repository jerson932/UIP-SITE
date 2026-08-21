<?php

namespace Database\Seeders;

use App\Models\PlantillaDocumento;
use Illuminate\Database\Seeder;

// Las 10 plantillas .docx reales de Oficio/Providencia que la UIP ya usa
// (archivos en resources/plantillas_oficiales/{clave}.docx — Fase 19,
// generación de Oficio/Providencia). "contenido" aquí es solo texto
// descriptivo para el panel: el documento real es el .docx que trae el
// logo/membrete y se procesa con PhpOffice\PhpWord\TemplateProcessor
// (DocumentoOficialService), no un textarea editable como las plantillas
// de correo.
class PlantillaDocumentoSeeder extends Seeder
{
    public function run(): void
    {
        $placeholdersOficio = '${titulo_numero} ${rc} ${no_solicitud} ${folio} ${fecha_genera} ${interesado} ${descripcion} ${dependencia}';
        $placeholdersProvidencia = '${no_solicitud} ${rc} ${folio} ${interesado} ${descripcion} ${no_providencia} ${fecha_genera} ${dependencia}';
        $placeholdersProvidenciaSinRc = '${no_solicitud} ${folio} ${interesado} ${descripcion} ${no_providencia} ${fecha_genera} ${dependencia}';

        $plantillas = [
            [
                'clave' => 'oficio_despacho',
                'nombre' => 'Oficio — Despacho Superior (Ministro)',
                'contenido' => "Oficio dirigido al Ministro de Gobernación (Despacho Superior). Placeholders: {$placeholdersOficio}",
            ],
            [
                'clave' => 'oficio_primer_vice',
                'nombre' => 'Oficio — Primer Viceministerio',
                'contenido' => "Oficio dirigido al Primer Viceministro. Placeholders: {$placeholdersOficio}",
            ],
            [
                'clave' => 'oficio_segundo_vice',
                'nombre' => 'Oficio — Segundo Viceministerio',
                'contenido' => "Oficio dirigido al Segundo Viceministro. Placeholders: {$placeholdersOficio}",
            ],
            [
                'clave' => 'oficio_tercer_vice',
                'nombre' => 'Oficio — Tercer Viceministerio de Gobernación',
                'contenido' => "Oficio dirigido a la Tercera Viceministra. Placeholders: {$placeholdersOficio}",
            ],
            [
                'clave' => 'oficio_cuarto_vice',
                'nombre' => 'Oficio — Cuarto Viceministerio',
                'contenido' => "Oficio dirigido al Cuarto Viceministro. Placeholders: {$placeholdersOficio}",
            ],
            [
                'clave' => 'oficio_quinto_vice',
                'nombre' => 'Oficio — Quinto Viceministerio',
                'contenido' => "Oficio dirigido al Quinto Viceministerio. Placeholders: {$placeholdersOficio}",
            ],
            [
                'clave' => 'providencia_generica',
                'nombre' => 'Providencia — Traslado genérico a dependencia',
                'contenido' => "Providencia genérica de traslado a cualquier dependencia sin plantilla especial (incluye \"Otro\"). Placeholders: {$placeholdersProvidencia}",
            ],
            [
                'clave' => 'providencia_digessp',
                'nombre' => 'Providencia — DIGESSP (Servicios de Seguridad Privada)',
                'contenido' => "Providencia de traslado a la Dirección General de Servicios de Seguridad Privada. Placeholders: {$placeholdersProvidenciaSinRc}",
            ],
            [
                'clave' => 'providencia_pnc',
                'nombre' => 'Providencia — PNC (Policía Nacional Civil)',
                'contenido' => "Providencia de traslado a la Dirección General de la Policía Nacional Civil (con copia al Enlace de Información Pública de la PNC). Placeholders: {$placeholdersProvidenciaSinRc}",
            ],
            [
                'clave' => 'providencia_repeju',
                'nombre' => 'Providencia — REPEJU (Registro de Personas Jurídicas)',
                'contenido' => "Providencia de traslado al Registro de Personas Jurídicas. Placeholders: {$placeholdersProvidenciaSinRc}",
            ],
        ];

        foreach ($plantillas as $p) {
            PlantillaDocumento::updateOrCreate(['clave' => $p['clave']], [
                'nombre' => $p['nombre'],
                'tipo' => 'docx',
                'contenido' => $p['contenido'],
                'visible_ciudadano_default' => false,
                'activa' => true,
            ]);
        }
    }
}
