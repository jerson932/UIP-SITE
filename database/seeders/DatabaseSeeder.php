<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Orden de siembra: catalogos primero (roles, permisos, estados,
     * dependencias, plantillas, feriados, configuracion), luego usuarios
     * de prueba, y al final los expedientes de demo que dependen de todo
     * lo anterior.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            SolicitudEstadoSeeder::class,
            DependenciaSeeder::class,
            PlantillaCorreoSeeder::class,
            FeriadoSeeder::class,
            ConfiguracionSeeder::class,
            UserSeeder::class,
            SolicitudDemoSeeder::class,
        ]);
    }
}
