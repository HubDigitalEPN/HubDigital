<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;

/**
 * Aplana un {@see Especimen} al array plano de TODAS sus columnas, con las
 * mismas claves que declara {@see RegistroColumnasEspecimen::todas()}.
 *
 * Fuente de verdad única para cualquier pantalla que liste o inspeccione
 * especímenes (búsqueda del catálogo, bandejas de revisión, ficha completa):
 * garantiza que el nombre de cada campo coincida con el catálogo de columnas y
 * evita que cada handler recorte campos por su cuenta (bug histórico de las
 * bandejas, que descartaban fecha/taxón al re-mapear).
 *
 * El nombre científico del taxón se pasa resuelto desde fuera (el llamador
 * conoce el mapa taxonId → nombre; aquí no se toca el repositorio para mantener
 * la función pura y sin dependencias de infraestructura).
 */
final class MapeadorFilaEspecimen
{
    /**
     * @return array<string, mixed>
     */
    public static function mapear(Especimen $e, ?string $taxonNombre = null): array
    {
        return [
            'id' => (string) $e->id(),
            'codigoCatalogo' => $e->codigoCatalogo(),
            'taxonId' => $e->taxonId(),
            'taxonNombre' => $e->taxonId() !== null ? ($taxonNombre ?? $e->taxonId()) : null,
            'taxonVerbatim' => $e->taxonVerbatim(),
            'localidad' => $e->localidad(),
            'localidadVerbatim' => $e->localidadVerbatim(),
            'fechaColecta' => $e->fechaColecta(),
            'fechaColectaFin' => $e->fechaColectaFin(),
            'fechaVerbatim' => $e->fechaVerbatim(),
            'colector' => $e->colector(),
            'entidadDepositanteId' => $e->entidadDepositanteId(),
            'estado' => $e->estado()->value,
            'occurrenceId' => $e->occurrenceId(),
            'catalogNumber' => $e->catalogNumber(),
            'oldCode' => $e->oldCode(),
            'cardexLiquidCollectionCode' => $e->cardexLiquidCollectionCode(),
            'individualCount' => $e->individualCount(),
            'individualCountVerbatim' => $e->individualCountVerbatim(),
            'sex' => $e->sex(),
            'lifeStage' => $e->lifeStage(),
            'caste' => $e->caste(),
            'typeStatus' => $e->typeStatus(),
            'preparations' => $e->preparations(),
            'disposition' => $e->disposition(),
            'occurrenceStatus' => $e->occurrenceStatus(),
            'specimenNotes' => $e->specimenNotes(),
            'country' => $e->country(),
            'stateProvince' => $e->stateProvince(),
            'municipality' => $e->municipality(),
            'localityName' => $e->localityName(),
            'decimalLatitude' => $e->decimalLatitude(),
            'decimalLongitude' => $e->decimalLongitude(),
            'coordVerbatim' => $e->coordVerbatim(),
            'geodeticDatum' => $e->geodeticDatum(),
            'elevationMinM' => $e->elevationMinM(),
            'elevationMaxM' => $e->elevationMaxM(),
            'biome' => $e->biome(),
            'habitat' => $e->habitat(),
            'microhabitat' => $e->microhabitat(),
            'biogeographicRegion' => $e->biogeographicRegion(),
            'endemic' => $e->endemic(),
            'dnaNotes' => $e->dnaNotes(),
            'occurrenceRemarks' => $e->occurrenceRemarks(),
            'taxonomicNotes' => $e->taxonomicNotes(),
            'actaRecepcion' => $e->actaRecepcion(),
            'estadoRevision' => $e->estadoRevision()->value,
            'motivoRevision' => $e->motivoRevision(),
            'filaOrigenExcel' => $e->filaOrigenExcel(),
            // Plantilla v2 (aditivos)
            'recordNumber' => $e->recordNumber(),
            'origin' => $e->origin(),
            'identifiedBy' => $e->identifiedBy(),
            'dateDetermined' => $e->dateDetermined(),
            'researchPermit' => $e->researchPermit(),
            'transportPermit' => $e->transportPermit(),
            'exportImportAuthorization' => $e->exportImportAuthorization(),
            'scientificNameAuthorship' => $e->scientificNameAuthorship(),
            'latLonMaxError' => $e->latLonMaxError(),
            'clade' => $e->clade(),
            'identificationQualifier' => $e->identificationQualifier(),
            'identificationRemarks' => $e->identificationRemarks(),
            'vernacularName' => $e->vernacularName(),
            'typeNotes' => $e->typeNotes(),
            'continent' => $e->continent(),
            'countryCode' => $e->countryCode(),
            'localityNotes' => $e->localityNotes(),
            'localityCode' => $e->localityCode(),
            'elevationMaxError' => $e->elevationMaxError(),
            'verbatimElevation' => $e->verbatimElevation(),
            'verbatimDepth' => $e->verbatimDepth(),
            'verbatimLatitude' => $e->verbatimLatitude(),
            'verbatimLongitude' => $e->verbatimLongitude(),
            'verbatimCoordinateSystem' => $e->verbatimCoordinateSystem(),
            'verbatimSrs' => $e->verbatimSrs(),
            'informationWithheld' => $e->informationWithheld(),
            'priorOwner' => $e->priorOwner(),
            'locatedAt' => $e->locatedAt(),
            'iptUpload' => $e->iptUpload(),
            'recordCreatedBy' => $e->recordCreatedBy(),
            'responsibleResearcherExport' => $e->responsibleResearcherExport(),
            'endemicVerbatim' => $e->endemicVerbatim(),
            'identificadores' => array_map(fn ($i) => $i->toArray(), $e->identificadores()),
        ];
    }
}
