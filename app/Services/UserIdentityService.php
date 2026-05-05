<?php

namespace App\Services;

use App\Enums\RolUsuario;
use App\Models\User;

final class UserIdentityService
{
    public function existeUsuario(string $uuid): bool
    {
        return User::where('id', $uuid)->exists();
    }

    public function obtenerRol(string $uuid): ?RolUsuario
    {
        $rolString = User::where('id', $uuid)->value('rol');

        return $rolString ? RolUsuario::tryFrom($rolString) : null;
    }

    public function esPrestamista(string $uuid): bool
    {
        return $this->obtenerRol($uuid) === RolUsuario::PRESTAMISTA;
    }

    public function esDepositante(string $uuid): bool
    {
        return $this->obtenerRol($uuid) === RolUsuario::DEPOSITANTE;
    }

    public function esCurador(string $uuid): bool
    {
        return $this->obtenerRol($uuid) === RolUsuario::CURADOR;
    }
}
