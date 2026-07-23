<?php

namespace Database\Seeders;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Database\Seeder;

class CuradorSeeder extends Seeder
{
    public function run(): void
    {
        // Se acepta CURADOR_PASSWORD además de CURADOR_PASS por compatibilidad con
        // entornos anteriores. Sin contraseña configurada se aborta en vez de sembrar
        // una por defecto: es la cuenta administrativa de la colección y un valor
        // conocido como 'secret' equivaldría a dejarla abierta.
        $password = env('CURADOR_PASS') ?? env('CURADOR_PASSWORD');

        if (blank($password)) {
            throw new \RuntimeException(
                'Define CURADOR_PASS en el archivo .env antes de sembrar la cuenta de curaduría.'
            );
        }

        $curador = User::firstOrCreate(
            ['email' => env('CURADOR_EMAIL', 'curador@epn.edu.ec')],
            [
                'first_name' => 'Curador',
                'last_name' => 'Hub Digital',
                'password' => $password,
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
