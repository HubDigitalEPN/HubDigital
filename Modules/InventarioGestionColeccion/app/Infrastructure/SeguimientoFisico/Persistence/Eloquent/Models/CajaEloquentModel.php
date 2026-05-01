<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CajaEloquentModel extends Model
{
    protected $table = 'iot.cajas';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'codigo',
        'familia_taxonomica_id',
        'nombre',
        'capacidad_maxima',
        'estado',
        'ranura_actual_id',
        'codigo_rfid',
    ];

    public function ranuraActual(): BelongsTo
    {
        return $this->belongsTo(RanuraGabineteEloquentModel::class, 'ranura_actual_id');
    }

    public function ubicaciones(): HasMany
    {
        return $this->hasMany(UbicacionCajaEloquentModel::class, 'caja_id');
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(AlertaUbicacionEloquentModel::class, 'caja_id');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(NotificacionEloquentModel::class, 'caja_id');
    }
}
