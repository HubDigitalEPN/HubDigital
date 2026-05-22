<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReordenarFamiliasEnGabinete;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\EventPublisherPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EvaluarOrdenTaxonomicoCaja\EvaluarOrdenTaxonomicoCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EvaluarOrdenTaxonomicoCaja\EvaluarOrdenTaxonomicoCajaInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UbicacionCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;

final class ReordenarFamiliasEnGabineteHandler
{
    public function __construct(
        private readonly CajaRepository $cajaRepo,
        private readonly RanuraGabineteRepository $ranuraRepo,
        private readonly UbicacionCajaRepository $ubicacionRepo,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EventPublisherPort $eventPublisher,
        private readonly EvaluarOrdenTaxonomicoCajaHandler $evaluarOrdenHandler,
    ) {}

    public function handle(ReordenarFamiliasEnGabineteInput $input): ReordenarFamiliasEnGabineteOutput
    {
        $cajasMovidas = 0;
        $movimientosConfirmados = [];

        foreach ($input->movimientos as $datosMovimiento) {
            $cajaId = CajaId::desde($datosMovimiento['caja_id']);
            $ranuraDestinoId = RanuraId::desde($datosMovimiento['ranura_destino_id']);

            $this->transactionManager->executeTransactional(
                function () use ($cajaId, $ranuraDestinoId): void {
                    $caja = $this->cajaRepo->buscarPorId($cajaId);
                    if ($caja === null) {
                        return;
                    }

                    $ranuraDestino = $this->ranuraRepo->buscarPorId($ranuraDestinoId);
                    if ($ranuraDestino === null) {
                        return;
                    }

                    $ocurridoEn = new \DateTimeImmutable;

                    // Cerrar ubicacion activa y liberar ranura de origen
                    $ubicacionActiva = $this->ubicacionRepo->buscarActivaPorCaja($cajaId);
                    if ($ubicacionActiva !== null) {
                        $ranuraOrigen = $this->ranuraRepo->buscarPorId($ubicacionActiva->ranuraGabineteId());
                        if ($ranuraOrigen !== null) {
                            $ranuraOrigen->liberarCaja();
                            $this->ranuraRepo->guardar($ranuraOrigen);
                        }
                        $ubicacionActiva->cerrar($ocurridoEn);
                        $this->ubicacionRepo->guardar($ubicacionActiva);
                    }

                    // Mover la caja a la ranura destino
                    $caja->retirarDeRanura();
                    $caja->ingresarEnRanura($ranuraDestinoId);
                    $ranuraDestino->asignarCaja($cajaId);

                    $nuevaUbicacion = UbicacionCaja::registrar(
                        id: $this->ubicacionRepo->nextIdentity(),
                        cajaId: $cajaId,
                        ranuraGabineteId: $ranuraDestinoId,
                        ingresadaEn: $ocurridoEn,
                    );

                    $this->cajaRepo->guardar($caja);
                    $this->ranuraRepo->guardar($ranuraDestino);
                    $this->ubicacionRepo->guardar($nuevaUbicacion);

                    foreach ($caja->pullEvents() as $evento) {
                        $this->eventPublisher->publish($evento);
                    }
                }
            );

            $movimientosConfirmados[] = [
                'caja_id' => (string) $cajaId,
                'ranura_id' => (string) $ranuraDestinoId,
            ];
            $cajasMovidas++;
        }

        // Evaluar orden taxonómico para cada caja reubicada (fuera de la transacción de movimiento)
        foreach ($movimientosConfirmados as $movimiento) {
            $this->evaluarOrdenHandler->handle(new EvaluarOrdenTaxonomicoCajaInput(
                cajaId: $movimiento['caja_id'],
                ranuraId: $movimiento['ranura_id'],
            ));
        }

        return ReordenarFamiliasEnGabineteOutput::fromPrimitives([
            'gabineteId' => $input->gabineteId,
            'cajasMovidas' => $cajasMovidas,
        ]);
    }
}
