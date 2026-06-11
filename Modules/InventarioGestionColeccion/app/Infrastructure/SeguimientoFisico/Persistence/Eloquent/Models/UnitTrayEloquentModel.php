<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent que mapea cada unit tray (bandeja interna de una caja) con su número y la
 * clasificación taxonómica dominante de los especímenes que agrupa. Es el puente de
 * persistencia de la entidad UnitTray del dominio.
 */
class UnitTrayEloquentModel extends Model
{
    protected $table = 'iot.unit_trays';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'caja_id',
        'numero',
        'clasificacion_dominante',
    ];

    protected $casts = [
        'numero' => 'integer',
        'clasificacion_dominante' => 'array',
    ];

    /** Caja que contiene este unit tray. */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(CajaEloquentModel::class, 'caja_id');
    }
}
