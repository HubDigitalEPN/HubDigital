<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarUnitTraysPorCaja;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UnitTray;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\ComparadorTaxonomicoUnitTray;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;

/**
 * Caso de uso: listar los unit trays de una caja ordenados por su taxonomía (subfamilia →
 * género → especie), con su clasificación dominante y su total de especímenes.
 *
 * @see ListarUnitTraysPorCajaInput
 * @see ListarUnitTraysPorCajaOutput
 */
final class ListarUnitTraysPorCajaHandler
{
    /**
     * @param  UnitTrayRepository  $unitTrayRepo  Recupera los unit trays de la caja.
     * @param  UnitTrayEspecimenRepository  $asignacionRepo  Cuenta los especímenes asignados a cada tray.
     */
    public function __construct(
        private readonly UnitTrayRepository $unitTrayRepo,
        private readonly UnitTrayEspecimenRepository $asignacionRepo,
    ) {}

    /**
     * Recupera los unit trays de la caja, los ordena por su clasificación taxonómica
     * (desempatando por número) y los proyecta con su subfamilia/género/especie y el total de
     * especímenes que contienen.
     */
    public function handle(ListarUnitTraysPorCajaInput $input): ListarUnitTraysPorCajaOutput
    {
        $cajaId = CajaId::desde($input->cajaId);

        $trays = $this->unitTrayRepo->buscarPorCaja($cajaId);

        // Los trays se presentan organizados alfabéticamente por taxonomía
        // (subfamilia → género → especie); el número solo desempata trays equivalentes.
        $comparador = new ComparadorTaxonomicoUnitTray;
        usort($trays, function (UnitTray $a, UnitTray $b) use ($comparador): int {
            $cmp = $comparador->comparar($a->clasificacionDominante(), $b->clasificacionDominante());

            return $cmp !== 0 ? $cmp : $a->numero() <=> $b->numero();
        });

        $items = array_map(function (UnitTray $tray): array {
            $clasificacion = $tray->clasificacionDominante();

            return [
                'unitTrayId' => (string) $tray->id(),
                'numero' => $tray->numero(),
                'subfamilia' => $clasificacion?->subfamilia(),
                'genero' => $clasificacion?->genero(),
                'especie' => $clasificacion?->especie(),
                'totalEspecimenes' => count($this->asignacionRepo->especimenIdsPorUnitTray($tray->id())),
            ];
        }, $trays);

        return new ListarUnitTraysPorCajaOutput(items: $items);
    }
}
