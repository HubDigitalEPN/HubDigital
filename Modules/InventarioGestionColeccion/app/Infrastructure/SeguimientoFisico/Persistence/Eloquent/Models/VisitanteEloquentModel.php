<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class VisitanteEloquentModel extends Model
{
    protected $table = 'taxonomia.visitantes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nombre',
        'contacto',
        'version_acceso',
        'registrado_en',
    ];

    protected $casts = [
        'version_acceso' => 'integer',
        'registrado_en' => 'datetime',
    ];
}
