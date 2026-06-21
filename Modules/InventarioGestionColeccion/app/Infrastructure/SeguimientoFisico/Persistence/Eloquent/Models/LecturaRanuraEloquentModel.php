<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent que mapea cada lectura individual de una ranura dentro de un barrido del
 * ESP32: qué RFID se detectó en una ranura y si resultó incongruente. Es el detalle de
 * persistencia asociado a una sincronización del hardware.
 */
class LecturaRanuraEloquentModel extends Model
{
    protected $table = 'iot.lecturas_ranura';

    public $timestamps = false;

    protected $fillable = [
        'sincronizacion_id',
        'numero_ranura',
        'rfid_detectado',
        'es_incongruente',
    ];

    protected $casts = [
        'es_incongruente' => 'boolean',
    ];

    /** Barrido del ESP32 al que pertenece esta lectura. */
    public function sincronizacion(): BelongsTo
    {
        return $this->belongsTo(SincronizacionEsp32EloquentModel::class, 'sincronizacion_id');
    }
}
