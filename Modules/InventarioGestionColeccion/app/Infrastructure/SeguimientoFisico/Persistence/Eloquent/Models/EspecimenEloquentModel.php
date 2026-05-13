<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EspecimenEloquentModel extends Model
{
    protected $table = 'taxonomia.especimenes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'codigo_catalogo',
        'taxon_id',
        'localidad',
        'fecha_colecta',
        'colector',
        'entidad_depositante_id',
        'estado',
    ];

    public function taxon(): BelongsTo
    {
        return $this->belongsTo(TaxonEloquentModel::class, 'taxon_id');
    }

    public function entidadDepositante(): BelongsTo
    {
        return $this->belongsTo(EntidadDepositanteEloquentModel::class, 'entidad_depositante_id');
    }
}
