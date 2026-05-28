<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarUnitTraysPorCaja;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UnitTray;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;

final class ListarUnitTraysPorCajaHandler
{
    public function __construct(
        private readonly UnitTrayRepository $unitTrayRepo,
        private readonly UnitTrayEspecimenRepository $asignacionRepo,
    ) {}

    public function handle(ListarUnitTraysPorCajaInput $input): ListarUnitTraysPorCajaOutput
    {
        $cajaId = CajaId::desde($input->cajaId);

        $items = array_map(function (UnitTray $tray): array {
            $clasificacion = $tray->clasificacionDominante();

            return [
                'unitTrayId' => (string) $tray->id(),
                'numero' => $tray->numero(),
                'subfamilia' => $clasificacion?->subfamilia(),
                'genero' => $clasificacion?->genero(),
                'totalEspecimenes' => count($this->asignacionRepo->especimenIdsPorUnitTray($tray->id())),
            ];
        }, $this->unitTrayRepo->buscarPorCaja($cajaId));

        return new ListarUnitTraysPorCajaOutput(items: $items);
    }
}
