<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'devuelto_en',
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
        // Plantilla v2 (aditivos)
        'record_number',
        'origin',
        'identified_by',
        'date_determined',
        'research_permit',
        'transport_permit',
        'export_import_authorization',
        'scientific_name_authorship',
        'lat_lon_max_error',
        'clade',
        'identification_qualifier',
        'identification_remarks',
        'vernacular_name',
        'type_notes',
        'continent',
        'country_code',
        'locality_notes',
        'locality_code',
        'elevation_max_error',
        'verbatim_elevation',
        'verbatim_depth',
        'verbatim_latitude',
        'verbatim_longitude',
        'verbatim_coordinate_system',
        'verbatim_srs',
        'information_withheld',
        'prior_owner',
        'located_at',
        'ipt_upload',
        'record_created_by',
        'responsible_researcher_export',
        'endemic_verbatim',
        // Junta con el trámite de depósito que trajo el espécimen
        'registro_deposito_id',
        'solicitud_deposito_id',
        'indice_matriz',
        'numero_solicitud_deposito',
        'tipo_tramite_origen',
        'ingresado_en',
        // Jerarquía Darwin Core tal como la declaró el depositante
        'kingdom',
        'phylum',
        'dwc_class',
        'dwc_order',
        'suborder',
        'family',
        'subfamily',
        'tribe',
        'genus',
        'specific_epithet',
        'infraspecific_epithet',
        'taxon_rank',
        // Columnas de la matriz que antes no tenían destino
        'sampling_protocol',
        'other_catalog_numbers',
        'event_time',
        'project_name',
        'collection_notes',
        'medium',
        'movilization_permit',
        'language',
        'dwc_extra',
    ];

    protected $casts = [
        'devuelto_en' => 'datetime',
        'ingresado_en' => 'datetime',
        'endemic' => 'boolean',
        'fecha_colecta' => 'date',
        'fecha_colecta_fin' => 'date',
        'dwc_extra' => 'array',
    ];

    /**
     * Solo el material que la colección custodia hoy.
     *
     * Excluye lo devuelto a su depositante: la fila no se borra —el rastro de qué estuvo
     * bajo custodia es documentación— pero ese material ya no está en el museo y contarlo
     * infla las cifras de la colección.
     *
     * La cuarentena NO se excluye: ese material sigue aquí, solo que aislado hasta su
     * revisión sanitaria.
     *
     * @param  Builder<self>  $query
     */
    public function scopeEnLaColeccion($query): void
    {
        $query->where(function ($q): void {
            $q->whereNull('estado_custodia')
                ->orWhere('estado_custodia', '!=', 'Devuelto');
        });
    }

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
