<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RanuraGabineteEloquentModel extends Model
{
    protected $table = 'iot.ranuras_gabinete';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'gabinete_id',
        'numero_ranura',
        'familia_taxonomica_esperada_id',
        'caja_actual_id',
        'activa',
    ];

    public function gabinete(): BelongsTo
    {
        return $this->belongsTo(GabineteEloquentModel::class, 'gabinete_id');
    }

    public function cajaActual(): BelongsTo
    {
        return $this->belongsTo(CajaEloquentModel::class, 'caja_actual_id');
    }
}
