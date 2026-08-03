<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class EdicionMasivaEloquentModel extends Model
{
    protected $table = 'taxonomia.ediciones_masivas';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tipo',
        'campo',
        'valor_aplicado',
        'texto_buscado',
        'texto_reemplazo',
        'total_afectados',
        'actor_id',
        'actor_nombre',
        'deshecha_en',
    ];

    protected $casts = [
        'total_afectados' => 'integer',
        'deshecha_en' => 'datetime',
    ];
}
