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

        // La identidad real de una localidad para el catálogo es su nombre + rango.
        // El padre (p. ej. la provincia) es incidental: "Aguarico (sitio)" bajo
        // Orellana y bajo Napo NO son dos lugares distintos para esta pantalla. Por
        // eso colapsamos por (nombre_canonico, rango) y mostramos cada uno una vez.
        //
        // Se trabaja sobre el conjunto completo (las localidades canónicas son un
        // set curado y pequeño, no las 48k filas del catálogo) y se pagina ya
        // colapsado, para que los conteos de paginación cuadren con lo que se ve.
        $todas = $this->localidadRepo->buscarTodos();

        // Mapa id → nombre para resolver el nombre del padre sin consultas extra.
        $nombrePorId = [];
        foreach ($todas as $l) {
            $nombrePorId[(string) $l->id()] = $l->nombreCanonico();
        }

        // Un representante por grupo: se conserva el registro MÁS completo (con
        // coordenadas/país/provincia), así "Editar" abre el mejor candidato.
        /** @var array<string, Localidad> $representantes */
        $representantes = [];
        foreach ($todas as $l) {
            $clave = mb_strtolower(trim($l->nombreCanonico())).'|'.$l->rango()->value;
            $actual = $representantes[$clave] ?? null;
            if ($actual === null || $this->completitud($l) > $this->completitud($actual)) {
                $representantes[$clave] = $l;
            }
        }

        $unicas = array_values($representantes);
        usort(
            $unicas,
            fn (Localidad $a, Localidad $b): int => strcasecmp($a->nombreCanonico(), $b->nombreCanonico())
                ?: strcmp($a->rango()->value, $b->rango()->value)
        );

        $total = count($unicas);
        $totalPaginas = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $offset = ($page - 1) * $perPage;

        $items = array_map(
            fn (Localidad $l) => new ListarLocalidadesItemOutput(
                id: (string) $l->id(),
                nombreCanonico: $l->nombreCanonico(),
                rango: $l->rango()->value,
                padreId: $l->padreId() !== null ? (string) $l->padreId() : null,
                padreNombre: $l->padreId() !== null ? ($nombrePorId[(string) $l->padreId()] ?? null) : null,
                latitud: $l->coordenada()?->latitud,
                longitud: $l->coordenada()?->longitud,
                country: $l->country(),
                stateProvince: $l->stateProvince(),
                municipality: $l->municipality(),
                geodeticDatum: $l->geodeticDatum(),
            ),
            array_slice($unicas, $offset, $perPage),
        );

        return new ListarLocalidadesOutput(
            items: $items,
            total: $total,
            page: $page,
            perPage: $perPage,
            totalPaginas: $totalPaginas,
        );
    }

    /**
     * Puntaje de completitud de una localidad: cuántos campos descriptivos tiene
     * poblados. Se usa para elegir el representante de cada grupo (nombre+rango).
     */
    private function completitud(Localidad $l): int
    {
        return ($l->coordenada() !== null ? 1 : 0)
            + ($l->country() !== null ? 1 : 0)
            + ($l->stateProvince() !== null ? 1 : 0)
            + ($l->municipality() !== null ? 1 : 0);
    }
}
