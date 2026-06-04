<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentificacionEloquentModel extends Model
{
    protected $table = 'taxonomia.identificaciones';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'especimen_id',
        'taxon_id',
        'identificado_por',
        'fecha_determinacion',
        'calificador',
        'observaciones',
        'es_actual',
    ];

    protected $casts = [
        'fecha_determinacion' => 'date',
        'es_actual' => 'boolean',
    ];

    public function especimen(): BelongsTo
    {
        return $this->belongsTo(EspecimenEloquentModel::class, 'especimen_id');
    }

    public function taxon(): BelongsTo
    {
        return $this->belongsTo(TaxonEloquentModel::class, 'taxon_id');
    }
}
