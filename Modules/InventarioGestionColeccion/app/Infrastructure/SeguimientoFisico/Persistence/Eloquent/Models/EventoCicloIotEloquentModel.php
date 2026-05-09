<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

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
