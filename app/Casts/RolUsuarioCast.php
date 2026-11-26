<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\RolUsuario;
use App\Models\PerfilPersonalizado;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/** Conserva los identificadores de los roles del sistema y valida los perfiles configurados. */
final class RolUsuarioCast implements CastsAttributes, SerializesCastableAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): RolUsuario|PerfilPersonalizado|null
    {
        return $value === null ? null : (PerfilPersonalizado::resolver((string) $value) ?? throw new \ValueError('Rol de usuario desconocido.'));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) return null;
        $perfil = $value instanceof RolUsuario || $value instanceof PerfilPersonalizado
            ? $value
            : PerfilPersonalizado::resolver((string) $value);

        return ($perfil ?? throw new \ValueError('Rol de usuario desconocido.'))->value;
    }

    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value?->value;
    }
}
