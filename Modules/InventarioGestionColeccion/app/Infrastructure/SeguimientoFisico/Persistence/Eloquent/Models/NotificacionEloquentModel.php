<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent que mapea la tabla de notificaciones del componente, dirigidas al curador
 * a partir de los eventos de seguimiento (con sus datos de contexto). Es el puente de
 * persistencia de la entidad Notificacion del dominio.
 */
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

    /** Caja a la que se refiere la notificación. */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(CajaEloquentModel::class, 'caja_id');
    }
}
