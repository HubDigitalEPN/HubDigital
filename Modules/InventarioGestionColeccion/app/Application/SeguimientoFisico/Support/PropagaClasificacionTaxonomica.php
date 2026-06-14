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
     * Dado un array de especimenIds, resuelve la clasificación AGREGADA del tray: conserva la
     * combinación dominante (subfamilia+género más frecuentes; desempate determinista) como valor
     * representativo, pero amplía los conjuntos de subfamilias y géneros con todos los taxones
     * distintos presentes. Así un tray que alberga varios taxones (p. ej. por error o por
     * restricción de espacio) los muestra todos —sin perder cuál es el dominante— y los propaga
     * tal cual hacia la Caja y el mapa. La detección de "fuera de lugar" sigue usando el valor
     * dominante (accesores escalares), no el agregado.
     *
     * @param  string[]  $especimenIds
     */
    private function resolverClasificacionAgregadaPorEspecimenes(
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

        return (new CalculadorClasificacionDominante)->calcularAgregado($clasificaciones);
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

        // Agregado: la caja conserva su combinación dominante pero acumula todas las
        // subfamilias y géneros distintos de sus trays (una caja puede albergar varios).
        $dominante = (new CalculadorClasificacionDominante)->calcularAgregado($clasificaciones);

        $dominante !== null
            ? $caja->actualizarClasificacion($dominante)
            : $caja->limpiarClasificacion();

        $cajaRepo->guardar($caja);
    }
}
