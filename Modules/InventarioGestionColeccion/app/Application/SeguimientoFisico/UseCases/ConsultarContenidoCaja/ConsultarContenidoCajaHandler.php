<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoCaja;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;

/**
 * Resuelve los unit trays de una caja con la clasificación dominante de cada uno.
 * Alimenta el nivel "caja" del mapa (grilla de unit trays).
 *
 * @see ConsultarContenidoCajaInput
 * @see ConsultarContenidoCajaOutput
 */
final class ConsultarContenidoCajaHandler
{
    /**
     * @param  UnitTrayRepository  $unitTrayRepo  Lista los unit trays de la caja consultada.
     */
    public function __construct(
        private readonly UnitTrayRepository $unitTrayRepo,
    ) {}

    /**
     * Recupera los unit trays de la caja, los ordena por su número y devuelve cada uno con su
     * clasificación taxonómica dominante (o null si está sin clasificar).
     */
    public function handle(ConsultarContenidoCajaInput $input): ConsultarContenidoCajaOutput
    {
        $cajaId = CajaId::desde($input->cajaId);
        $unitTrays = $this->unitTrayRepo->buscarPorCaja($cajaId);

        usort($unitTrays, fn ($a, $b) => $a->numero() <=> $b->numero());

        $items = array_map(fn ($tray): ConsultarContenidoCajaItemOutput => new ConsultarContenidoCajaItemOutput(
            unitTrayId: (string) $tray->id(),
            numero: $tray->numero(),
            clasificacionDominante: $this->clasificacionAArray($tray->clasificacionDominante()),
        ), $unitTrays);

        return new ConsultarContenidoCajaOutput(items: $items);
    }

    /** @return ?array<string, mixed> */
    private function clasificacionAArray(?ClasificacionTaxonomica $clasificacion): ?array
    {
        if ($clasificacion === null || $clasificacion->estaVacia()) {
            return null;
        }

        return [
            'orden' => $clasificacion->orden(),
            'suborden' => $clasificacion->suborden(),
            'superfamilia' => $clasificacion->superfamilia(),
            'familia' => $clasificacion->familia(),
            'subfamilia' => $clasificacion->subfamilia(),
            'genero' => $clasificacion->genero(),
            'especie' => $clasificacion->especie(),
            'subfamilias' => $clasificacion->subfamilias(),
            'generos' => $clasificacion->generos(),
        ];
    }
}
