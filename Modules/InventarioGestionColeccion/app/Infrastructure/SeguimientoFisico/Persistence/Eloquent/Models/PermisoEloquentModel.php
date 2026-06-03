<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class PermisoEloquentModel extends Model
{
    protected $table = 'taxonomia.permisos';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tipo',
        'numero',
        'responsable',
        'detalles',
    ];
}
