<?php

namespace Database\Factories;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'rol' => RolUsuario::CURADOR->value,
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Sincroniza el pivote de membresía con el rol primario (columna `rol`),
     * de modo que tieneRol()/rolActivo() funcionen en toda la suite existente.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if ($user->rol !== null) {
                $user->roles()->firstOrCreate(['rol' => $user->rol->value]);
            }
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function curador(): static
    {
        return $this->state(fn (array $attributes) => [
            'rol' => RolUsuario::CURADOR->value,
        ]);
    }

    public function prestamista(): static
    {
        return $this->state(fn (array $attributes) => [
            'rol' => RolUsuario::PRESTAMISTA->value,
        ]);
    }

    public function depositante(): static
    {
        return $this->state(fn (array $attributes) => [
            'rol' => RolUsuario::DEPOSITANTE->value,
            'cargo' => fake()->jobTitle(),
            'institucion' => fake()->company(),
        ]);
    }

    /**
     * Cuenta con varios roles asignados. El primero es el rol primario/activo.
     */
    public function conRoles(RolUsuario ...$roles): static
    {
        $primario = $roles[0] ?? RolUsuario::PRESTAMISTA;

        return $this->state(fn (array $attributes) => [
            'rol' => $primario->value,
        ])->afterCreating(function (User $user) use ($roles) {
            foreach ($roles as $rol) {
                $user->roles()->firstOrCreate(['rol' => $rol->value]);
            }
        });
    }
}
