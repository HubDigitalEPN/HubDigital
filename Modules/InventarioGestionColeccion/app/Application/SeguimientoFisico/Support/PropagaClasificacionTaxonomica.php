<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Support;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ClasificacionTaxonomicaPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
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
     * Devuelve la ClasificacionTaxonomica más frecuente del array.
     * Criterio primario: subfamilia más frecuente.
     * Criterio de desempate: género más frecuente dentro de esa subfamilia.
     *
     * @param  ClasificacionTaxonomica[]  $clasificaciones
     */
    private function clasificacionMasFrecuente(array $clasificaciones): ?ClasificacionTaxonomica
    {
        if ($clasificaciones === []) {
            return null;
        }

        $frecuencias = [];
        foreach ($clasificaciones as $cls) {
            $key = ($cls->subfamilia() ?? '').'|'.($cls->genero() ?? '');
            if (! isset($frecuencias[$key])) {
                $frecuencias[$key] = ['cls' => $cls, 'count' => 0];
            }
            $frecuencias[$key]['count']++;
        }

        usort($frecuencias, static function (array $a, array $b): int {
            if ($b['count'] !== $a['count']) {
                return $b['count'] - $a['count'];
            }

            return strcasecmp(
                ($a['cls']->subfamilia() ?? '').($a['cls']->genero() ?? ''),
                ($b['cls']->subfamilia() ?? '').($b['cls']->genero() ?? ''),
            );
        });

        return $frecuencias[0]['cls'];
    }
}
