<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenEsperadoFamiliasEloquentModel extends Model
{
    protected $table = 'iot.orden_esperado_familias';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'familias',
    ];

    protected $casts = [
        'id' => 'string',
        'familias' => 'array',
    ];
}
