<?php

namespace Database\Seeders;

use App\Models\Dependencia;
use App\Models\Enlace;
use Illuminate\Database\Seeder;

class DependenciaSeeder extends Seeder
{
    public function run(): void
    {
        // Nombres consistentes con los expedientes de ejemplo (SolicitudDemoSeeder)
        // y con lo que el usuario ya vio y validó en el prototipo HTML.
        $dependencias = [
            ['codigo' => 'PLANIF', 'nombre' => 'Dirección de Planificación', 'enlace' => 'Lic. Marta Solís'],
            ['codigo' => 'FIN', 'nombre' => 'Dirección de Finanzas', 'enlace' => 'Ing. Pedro Ramírez'],
            ['codigo' => 'JURID', 'nombre' => 'Dirección de Asuntos Jurídicos', 'enlace' => 'Licda. Rosa Ixchop'],
            ['codigo' => 'RRHH', 'nombre' => 'Dirección de Recursos Humanos', 'enlace' => 'Lic. Jorge Tzul'],
            ['codigo' => 'ADMIN', 'nombre' => 'Dirección Administrativa', 'enlace' => 'Ing. Sofía Reyes'],
        ];

        foreach ($dependencias as $d) {
            $dep = Dependencia::updateOrCreate(['codigo' => $d['codigo']], [
                'nombre' => $d['nombre'],
                'activa' => true,
            ]);

            Enlace::updateOrCreate(
                ['dependencia_id' => $dep->id, 'nombre' => $d['enlace']],
                [
                    'correo' => strtolower(str_replace(' ', '.', $d['codigo'])) . '.enlace@mingob.gob.gt',
                    'telefono' => null,
                    'activo' => true,
                ]
            );
        }
    }
}
