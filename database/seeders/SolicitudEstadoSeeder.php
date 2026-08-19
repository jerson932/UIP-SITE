<?php

namespace Database\Seeders;

use App\Models\SolicitudEstado;
use Illuminate\Database\Seeder;

// Catalogo de estados (spec seccion 25 "Estados y automatizacion de interfaz"
// + seccion 26 "Experiencia del ciudadano"), tal como quedo validado en el
// prototipo HTML.
class SolicitudEstadoSeeder extends Seeder
{
    public function run(): void
    {
        $estados = [
            ['clave' => 'pendiente_validacion', 'etiqueta' => 'Pendiente de validación', 'color' => '#eda100', 'orden' => 1, 'es_final' => false],
            ['clave' => 'en_revision', 'etiqueta' => 'En revisión', 'color' => '#2a78d6', 'orden' => 2, 'es_final' => false],
            ['clave' => 'en_seguimiento', 'etiqueta' => 'En seguimiento', 'color' => '#1baf7a', 'orden' => 3, 'es_final' => false],
            ['clave' => 'aclaracion_solicitada', 'etiqueta' => 'Se requiere aclaración', 'color' => '#fab219', 'orden' => 4, 'es_final' => false],
            ['clave' => 'prorroga', 'etiqueta' => 'Prórroga registrada', 'color' => '#4a3aa7', 'orden' => 5, 'es_final' => false],
            ['clave' => 'recurso_revision', 'etiqueta' => 'Recurso de revisión', 'color' => '#e87ba4', 'orden' => 6, 'es_final' => false],
            ['clave' => 'respuesta_disponible', 'etiqueta' => 'Respuesta disponible', 'color' => '#0ca30c', 'orden' => 7, 'es_final' => false],
            ['clave' => 'finalizada', 'etiqueta' => 'Finalizada', 'color' => '#52514e', 'orden' => 8, 'es_final' => true],
            ['clave' => 'rechazada', 'etiqueta' => 'Rechazada / no procede', 'color' => '#d03b3b', 'orden' => 9, 'es_final' => true],
        ];

        foreach ($estados as $e) {
            SolicitudEstado::updateOrCreate(['clave' => $e['clave']], $e);
        }
    }
}
