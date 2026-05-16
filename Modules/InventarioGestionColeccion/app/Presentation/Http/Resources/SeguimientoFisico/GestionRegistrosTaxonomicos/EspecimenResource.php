<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Resources\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen\RegistrarEspecimenOutput;

/** @property RegistrarEspecimenOutput $resource */
final class EspecimenResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'codigo_catalogo' => $this->resource->codigoCatalogo,
            'taxon_id' => $this->resource->taxonId,
            'localidad' => $this->resource->localidad,
            'fecha_colecta' => $this->resource->fechaColecta,
            'colector' => $this->resource->colector,
            'estado' => $this->resource->estado,
            'entidad_depositante_id' => $this->resource->entidadDepositanteId,
            'occurrence_id' => $this->resource->occurrenceId,
            'catalog_number' => $this->resource->catalogNumber,
            'old_code' => $this->resource->oldCode,
            'cardex_liquid_collection_code' => $this->resource->cardexLiquidCollectionCode,
            'individual_count' => $this->resource->individualCount,
            'preparations' => $this->resource->preparations,
            'disposition' => $this->resource->disposition,
            'occurrence_status' => $this->resource->occurrenceStatus,
            'specimen_notes' => $this->resource->specimenNotes,
            'country' => $this->resource->country,
            'state_province' => $this->resource->stateProvince,
            'municipality' => $this->resource->municipality,
            'locality_name' => $this->resource->localityName,
            'decimal_latitude' => $this->resource->decimalLatitude,
            'decimal_longitude' => $this->resource->decimalLongitude,
            'geodetic_datum' => $this->resource->geodeticDatum,
            'elevation_in_meters' => $this->resource->elevationInMeters,
            'biome' => $this->resource->biome,
            'habitat' => $this->resource->habitat,
            'identificadores' => $this->resource->identificadores,
        ];
    }
}
