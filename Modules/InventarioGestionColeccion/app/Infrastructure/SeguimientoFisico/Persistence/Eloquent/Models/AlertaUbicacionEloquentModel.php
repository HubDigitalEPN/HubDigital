<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertaUbicacionEloquentModel extends Model
{
    protected $table = 'iot.alertas_ubicacion';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'caja_id',
        'tipo',
        'estado',
        'datos_contexto',
        'motivo_resolucion',
    ];

    protected $casts = [
        'datos_contexto' => 'array',
    ];

    public function caja(): BelongsTo
    {
        return $this->belongsTo(CajaEloquentModel::class, 'caja_id');
    }
}
