<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResincronizarClasificaciones;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ClasificacionTaxonomicaPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Support\PropagaClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;

/**
 * Caso de uso de mantenimiento: recalcula la clasificación dominante de TODOS los unit
 * trays a partir de los especímenes realmente asignados hoy, y la repropaga a sus cajas.
 *
 * Es el fallback para clasificaciones cacheadas que quedaron huérfanas: especímenes
 * borrados directamente de la BD, trays movidos por versiones anteriores que no
 * repropagaban a la caja de origen, etc. Un tray sin especímenes (o cuyos especímenes
 * ya no existen) queda sin clasificación; una caja sin trays clasificados también.
 */
final class ResincronizarClasificacionesHandler
{
    use PropagaClasificacionTaxonomica;

    public function __construct(
        private readonly CajaRepository $cajaRepo,
        private readonly UnitTrayRepository $unitTrayRepo,
        private readonly UnitTrayEspecimenRepository $asignacionRepo,
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly ClasificacionTaxonomicaPort $clasificacionPort,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    public function handle(): ResincronizarClasificacionesOutput
    {
        $traysRecalculados = 0;
        $traysSinClasificacion = 0;
        $cajasRecalculadas = 0;
        $cajasSinClasificacion = 0;

        $this->transactionManager->executeTransactional(function () use (
            &$traysRecalculados,
            &$traysSinClasificacion,
            &$cajasRecalculadas,
            &$cajasSinClasificacion,
        ): void {
            foreach ($this->cajaRepo->buscarTodas() as $caja) {
                foreach ($this->unitTrayRepo->buscarPorCaja($caja->id()) as $tray) {
                    $especimenIds = $this->asignacionRepo->especimenIdsPorUnitTray($tray->id());

                    $clasificacion = $especimenIds === []
                        ? null
                        : $this->resolverClasificacionAgregadaPorEspecimenes(
                            $especimenIds,
                            $this->especimenRepo,
                            $this->clasificacionPort,
                        );

                    $clasificacion !== null
                        ? $tray->actualizarClasificacion($clasificacion)
                        : $tray->limpiarClasificacion();

                    $this->unitTrayRepo->guardar($tray);

                    $traysRecalculados++;
                    if ($clasificacion === null) {
                        $traysSinClasificacion++;
                    }
                }

                $this->propagarClasificacionACaja($caja->id(), $this->unitTrayRepo, $caja, $this->cajaRepo);

                $cajasRecalculadas++;
                if ($caja->clasificacionTaxonomica() === null) {
                    $cajasSinClasificacion++;
                }
            }
        });

        return new ResincronizarClasificacionesOutput(
            traysRecalculados: $traysRecalculados,
            traysSinClasificacion: $traysSinClasificacion,
            cajasRecalculadas: $cajasRecalculadas,
            cajasSinClasificacion: $cajasSinClasificacion,
        );
    }
}
