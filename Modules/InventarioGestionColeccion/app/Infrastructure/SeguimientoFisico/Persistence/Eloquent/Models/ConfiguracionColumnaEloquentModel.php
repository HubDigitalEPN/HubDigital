<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionColumnaEloquentModel extends Model
{
    protected $table = 'taxonomia.configuracion_columnas';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'pantalla',
        'clave_columna',
        'prioridad',
        'actualizado_por_id',
    ];
}
