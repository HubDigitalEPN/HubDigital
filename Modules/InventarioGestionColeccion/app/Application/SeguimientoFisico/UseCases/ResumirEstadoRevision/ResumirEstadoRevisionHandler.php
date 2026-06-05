<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResumirEstadoRevision;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\MuestraColectaRepositoryInterface;

/**
 * Resumen agregado del estado de revisión del catálogo. Une los conteos de
 * las 5 bandejas de revisión + las métricas globales (total especímenes y
 * % publicable a GBIF). Una sola llamada al handler hace 8 queries COUNT,
 * todas SQL-side.
 */
final class ResumirEstadoRevisionHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenes,
        private readonly MuestraColectaRepositoryInterface $muestras,
    ) {}

    public function handle(): ResumirEstadoRevisionOutput
    {
        return new ResumirEstadoRevisionOutput(
            totalEspecimenes: $this->especimenes->contarTotal(),
            publicablesGbif: $this->especimenes->contarPublicablesGbif(),
            pendientesRevision: $this->especimenes->contarPendientesRevision(),
            localidadesVerbatimPendientes: $this->especimenes->contarLocalidadVerbatimsPendientes(),
            taxonVerbatimPendientes: $this->especimenes->contarTaxonVerbatimsPendientes(),
            duplicadosCatalogNumberGrupos: $this->especimenes->contarGruposCatalogNumberDuplicados(2),
            fechaVerbatimPendientes: $this->especimenes->contarFechaVerbatimsPendientes(),
            muestrasPendientes: $this->muestras->contarParaRevision(),
        );
    }
}
