<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\RolUsuario;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** Lee membresías históricas y escribe siempre uno de los cinco roles vigentes. */
final class RolUsuarioCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?RolUsuario
    {
        return $value === null ? null : (RolUsuario::desdeHistorico((string) $value) ?? throw new \ValueError('Rol de usuario desconocido.'));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) return null;
        return ($value instanceof RolUsuario ? $value : (RolUsuario::desdeHistorico((string) $value) ?? throw new \ValueError('Rol de usuario desconocido.')))->value;
    }
}
