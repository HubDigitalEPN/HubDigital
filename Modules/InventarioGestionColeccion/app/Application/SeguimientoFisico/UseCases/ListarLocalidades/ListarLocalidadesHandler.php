<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidades;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\LocalidadRepositoryInterface;

final class ListarLocalidadesHandler
{
    public function __construct(
        private readonly LocalidadRepositoryInterface $localidadRepo,
    ) {}

    public function handle(ListarLocalidadesInput $input): ListarLocalidadesOutput
    {
        $page = max(1, $input->page);
        $perPage = max(1, min(200, $input->perPage));

        $total = $this->localidadRepo->contarTodos();
        $totalPaginas = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $offset = ($page - 1) * $perPage;

        $localidades = $this->localidadRepo->listarPagina($offset, $perPage);

        $padreIds = array_values(array_unique(array_filter(
            array_map(fn (Localidad $l) => $l->padreId() !== null ? (string) $l->padreId() : null, $localidades)
        )));

        $padresMap = [];
        if ($padreIds !== []) {
            foreach ($this->localidadRepo->buscarPorIds($padreIds) as $padre) {
                $padresMap[(string) $padre->id()] = $padre->nombreCanonico();
            }
        }

        $items = array_map(
            fn (Localidad $l) => new ListarLocalidadesItemOutput(
                id: (string) $l->id(),
                nombreCanonico: $l->nombreCanonico(),
                rango: $l->rango()->value,
                padreId: $l->padreId() !== null ? (string) $l->padreId() : null,
                padreNombre: $l->padreId() !== null ? ($padresMap[(string) $l->padreId()] ?? null) : null,
                latitud: $l->coordenada()?->latitud,
                longitud: $l->coordenada()?->longitud,
                country: $l->country(),
                stateProvince: $l->stateProvince(),
                municipality: $l->municipality(),
                geodeticDatum: $l->geodeticDatum(),
            ),
            $localidades,
        );

        return new ListarLocalidadesOutput(
            items: $items,
            total: $total,
            page: $page,
            perPage: $perPage,
            totalPaginas: $totalPaginas,
        );
    }
}
