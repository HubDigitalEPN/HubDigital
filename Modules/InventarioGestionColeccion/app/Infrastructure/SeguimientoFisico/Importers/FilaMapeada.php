<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers;

/**
 * DTO que representa una fila del Excel mapeada a campos de Especimen.
 *
 * El mapper produce este DTO desde un array asociativo de la fila cruda.
 * El orchestrator lo consume para llamar a `Especimen::crear()`.
 *
 * El campo `motivoRevision` acumula descripciones de transformaciones no
 * triviales (coord no numérica, count no numérico, occurrence_id ausente)
 * separadas por `; `. Si tiene contenido, `requiereRevision` es true.
 */
final readonly class FilaMapeada
{
    /**
     * @param  string[]  $warnings  Lista de advertencias acumuladas durante el mapeo.
     */
    public function __construct(
        public string $codigoCatalogo,
        public ?string $occurrenceId,
        public ?string $catalogNumber,
        public ?string $oldCode,
        public ?string $cardexLiquidCollectionCode,
        public ?string $taxonVerbatim,
        public ?string $localidadVerbatim,
        public string $localidad,
        public ?string $fechaVerbatim,
        public string $fechaColecta,
        public ?string $fechaColectaFin,
        public string $colector,
        public ?int $individualCount,
        public ?string $individualCountVerbatim,
        public ?string $sex,
        public ?string $lifeStage,
        public ?string $caste,
        public ?string $typeStatus,
        public ?string $preparations,
        public ?string $disposition,
        public ?string $occurrenceStatus,
        public ?string $specimenNotes,
        public ?string $country,
        public ?string $stateProvince,
        public ?string $municipality,
        public ?string $localityName,
        public ?float $decimalLatitude,
        public ?float $decimalLongitude,
        public ?string $coordVerbatim,
        public ?string $geodeticDatum,
        public ?float $elevationMinM,
        public ?float $elevationMaxM,
        public ?string $biome,
        public ?string $habitat,
        public ?string $microhabitat,
        public ?string $biogeographicRegion,
        public ?bool $endemic,
        public ?string $dnaNotes,
        public ?string $occurrenceRemarks,
        public ?string $taxonomicNotes,
        public ?string $actaRecepcion,
        public array $warnings,
    ) {}

    public function requiereRevision(): bool
    {
        return $this->warnings !== [];
    }

    public function motivoRevision(): ?string
    {
        return $this->warnings !== [] ? implode('; ', $this->warnings) : null;
    }
}
