<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoUnitTray;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ClasificacionTaxonomicaPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;

/**
 * Resuelve los especímenes asignados a un unit tray con su nombre científico.
 * Alimenta el nivel más profundo del mapa (lista de especímenes del unit tray).
 *
 * @see ConsultarContenidoUnitTrayInput
 * @see ConsultarContenidoUnitTrayOutput
 */
final class ConsultarContenidoUnitTrayHandler
{
    /**
     * @param  UnitTrayEspecimenRepository  $asignacionRepo  Resuelve los especímenes asignados al unit tray.
     * @param  EspecimenRepositoryInterface  $especimenRepo  Recupera cada espécimen por su identificador.
     * @param  TaxonRepositoryInterface  $taxonRepo  Resuelve el nombre científico de los taxones en un solo lote.
     * @param  ClasificacionTaxonomicaPort  $clasificacionPort  Resuelve género y especie recorriendo el árbol taxonómico.
     */
    public function __construct(
        private readonly UnitTrayEspecimenRepository $asignacionRepo,
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
        private readonly ClasificacionTaxonomicaPort $clasificacionPort,
    ) {}

    /**
     * Recupera los especímenes asignados al unit tray y resuelve su nombre científico en un
     * único lote (para no golpear la base de datos remota por cada espécimen), devolviendo
     * cada uno con su código de catálogo y nombre científico.
     */
    public function handle(ConsultarContenidoUnitTrayInput $input): ConsultarContenidoUnitTrayOutput
    {
        $unitTrayId = UnitTrayId::desde($input->unitTrayId);
        $especimenIds = $this->asignacionRepo->especimenIdsPorUnitTray($unitTrayId);

        $especimenes = [];
        foreach ($especimenIds as $especimenId) {
            $especimen = $this->especimenRepo->buscarPorId(EspecimenId::desde($especimenId));
            if ($especimen !== null) {
                $especimenes[] = $especimen;
            }
        }

        // El nombre científico se resuelve en un solo lote (buscarPorIds), evitando
        // un SELECT por espécimen contra el pooler remoto.
        $taxonIds = array_values(array_unique(array_filter(
            array_map(fn (Especimen $e) => $e->taxonId(), $especimenes),
            fn (?string $id) => $id !== null && $id !== '',
        )));

        $nombresPorTaxon = [];
        if ($taxonIds !== []) {
            foreach ($this->taxonRepo->buscarPorIds($taxonIds) as $taxon) {
                $nombresPorTaxon[(string) $taxon->id()] = $taxon->nombreCientifico();
            }
        }

        // Género y especie se resuelven recorriendo el árbol taxonómico una sola vez por taxón
        // distinto (cacheado), para no repetir el ascenso por cada espécimen del unit tray.
        $clasificacionPorTaxon = [];
        foreach ($taxonIds as $taxonId) {
            $clasificacionPorTaxon[$taxonId] = $this->clasificacionPort->resolverParaTaxon($taxonId);
        }

        $items = array_map(function (Especimen $e) use ($nombresPorTaxon, $clasificacionPorTaxon): ConsultarContenidoUnitTrayItemOutput {
            $taxonId = $e->taxonId();
            $tieneTaxon = $taxonId !== null && $taxonId !== '';
            $clasificacion = $tieneTaxon ? ($clasificacionPorTaxon[$taxonId] ?? null) : null;

            return new ConsultarContenidoUnitTrayItemOutput(
                especimenId: (string) $e->id(),
                codigoCatalogo: $e->codigoCatalogo(),
                nombreCientifico: $tieneTaxon ? ($nombresPorTaxon[$taxonId] ?? null) : null,
                genero: $clasificacion instanceof ClasificacionTaxonomica ? $clasificacion->genero() : null,
                especie: $clasificacion instanceof ClasificacionTaxonomica ? $clasificacion->especie() : null,
                individualCount: $e->individualCount(),
                provincia: $e->stateProvince(),
                localidad: $e->localityName() ?? $e->localidad(),
            );
        }, $especimenes);

        return new ConsultarContenidoUnitTrayOutput(items: $items);
    }
}
