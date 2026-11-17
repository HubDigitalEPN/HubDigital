<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\RolUsuario;
use App\Models\User;
use App\Services\Security\TurnstileVerifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        app(TurnstileVerifier::class)->validateRegistration(request());

        if (($input['rol'] ?? '') === 'PRESTAMISTA') $input['rol'] = RolUsuario::DEPOSITANTE->value;
        $input['email'] = User::normalizarEmail($input['email'] ?? '');
        $input['first_name'] = trim($input['first_name'] ?? '');
        $input['last_name'] = trim($input['last_name'] ?? '');

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'rol' => [
                'required',
                Rule::in([
                    RolUsuario::PRESTAMISTA->value,
                    RolUsuario::DEPOSITANTE->value,
                ]),
            ],
            'cargo' => ['nullable', 'required_if:rol,'.RolUsuario::DEPOSITANTE->value, 'string', 'max:255'],
            'institucion' => ['nullable', 'required_if:rol,'.RolUsuario::DEPOSITANTE->value, 'string', 'max:255'],
        ])->validate();

        $rol = RolUsuario::from($input['rol']);

        try {
            return DB::transaction(function () use ($input, $rol): User {
                $user = User::create([
                    'first_name' => $input['first_name'],
                    'last_name' => $input['last_name'],
                    'email' => $input['email'],
                    'password' => $input['password'],
                    'rol' => $rol,
                    'cargo' => $rol === RolUsuario::DEPOSITANTE ? trim($input['cargo']) : null,
                    'institucion' => $rol === RolUsuario::DEPOSITANTE ? trim($input['institucion']) : null,
                ]);

                $user->asignarRol($user->rol);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // Mismo resultado para la validación previa y para una carrera entre
            // dos solicitudes simultáneas; nunca se crea una cuenta duplicada.
            throw ValidationException::withMessages([
                'email' => 'No fue posible registrar este correo. Inicia sesión o recupera tu contraseña.',
            ]);
        }
    }
}
