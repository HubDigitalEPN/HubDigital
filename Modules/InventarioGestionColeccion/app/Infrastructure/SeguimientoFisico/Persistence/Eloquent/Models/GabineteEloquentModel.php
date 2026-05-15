<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GabineteEloquentModel extends Model
{
    protected $table = 'iot.gabinetes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'codigo',
        'nombre',
        'total_ranuras',
        'activo',
    ];

    public function ranuras(): HasMany
    {
        return $this->hasMany(RanuraGabineteEloquentModel::class, 'gabinete_id');
    }

    public function sincronizaciones(): HasMany
    {
        return $this->hasMany(SincronizacionEsp32EloquentModel::class, 'gabinete_id');
    }
}
