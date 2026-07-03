<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidadesParaSelector;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\LocalidadRepositoryInterface;

/**
 * Lista TODAS las localidades canónicas como proyección compacta
 * (id, nombreCanonico, rango) ordenada por nombre. Alimenta el selector
 * "Localidad padre" de los formularios de registro/edición: sin esto el
 * desplegable solo ofrecía la página actual de la paginación, impidiendo
 * elegir como padre cualquier localidad fuera de esa página.
 *
 * Las localidades canónicas son un conjunto curado (muy inferior a las 48k
 * filas del catálogo), por lo que traerlas completas es seguro.
 */
final class ListarLocalidadesParaSelectorHandler
{
    public function __construct(
        private readonly LocalidadRepositoryInterface $localidadRepo,
    ) {}

    public function handle(ListarLocalidadesParaSelectorInput $input): ListarLocalidadesParaSelectorOutput
    {
        $localidades = $this->localidadRepo->buscarTodos();

        $items = array_map(
            fn (Localidad $l): array => [
                'id' => (string) $l->id(),
                'nombreCanonico' => $l->nombreCanonico(),
                'rango' => $l->rango()->value,
            ],
            $localidades,
        );

        usort($items, fn (array $a, array $b): int => strcasecmp($a['nombreCanonico'], $b['nombreCanonico']));

        return new ListarLocalidadesParaSelectorOutput($items);
    }
}
