<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

// Roles segun spec tabla 10 ("Roles y alcance")
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['nombre' => 'Administrador', 'descripcion' => 'Acceso total al sistema'],
            ['nombre' => 'Recepción UIP', 'descripcion' => 'Recepción y registro de solicitudes'],
            ['nombre' => 'Usuario UIP', 'descripcion' => 'Gestión de expedientes autorizados'],
            ['nombre' => 'Coordinador', 'descripcion' => 'Supervisión y aprobaciones'],
            ['nombre' => 'Enlace', 'descripcion' => 'Solicitudes asignadas a su dependencia'],
            ['nombre' => 'Consulta', 'descripcion' => 'Acceso limitado, solo lectura'],
        ];

        foreach ($roles as $r) {
            Rol::updateOrCreate(['nombre' => $r['nombre']], $r);
        }
    }
}
