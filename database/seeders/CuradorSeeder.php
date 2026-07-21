<?php

namespace Database\Seeders;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Database\Seeder;

class CuradorSeeder extends Seeder
{
    public function run(): void
    {
        $curador = User::firstOrCreate(
            ['email' => env('CURADOR_EMAIL', 'curador@epn.edu.ec')],
            [
                'first_name' => 'Curador',
                'last_name' => 'Hub Digital',
                'password' => env('CURADOR_PASSWORD', 'secret'),
                'rol' => RolUsuario::CURADOR,
                // El curador es una cuenta administrativa con correo institucional
                // ficticio (sin buzón real): se siembra ya verificada para no
                // quedar bloqueada por el muro de verificación de email.
                'email_verified_at' => now(),
            ]
        );

        // Registra la membresía del rol en el pivote (idempotente).
        $curador->asignarRol(RolUsuario::CURADOR);
    }
}
