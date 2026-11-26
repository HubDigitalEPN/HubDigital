<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RolUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @property string $codigo
 * @property string $nombre
 * @property-read string $value
 */
final class PerfilPersonalizado extends Model
{
    protected $table = 'usuarios.perfiles_personalizados';

    protected $primaryKey = 'codigo';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['codigo', 'nombre'];

    public function getValueAttribute(): string
    {
        return $this->codigo;
    }

    public function etiqueta(): string
    {
        return $this->nombre;
    }

    /** @return Collection<string, RolUsuario|self> */
    public static function perfiles(): Collection
    {
        return collect([...RolUsuario::cases(), ...self::query()->orderBy('nombre')->get()])->keyBy('value');
    }

    public static function resolver(string $codigo): RolUsuario|self|null
    {
        return RolUsuario::desdeHistorico($codigo)
            ?? (preg_match('/^PERFIL_[0-9A-Z]{26}$/D', strtoupper($codigo)) ? self::find(strtoupper($codigo)) : null);
    }
}
