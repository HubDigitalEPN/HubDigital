<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocalidadEloquentModel extends Model
{
    protected $table = 'taxonomia.localidades';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nombre_canonico',
        'rango',
        'padre_id',
        'decimal_latitude',
        'decimal_longitude',
        'geodetic_datum',
        'coordinate_uncertainty_m',
        'lat_lon_verbatim',
        'country',
        'state_province',
        'municipality',
    ];

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'padre_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'padre_id');
    }
}
