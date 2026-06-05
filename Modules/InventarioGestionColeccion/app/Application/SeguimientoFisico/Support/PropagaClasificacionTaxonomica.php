<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Support;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ClasificacionTaxonomicaPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\CalculadorClasificacionDominante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\ComparadorTaxonomicoUnitTray;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;

/**
 * Lógica de propagación de ClasificacionTaxonomica compartida entre los handlers
 * de UnitTray: Especimen → UnitTray → Caja.
 */
trait PropagaClasificacionTaxonomica
{
    /**
     * Dado un array de especimenIds, resuelve la clasificación dominante
     * (subfamilia más frecuente; desempate por género más frecuente).
     *
     * @param  string[]  $especimenIds
     */
    private function resolverDominantePorEspecimenes(
        array $especimenIds,
        EspecimenRepositoryInterface $especimenRepo,
        ClasificacionTaxonomicaPort $clasificacionPort,
    ): ?ClasificacionTaxonomica {
        $clasificaciones = [];

        foreach ($especimenIds as $rawId) {
            $especimen = $especimenRepo->buscarPorId(EspecimenId::desde($rawId));
            if ($especimen === null) {
                continue;
            }

            $cls = $clasificacionPort->resolverParaTaxon($especimen->taxonId());
            if ($cls !== null && ! $cls->estaVacia()) {
                $clasificaciones[] = $cls;
            }
        }

        return $this->clasificacionMasFrecuente($clasificaciones);
    }

    /**
     * Soft alert: identifica los especímenes cuya clasificación NO coincide con la
     * dominante del tray (hasta especie). No bloquea la asignación — solo advierte que
     * "no parecen pertenecer" a este tray. Los especímenes sin clasificación se ignoran.
     *
     * @param  string[]  $especimenIds
     * @return string[] códigos de catálogo de los especímenes fuera de lugar
     */
    private function detectarEspecimenesFueraDeLugar(
        array $especimenIds,
        ?ClasificacionTaxonomica $dominante,
        EspecimenRepositoryInterface $especimenRepo,
        ClasificacionTaxonomicaPort $clasificacionPort,
    ): array {
        if ($dominante === null || $dominante->estaVacia()) {
            return [];
        }

        $comparador = new ComparadorTaxonomicoUnitTray;
        $fueraDeLugar = [];

        foreach ($especimenIds as $rawId) {
            $especimen = $especimenRepo->buscarPorId(EspecimenId::desde($rawId));
            if ($especimen === null) {
                continue;
            }

            $cls = $clasificacionPort->resolverParaTaxon($especimen->taxonId());
            if ($cls === null || $cls->estaVacia()) {
                continue;
            }

            if (! $comparador->perteneceAlGrupo($cls, $dominante)) {
                $fueraDeLugar[] = $especimen->codigoCatalogo();
            }
        }

        return $fueraDeLugar;
    }

    /**
     * Recalcula la clasificación de la Caja a partir de TODOS sus UnitTrays
     * y persiste la Caja.
     */
    private function propagarClasificacionACaja(
        CajaId $cajaId,
        UnitTrayRepository $unitTrayRepo,
        Caja $caja,
        CajaRepository $cajaRepo,
    ): void {
        $trays = $unitTrayRepo->buscarPorCaja($cajaId);

        $clasificaciones = [];
        foreach ($trays as $tray) {
            $cls = $tray->clasificacionDominante();
            if ($cls !== null && ! $cls->estaVacia()) {
                $clasificaciones[] = $cls;
            }
        }

        $dominante = $this->clasificacionMasFrecuente($clasificaciones);

        $dominante !== null
            ? $caja->actualizarClasificacion($dominante)
            : $caja->limpiarClasificacion();

        $cajaRepo->guardar($caja);
    }

    /**
     * @param  ClasificacionTaxonomica[]  $clasificaciones
     */
    private function clasificacionMasFrecuente(array $clasificaciones): ?ClasificacionTaxonomica
    {
        return (new CalculadorClasificacionDominante)->calcular($clasificaciones);
    }
}
