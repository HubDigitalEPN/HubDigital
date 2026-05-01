<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificacionEloquentModel extends Model
{
    protected $table = 'iot.notificaciones';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'caja_id',
        'tipo',
        'datos_contexto',
    ];

    protected $casts = [
        'datos_contexto' => 'array',
    ];

    public function caja(): BelongsTo
    {
        return $this->belongsTo(CajaEloquentModel::class, 'caja_id');
    }
}
