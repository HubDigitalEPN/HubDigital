<?php

namespace Database\Seeders;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Database\Seeder;

class CuradorSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => env('CURADOR_EMAIL', 'curador@epn.edu.ec')],
            [
                'name' => 'Curador Hub Digital',
                'password' => env('CURADOR_PASSWORD', 'secret'),
                'rol' => RolUsuario::CURADOR,
            ]
        );
    }
}
