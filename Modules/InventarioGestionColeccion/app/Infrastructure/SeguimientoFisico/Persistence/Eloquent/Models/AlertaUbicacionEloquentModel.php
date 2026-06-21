<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent que mapea la tabla de alertas de ubicación generadas por el componente
 * (incongruencia taxonómica, tiempo de extracción excedido). Es solo el puente de
 * persistencia; la lógica de la alerta vive en la entidad de dominio que este modelo respalda.
 */
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

    /** Caja sobre la que se levantó la alerta. */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(CajaEloquentModel::class, 'caja_id');
    }
}
