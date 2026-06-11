<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Eloquent que mapea la bitácora de eventos de dominio del componente (event store
 * append-only): cada fila guarda el tipo de agregado, el evento, su versión, el actor que
 * lo originó y el momento de ocurrencia. Sirve como historial auditable del ciclo IoT.
 */
class EventoCicloIotEloquentModel extends Model
{
    protected $table = 'iot.eventos_ciclo_iot';

    public $timestamps = false;

    protected $fillable = [
        'tipo_agregado',
        'agregado_id',
        'tipo_evento',
        'version_evento',
        'datos',
        'actor_id',
        'actor_rol',
        'ocurrido_en',
    ];

    protected $casts = [
        'datos' => 'array',
    ];
}
