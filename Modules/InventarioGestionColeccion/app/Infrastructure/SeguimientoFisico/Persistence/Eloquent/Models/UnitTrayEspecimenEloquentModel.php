<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class UnitTrayEspecimenEloquentModel extends Model
{
    protected $table = 'iot.unit_tray_especimenes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'unit_tray_id',
        'especimen_id',
    ];
}
