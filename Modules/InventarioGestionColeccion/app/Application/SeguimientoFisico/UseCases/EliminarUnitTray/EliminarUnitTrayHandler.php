<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EliminarUnitTray;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Support\PropagaClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;

/**
 * Caso de uso: eliminar un unit tray de su caja y recalcular la clasificación taxonómica de
 * la caja a partir de los trays restantes.
 *
 * @see EliminarUnitTrayInput
 * @see EliminarUnitTrayOutput
 */
final class EliminarUnitTrayHandler
{
    use PropagaClasificacionTaxonomica;

    /**
     * @param  UnitTrayRepository  $unitTrayRepo  Recupera y elimina el unit tray.
     * @param  CajaRepository  $cajaRepo  Recupera la caja contenedora y persiste su clasificación recalculada.
     * @param  TransactionManagerPort  $transactionManager  Envuelve la eliminación y la propagación en una transacción.
     */
    public function __construct(
        private readonly UnitTrayRepository $unitTrayRepo,
        private readonly CajaRepository $cajaRepo,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    /**
     * Elimina el unit tray y propaga a su caja la clasificación dominante recalculada sobre
     * los trays restantes, todo dentro de una transacción.
     *
     * @throws \DomainException si el unit tray o su caja asociada no existen.
     */
    public function handle(EliminarUnitTrayInput $input): EliminarUnitTrayOutput
    {
        $unitTrayId = UnitTrayId::desde($input->unitTrayId);

        $unitTray = $this->unitTrayRepo->buscarPorId($unitTrayId);
        if ($unitTray === null) {
            throw new \DomainException("UnitTray '{$input->unitTrayId}' no encontrado.");
        }

        $cajaId = $unitTray->cajaId();

        $caja = $this->cajaRepo->buscarPorId($cajaId);
        if ($caja === null) {
            throw new \DomainException('Caja asociada al UnitTray no encontrada.');
        }

        $this->transactionManager->executeTransactional(
            function () use ($unitTrayId, $cajaId, $caja): void {
                $this->unitTrayRepo->eliminar($unitTrayId);

                $this->propagarClasificacionACaja(
                    $cajaId,
                    $this->unitTrayRepo,
                    $caja,
                    $this->cajaRepo,
                );
            }
        );

        $cajaCls = $caja->clasificacionTaxonomica();

        return EliminarUnitTrayOutput::fromPrimitives([
            'unitTrayId' => (string) $unitTrayId,
            'cajaId' => (string) $cajaId,
            'cajaTieneClasificacion' => $cajaCls !== null && ! $cajaCls->estaVacia(),
        ]);
    }
}
