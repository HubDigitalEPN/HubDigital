<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesAsignables;

/**
 * DTO de entrada con el filtro de búsqueda, el tray en contexto y el tope de resultados.
 */
final readonly class ListarEspecimenesAsignablesInput
{
    /**
     * @param  ?string  $busqueda  Filtro ILIKE sobre código de catálogo o nombre científico.
     * @param  ?string  $unitTrayId  Tray en contexto: sus especímenes ya asignados se
     *                               incluyen siempre, aunque no coincidan con la búsqueda.
     * @param  int  $limite  Tope de coincidencias devueltas (la lista es buscable,
     *                       nunca se cargan los 48k especímenes de golpe).
     */
    public function __construct(
        public ?string $busqueda = null,
        public ?string $unitTrayId = null,
        public int $limite = 50,
    ) {}
}
