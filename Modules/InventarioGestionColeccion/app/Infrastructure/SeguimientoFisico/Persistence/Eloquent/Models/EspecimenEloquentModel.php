<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EspecimenEloquentModel extends Model
{
    protected $table = 'taxonomia.especimenes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'codigo_catalogo',
        'occurrence_id',
        'catalog_number',
        'old_code',
        'cardex_liquid_collection_code',
        'taxon_id',
        'localidad',
        'fecha_colecta',
        'colector',
        'entidad_depositante_id',
        'estado',
        'individual_count',
        'preparations',
        'disposition',
        'occurrence_status',
        'specimen_notes',
        'country',
        'state_province',
        'municipality',
        'locality_name',
        'decimal_latitude',
        'decimal_longitude',
        'geodetic_datum',
        'elevation_in_meters',
        'biome',
        'habitat',
    ];

    public function taxon(): BelongsTo
    {
        return $this->belongsTo(TaxonEloquentModel::class, 'taxon_id');
    }

    public function entidadDepositante(): BelongsTo
    {
        return $this->belongsTo(EntidadDepositanteEloquentModel::class, 'entidad_depositante_id');
    }

    public function identificadores(): HasMany
    {
        return $this->hasMany(EspecimenIdentificadorEloquentModel::class, 'especimen_id');
    }
}
