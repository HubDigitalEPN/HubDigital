<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Eloquent que mapea el orden taxonómico esperado de familias en la colección,
 * almacenado como una lista ordenada. Sirve de referencia para detectar cajas fuera del
 * orden taxonómico previsto. Es el puente de persistencia de la entidad del dominio.
 */
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
