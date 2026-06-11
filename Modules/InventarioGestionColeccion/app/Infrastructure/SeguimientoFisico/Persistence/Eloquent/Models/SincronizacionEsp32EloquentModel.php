<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Eloquent que mapea cada barrido completo del ESP32 sobre un gabinete: su estado,
 * el momento en que se realizó y cuántas incongruencias arrojó. Agrupa las lecturas de
 * ranura de ese ciclo. Es el puente de persistencia de la entidad del dominio.
 */
class SincronizacionEsp32EloquentModel extends Model
{
    protected $table = 'iot.sincronizaciones_esp32';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'gabinete_id',
        'estado',
        'realizada_en',
        'total_incongruencias',
    ];

    /** Gabinete sobre el que se ejecutó el barrido. */
    public function gabinete(): BelongsTo
    {
        return $this->belongsTo(GabineteEloquentModel::class, 'gabinete_id');
    }

    /** Lecturas de ranura individuales que conforman el barrido. */
    public function lecturas(): HasMany
    {
        return $this->hasMany(LecturaRanuraEloquentModel::class, 'sincronizacion_id');
    }
}
