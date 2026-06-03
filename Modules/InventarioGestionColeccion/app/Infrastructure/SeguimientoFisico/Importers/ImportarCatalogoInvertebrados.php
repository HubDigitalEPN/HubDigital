<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\MuestraColecta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\MuestraColectaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\MuestraColectaId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\Contracts\FuenteCatalogoIterator;

/**
 * Orchestrator del importador (spec P6 + hardening).
 *
 * Lee filas de una `FuenteCatalogoIterator`, las mapea con `FilaCatalogoMapper`,
 * construye la jerarquía taxonómica con `ConstructorTaxonomiaImport` y persiste
 * `Especimen` + `MuestraColecta` (agrupando por `oldCode`).
 *
 * Características clave:
 *  - **Cero pérdida**: cada fila genera un espécimen, incluso si tiene warnings.
 *  - **Idempotencia**: si la fila ya fue importada (mismo `fila_origen_excel`),
 *    se salta y se incrementa `duplicadosSaltados`.
 *  - **Taxonomía construida en vuelo**: kingdom..genus + species (binomen)
 *    encadenados vía `padre_id`. El `taxon_id` final se decide por `taxonRank`.
 *  - **Agrupación de muestras**: filas con mismo `oldCode` comparten `muestra_id`.
 *  - **Estado de revisión**: especímenes con warnings → `pendiente` + motivo.
 *  - **Dry-run**: cuenta sin persistir.
 *  - **Chunk / rango**: --from/--to/--chunk para batches.
 *  - **Tolerante a errores**: una fila fatal no aborta el import; queda en `erroresFatales`.
 */
