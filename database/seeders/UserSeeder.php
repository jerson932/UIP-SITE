<?php

namespace Database\Seeders;

use App\Models\Dependencia;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

// Usuarios de prueba para Fase 3 (autenticacion/roles). La contraseña es
// solo para desarrollo local, cambiarla antes de cualquier despliegue real.
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Rol::where('nombre', 'Administrador')->first();
        $usuarioUip = Rol::where('nombre', 'Usuario UIP')->first();
        $enlaceRol = Rol::where('nombre', 'Enlace')->first();
        $planif = Dependencia::where('codigo', 'PLANIF')->first();

        User::updateOrCreate(
            ['email' => 'jersonmelendez123@gmail.com'],
            [
                'name' => 'Jerson Melendez',
                'password' => Hash::make('password'),
                'role_id' => $admin?->id,
                'activo' => true,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'uip.demo@mingob.gob.gt'],
            [
                'name' => 'Usuario UIP (demo)',
                'password' => Hash::make('password'),
                'role_id' => $usuarioUip?->id,
                'activo' => true,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'enlace.planificacion@mingob.gob.gt'],
            [
                'name' => 'Enlace Planificación (demo)',
                'password' => Hash::make('password'),
                'role_id' => $enlaceRol?->id,
                'dependencia_id' => $planif?->id,
                'activo' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
