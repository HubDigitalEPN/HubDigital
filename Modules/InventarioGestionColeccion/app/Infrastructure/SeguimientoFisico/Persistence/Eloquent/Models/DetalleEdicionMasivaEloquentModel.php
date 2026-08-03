<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleEdicionMasivaEloquentModel extends Model
{
    protected $table = 'taxonomia.ediciones_masivas_detalle';

    public $incrementing = false;

    protected $keyType = 'string';

    /** La tabla no lleva timestamps: la fecha relevante es la de la cabecera. */
    public $timestamps = false;

    protected $fillable = [
        'id',
        'edicion_id',
        'especimen_id',
        'valor_previo',
        'valor_aplicado',
        'estado_reversion',
        'revertido_en',
    ];

    protected $casts = [
        'revertido_en' => 'datetime',
    ];
}
