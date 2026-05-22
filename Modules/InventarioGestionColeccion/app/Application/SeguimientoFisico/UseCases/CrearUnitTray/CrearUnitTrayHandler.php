<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearUnitTray;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ClasificacionTaxonomicaPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Support\PropagaClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UnitTray;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;

final class CrearUnitTrayHandler
{
    use PropagaClasificacionTaxonomica;

    public function __construct(
        private readonly UnitTrayRepository $unitTrayRepo,
        private readonly CajaRepository $cajaRepo,
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly ClasificacionTaxonomicaPort $clasificacionPort,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    public function handle(CrearUnitTrayInput $input): CrearUnitTrayOutput
    {
        $cajaId = CajaId::desde($input->cajaId);

        $caja = $this->cajaRepo->buscarPorId($cajaId);
        if ($caja === null) {
            throw new \DomainException("Caja '{$input->cajaId}' no encontrada.");
        }

        $unitTray = $this->transactionManager->executeTransactional(
            function () use ($input, $cajaId, $caja): UnitTray {
                $unitTray = UnitTray::crear(
                    id: $this->unitTrayRepo->nextIdentity(),
                    cajaId: $cajaId,
                    numero: $input->numero,
                );

                if ($input->especimenIds !== []) {
                    $clasificacion = $this->resolverDominantePorEspecimenes(
                        $input->especimenIds,
                        $this->especimenRepo,
                        $this->clasificacionPort,
                    );

                    if ($clasificacion !== null) {
                        $unitTray->actualizarClasificacion($clasificacion);
                    }
                }

                $this->unitTrayRepo->guardar($unitTray);

                $this->propagarClasificacionACaja(
                    $cajaId,
                    $this->unitTrayRepo,
                    $caja,
                    $this->cajaRepo,
                );

                return $unitTray;
            }
        );

        $cls = $unitTray->clasificacionDominante();

        return CrearUnitTrayOutput::fromPrimitives([
            'unitTrayId' => (string) $unitTray->id(),
            'cajaId' => (string) $cajaId,
            'numero' => $unitTray->numero(),
            'tieneClasificacion' => $cls !== null && ! $cls->estaVacia(),
            'subfamiliaAsignada' => $cls?->subfamilia(),
            'generoAsignado' => $cls?->genero(),
        ]);
    }
}
