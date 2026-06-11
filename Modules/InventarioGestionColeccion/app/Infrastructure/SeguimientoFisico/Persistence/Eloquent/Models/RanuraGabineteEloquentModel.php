<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent que mapea cada ranura de un gabinete (la posición física con un lector
 * RC522), su número y la caja que aloja actualmente. Es el puente de persistencia de la
 * entidad RanuraGabinete del dominio.
 */
class RanuraGabineteEloquentModel extends Model
{
    protected $table = 'iot.ranuras_gabinete';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'gabinete_id',
        'numero_ranura',
        'caja_actual_id',
        'activa',
    ];

    protected $casts = [
        'numero_ranura' => 'integer',
        'activa' => 'boolean',
    ];

    /** Gabinete al que pertenece la ranura. */
    public function gabinete(): BelongsTo
    {
        return $this->belongsTo(GabineteEloquentModel::class, 'gabinete_id');
    }

    /** Caja alojada actualmente en la ranura (null si está vacía). */
    public function cajaActual(): BelongsTo
    {
        return $this->belongsTo(CajaEloquentModel::class, 'caja_actual_id');
    }
}
