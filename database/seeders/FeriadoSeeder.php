<?php

namespace Database\Seeders;

use App\Models\Feriado;
use Illuminate\Database\Seeder;

// Dias no habiles 2026 en Guatemala, usados para calcular los plazos de
// 10 dias habiles (solicitud) y 2 dias habiles (aclaracion).
// Jueves y Viernes Santo se calculan a partir del Domingo de Pascua
// (5 de abril de 2026, algoritmo de Gauss) - verificar cada anio.
class FeriadoSeeder extends Seeder
{
    public function run(): void
    {
        $feriados = [
            ['fecha' => '2026-01-01', 'descripcion' => 'Año Nuevo'],
            ['fecha' => '2026-04-02', 'descripcion' => 'Jueves Santo'],
            ['fecha' => '2026-04-03', 'descripcion' => 'Viernes Santo'],
            ['fecha' => '2026-04-04', 'descripcion' => 'Sábado Santo (asueto)'],
            ['fecha' => '2026-05-01', 'descripcion' => 'Día del Trabajo'],
            ['fecha' => '2026-06-30', 'descripcion' => 'Día del Ejército'],
            ['fecha' => '2026-08-15', 'descripcion' => 'Asunción (solo Guatemala capital)'],
            ['fecha' => '2026-09-15', 'descripcion' => 'Día de la Independencia'],
            ['fecha' => '2026-10-20', 'descripcion' => 'Día de la Revolución'],
            ['fecha' => '2026-11-01', 'descripcion' => 'Día de Todos los Santos'],
            ['fecha' => '2026-12-24', 'descripcion' => 'Nochebuena (medio día, asueto)'],
            ['fecha' => '2026-12-25', 'descripcion' => 'Navidad'],
            ['fecha' => '2026-12-31', 'descripcion' => 'Fin de Año (medio día, asueto)'],
        ];

        foreach ($feriados as $f) {
            Feriado::updateOrCreate(['fecha' => $f['fecha']], $f);
        }
    }
}
