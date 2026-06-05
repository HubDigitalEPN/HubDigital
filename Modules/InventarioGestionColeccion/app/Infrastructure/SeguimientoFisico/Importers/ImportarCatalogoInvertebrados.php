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
 * Orchestrator del importador (spec P6 + hardening + bulk).
 *
 * Lee filas de una `FuenteCatalogoIterator`, las mapea con `FilaCatalogoMapper`,
 * construye la jerarquía taxonómica con `ConstructorTaxonomiaImport` y persiste
 * `Especimen` + `MuestraColecta` (agrupando por `oldCode`) en **bulk inserts**
 * por chunk para minimizar round-trips contra la BD.
 *
 * Características clave:
 *  - **Cero pérdida**: cada fila genera un espécimen, incluso si tiene warnings.
 *  - **Idempotencia bulk**: al inicio de cada chunk se consulta a la BD qué
 *    `fila_origen_excel` ya están persistidas y se saltan. Una sola query
 *    por chunk en vez de N queries por fila.
 *  - **Taxonomía precargada**: el `ConstructorTaxonomiaImport` carga TODOS
 *    los taxones existentes en memoria al inicio (una query). Después, cada
 *    miss de cache se sabe que es un taxón nuevo → INSERT directo sin SELECT.
 *  - **Bulk inserts**: especímenes (+ identificadores) y muestras se acumulan
 *    en buffers y se flushean cada `$chunk` filas.
 *  - **Agrupación de muestras**: filas con mismo `oldCode` comparten `muestra_id`.
 *  - **Estado de revisión**: especímenes con warnings → `pendiente` + motivo.
 *  - **Dry-run**: cuenta sin persistir (excepto la precarga inicial de taxones).
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
        if (! $dryRun) {
            $constructorTaxonomia->precargarTaxonomiaExistente();
        }

        /** @var array<string, MuestraColectaId> $muestrasPorOldCode */
        $muestrasPorOldCode = [];
        /** @var array<int, array{0: int, 1: array<string, mixed>}> $bufferFilas  número de fila + fila normalizada */
        $bufferFilas = [];

        $flush = function () use (
            &$bufferFilas,
            &$muestrasPorOldCode,
            &$especimenesPersistidos,
            &$muestrasCreadas,
            &$duplicadosSaltados,
            &$marcadosParaRevision,
            &$motivosRevision,
            &$erroresFatales,
            $constructorTaxonomia,
            $dryRun,
        ): void {
            if ($bufferFilas === []) {
                return;
            }

            // Idempotencia en bulk: una sola query a la BD para saber qué filas ya están.
            $filasIds = array_map(fn ($entry) => $entry[0], $bufferFilas);
            $yaExistentes = $dryRun ? [] : array_flip($this->especimenRepo->filasOrigenExistentes($filasIds));

            $especimenesNuevos = [];
            $muestrasNuevasEnEsteChunk = [];

            foreach ($bufferFilas as $entry) {
                [$numFila, $fila] = $entry;
                if (isset($yaExistentes[$numFila])) {
                    $duplicadosSaltados++;

                    continue;
                }

                try {
                    $mapeada = $this->mapper->mapear($fila);
                    $normalizada = $this->mapper->normalizarClaves($fila);

                    $taxonId = $constructorTaxonomia->resolverDeFila($normalizada);

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
                            $muestrasPorOldCode[$mapeada->oldCode] = $nuevaMuestra->id();
                            $muestrasNuevasEnEsteChunk[] = $nuevaMuestra;
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

                    $especimenesNuevos[] = $especimen;
                    $especimenesPersistidos++;
                } catch (\Throwable $e) {
                    $erroresFatales[] = [
                        'fila' => $numFila,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            if (! $dryRun) {
                if ($muestrasNuevasEnEsteChunk !== []) {
                    $this->muestraRepo->guardarBatch($muestrasNuevasEnEsteChunk);
                }
                if ($especimenesNuevos !== []) {
                    $this->especimenRepo->guardarBatch($especimenesNuevos);
                }
            }

            $bufferFilas = [];
        };

        foreach ($fuente->iterar() as $numFila => $fila) {
            if ($numFila < $desde) {
                continue;
            }
            if ($hasta !== null && $numFila > $hasta) {
                break;
            }
            $filasLeidas++;
            $bufferFilas[] = [$numFila, $fila];

            if (count($bufferFilas) >= $chunk) {
                $flush();
            }
        }

        $flush();

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
