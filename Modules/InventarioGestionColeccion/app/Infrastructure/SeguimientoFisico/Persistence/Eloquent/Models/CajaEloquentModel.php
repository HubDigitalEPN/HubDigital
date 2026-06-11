<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Eloquent que mapea la tabla de cajas entomológicas rastreadas por el componente,
 * incluyendo su código RFID, clasificación taxonómica asignada y la ranura que ocupa
 * actualmente. Es solo el puente de persistencia de la entidad Caja del dominio.
 */
class CajaEloquentModel extends Model
{
    protected $table = 'iot.cajas';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'codigo',
        'es_especial',
        'observacion',
        'clasificacion_taxonomica',
        'nombre',
        'estado',
        'ranura_actual_id',
        'codigo_rfid',
    ];

    protected $casts = [
        'es_especial' => 'boolean',
        'clasificacion_taxonomica' => 'array',
    ];

    /** Ranura que la caja ocupa en este momento (null si no está ubicada). */
    public function ranuraActual(): BelongsTo
    {
        return $this->belongsTo(RanuraGabineteEloquentModel::class, 'ranura_actual_id');
    }

    /** Historial de ubicaciones (ingresos y retiros) de la caja. */
    public function ubicaciones(): HasMany
    {
        return $this->hasMany(UbicacionCajaEloquentModel::class, 'caja_id');
    }

    /** Alertas de ubicación levantadas sobre la caja. */
    public function alertas(): HasMany
    {
        return $this->hasMany(AlertaUbicacionEloquentModel::class, 'caja_id');
    }

    /** Notificaciones emitidas en relación con la caja. */
    public function notificaciones(): HasMany
    {
        return $this->hasMany(NotificacionEloquentModel::class, 'caja_id');
    }
}
