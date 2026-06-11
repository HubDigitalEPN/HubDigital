<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent que mapea cada estadía de una caja en una ranura (cuándo ingresó y cuándo
 * se retiró), formando el historial de movimientos que permite localizar cajas y calcular
 * tiempos de extracción. Es el puente de persistencia de la entidad UbicacionCaja del dominio.
 */
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

    /** Caja cuya estadía registra esta ubicación. */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(CajaEloquentModel::class, 'caja_id');
    }

    /** Ranura en la que estuvo (o está) la caja durante esta estadía. */
    public function ranura(): BelongsTo
    {
        return $this->belongsTo(RanuraGabineteEloquentModel::class, 'ranura_gabinete_id');
    }
}
