<?php

namespace App\Services;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Collection;

final class UserIdentityService
{
    public function existeUsuario(string $uuid): bool
    {
        return User::where('id', $uuid)->exists();
    }

    /**
     * Roles asignados a la cuenta (membresía).
     *
     * @return Collection<int, RolUsuario>
     */
    public function obtenerRoles(string $uuid): Collection
    {
        return User::find($uuid)?->rolesAsignados() ?? collect();
    }

    public function tieneRol(string $uuid, RolUsuario $rol): bool
    {
        return User::find($uuid)?->tieneRol($rol) ?? false;
    }

    /**
     * Rol primario de la cuenta (columna escalar). No depende de la sesión.
     */
    public function obtenerRolPrimario(string $uuid): ?RolUsuario
    {
        return User::find($uuid)?->rol;
    }

    /**
     * @deprecated Ambiguo con multi-rol; usar obtenerRolPrimario() o tieneRol().
     */
    public function obtenerRol(string $uuid): ?RolUsuario
    {
        return $this->obtenerRolPrimario($uuid);
    }

    public function esPrestamista(string $uuid): bool
    {
        return $this->tieneRol($uuid, RolUsuario::PRESTAMISTA);
    }

    public function esDepositante(string $uuid): bool
    {
        return $this->tieneRol($uuid, RolUsuario::DEPOSITANTE);
    }

    public function esCurador(string $uuid): bool
    {
        return $this->tieneRol($uuid, RolUsuario::CURADOR);
    }
}
