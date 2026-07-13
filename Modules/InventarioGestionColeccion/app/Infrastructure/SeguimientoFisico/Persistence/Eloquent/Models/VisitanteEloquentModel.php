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
        'puede_reubicar',
        'registrado_en',
    ];

    protected $casts = [
        'version_acceso' => 'integer',
        'puede_reubicar' => 'boolean',
        'registrado_en' => 'datetime',
    ];
}
