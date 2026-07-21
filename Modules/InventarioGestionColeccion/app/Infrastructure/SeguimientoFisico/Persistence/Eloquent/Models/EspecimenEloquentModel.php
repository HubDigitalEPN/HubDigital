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
        'taxon_verbatim',
        'muestra_id',
        'localidad_id',
        'localidad',
        'localidad_verbatim',
        'fecha_colecta',
        'fecha_verbatim',
        'fecha_colecta_fin',
        'colector',
        'entidad_depositante_id',
        'estado',
        'estado_custodia',
        'individual_count',
        'individual_count_verbatim',
        'sex',
        'life_stage',
        'caste',
        'type_status',
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
        'coord_verbatim',
        'geodetic_datum',
        'elevation_min_m',
        'elevation_max_m',
        'biome',
        'habitat',
        'microhabitat',
        'biogeographic_region',
        'endemic',
        'dna_notes',
        'occurrence_remarks',
        'taxonomic_notes',
        'acta_recepcion',
        'estado_revision',
        'motivo_revision',
        'fila_origen_excel',
    ];

    protected $casts = [
        'endemic' => 'boolean',
        'fecha_colecta' => 'date',
        'fecha_colecta_fin' => 'date',
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