final class ImportarCatalogoInvertebrados
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly MuestraColectaRepositoryInterface $muestraRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
        private readonly FilaCatalogoMapper $mapper,
    ) {}

    public function ejecutar(
        FuenteCatalogoIterator $fuente,
        bool $dryRun = false,
        int $desde = 1,
        ?int $hasta = null,
        int $chunk = 500,
    ): ResultadoImport {
        $filasLeidas = 0;
        $especimenesPersistidos = 0;
        $muestrasCreadas = 0;
        $duplicadosSaltados = 0;
        $marcadosParaRevision = 0;
        $motivosRevision = [];
        $erroresFatales = [];

        $constructorTaxonomia = new ConstructorTaxonomiaImport($this->taxonRepo);

        /** @var array<string, MuestraColectaId> $muestrasPorOldCode */
        $muestrasPorOldCode = [];
        $procesadosEnChunk = 0;

        foreach ($fuente->iterar() as $numFila => $fila) {
            if ($numFila < $desde) {
                continue;
            }
            if ($hasta !== null && $numFila > $hasta) {
                break;
            }
            $filasLeidas++;

            try {
                // Idempotencia: saltar filas ya importadas (mismo número de fila origen).
                if (! $dryRun && $this->especimenRepo->existePorFilaOrigen($numFila)) {
                    $duplicadosSaltados++;

                    continue;
                }

                $mapeada = $this->mapper->mapear($fila);
                $normalizada = $this->mapper->normalizarClaves($fila);

                // Resolver taxonomía: kingdom..genus + species binomen.
                $taxonId = $constructorTaxonomia->resolverDeFila($normalizada);

                // Resolver muestra: si hay oldCode, agrupamos en una muestra
                // compartida entre todos los especímenes con el mismo código.
                $muestraId = null;
                if ($mapeada->oldCode !== null) {
                    if (! isset($muestrasPorOldCode[$mapeada->oldCode])) {
                        $nuevaMuestra = MuestraColecta::crear(
                            id: $this->muestraRepo->nextIdentity(),
                            codigoMuestra: $mapeada->oldCode,
                            fechaVerbatim: $mapeada->fechaVerbatim,
                            localidadVerbatim: $mapeada->localidadVerbatim,
                            colector: $mapeada->colector !== '' ? $mapeada->colector : null,
                            motivoRevision: 'agrupación por oldCode sin confirmar',
                        );
                        if (! $dryRun) {
                            $this->muestraRepo->guardar($nuevaMuestra);
                        }
                        $muestrasPorOldCode[$mapeada->oldCode] = $nuevaMuestra->id();
                        $muestrasCreadas++;
                    }
                    $muestraId = (string) $muestrasPorOldCode[$mapeada->oldCode];
                }

                $especimen = Especimen::crear(
                    id: $this->especimenRepo->nextIdentity(),
                    codigoCatalogo: $mapeada->codigoCatalogo,
                    taxonId: $taxonId !== null ? (string) $taxonId : null,
                    localidad: $mapeada->localidad,
                    fechaColecta: $mapeada->fechaColecta,
                    colector: $mapeada->colector,
                    occurrenceId: $mapeada->occurrenceId,
                    catalogNumber: $mapeada->catalogNumber,
                    oldCode: $mapeada->oldCode,
                    cardexLiquidCollectionCode: $mapeada->cardexLiquidCollectionCode,
                    individualCount: $mapeada->individualCount,
                    preparations: $mapeada->preparations,
                    disposition: $mapeada->disposition,
                    occurrenceStatus: $mapeada->occurrenceStatus,
                    specimenNotes: $mapeada->specimenNotes,
                    country: $mapeada->country,
                    stateProvince: $mapeada->stateProvince,
                    municipality: $mapeada->municipality,
                    localityName: $mapeada->localityName,
                    decimalLatitude: $mapeada->decimalLatitude,
                    decimalLongitude: $mapeada->decimalLongitude,
                    geodeticDatum: $mapeada->geodeticDatum,
                    elevationMinM: $mapeada->elevationMinM,
                    biome: $mapeada->biome,
                    habitat: $mapeada->habitat,
                    taxonVerbatim: $mapeada->taxonVerbatim,
                    muestraId: $muestraId,
                    localidadVerbatim: $mapeada->localidadVerbatim,
                    fechaVerbatim: $mapeada->fechaVerbatim,
                    fechaColectaFin: $mapeada->fechaColectaFin,
                    individualCountVerbatim: $mapeada->individualCountVerbatim,
                    sex: $mapeada->sex,
                    lifeStage: $mapeada->lifeStage,
                    caste: $mapeada->caste,
                    typeStatus: $mapeada->typeStatus,
                    coordVerbatim: $mapeada->coordVerbatim,
                    elevationMaxM: $mapeada->elevationMaxM,
                    microhabitat: $mapeada->microhabitat,
                    biogeographicRegion: $mapeada->biogeographicRegion,
                    endemic: $mapeada->endemic,
                    dnaNotes: $mapeada->dnaNotes,
                    occurrenceRemarks: $mapeada->occurrenceRemarks,
                    taxonomicNotes: $mapeada->taxonomicNotes,
                    actaRecepcion: $mapeada->actaRecepcion,
                    filaOrigenExcel: $numFila,
                );

                if ($mapeada->requiereRevision()) {
                    $especimen->marcarParaRevision($mapeada->motivoRevision() ?? 'revisión requerida');
                    $marcadosParaRevision++;
                    foreach ($mapeada->warnings as $w) {
                        $motivosRevision[$w] = ($motivosRevision[$w] ?? 0) + 1;
                    }
                }

                if (! $dryRun) {
                    $this->especimenRepo->guardar($especimen);
                }
                $especimenesPersistidos++;

                if (++$procesadosEnChunk >= $chunk) {
                    $procesadosEnChunk = 0;
                    // Hook para liberar memoria si se quisiera (no-op por ahora).
                }
            } catch (\Throwable $e) {
                $erroresFatales[] = [
                    'fila' => $numFila,
                    'error' => $e->getMessage(),
                ];
            }
        }

        arsort($motivosRevision);

        return new ResultadoImport(
            filasLeidas: $filasLeidas,
            especimenesPersistidos: $especimenesPersistidos,
            muestrasCreadas: $muestrasCreadas,
            duplicadosSaltados: $duplicadosSaltados,
            marcadosParaRevision: $marcadosParaRevision,
            motivosRevision: $motivosRevision,
            erroresFatales: $erroresFatales,
            dryRun: $dryRun,
        );
    }
}
