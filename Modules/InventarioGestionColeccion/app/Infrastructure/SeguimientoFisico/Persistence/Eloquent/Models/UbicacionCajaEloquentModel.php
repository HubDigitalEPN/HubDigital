<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UbicacionCajaEloquentModel extends Model
{
    protected $table = 'iot.ubicaciones_caja';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'caja_id',
        'ranura_gabinete_id',
        'ingresada_en',
        'retirada_en',
    ];

    public function caja(): BelongsTo
    {
        return $this->belongsTo(CajaEloquentModel::class, 'caja_id');
    }

    public function ranura(): BelongsTo
    {
        return $this->belongsTo(RanuraGabineteEloquentModel::class, 'ranura_gabinete_id');
    }
}
