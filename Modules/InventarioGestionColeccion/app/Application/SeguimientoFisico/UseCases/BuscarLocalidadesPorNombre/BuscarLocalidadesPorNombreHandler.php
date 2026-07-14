<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarLocalidadesPorNombre;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\LocalidadRepositoryInterface;

/**
 * Búsqueda libre de localidades canónicas por nombre. Alimenta el selector
 * "reasignar a otro nombre" de la bandeja de revisión de localidades.
 */
final class BuscarLocalidadesPorNombreHandler
{
    public function __construct(
        private readonly LocalidadRepositoryInterface $localidadRepo,
    ) {}

    public function handle(BuscarLocalidadesPorNombreInput $input): BuscarLocalidadesPorNombreOutput
    {
        $consulta = trim($input->consulta);
        if ($consulta === '') {
            return new BuscarLocalidadesPorNombreOutput([]);
        }

        $encontrados = array_slice(
            $this->localidadRepo->buscarPorNombreContiene($consulta),
            0,
            max(1, $input->limite),
        );

        $items = array_map(
            fn (Localidad $l): array => [
                'id' => (string) $l->id(),
                'nombreCanonico' => $l->nombreCanonico(),
                'rango' => $l->rango()->value,
            ],
            $encontrados,
        );

        return new BuscarLocalidadesPorNombreOutput($items);
    }
}
