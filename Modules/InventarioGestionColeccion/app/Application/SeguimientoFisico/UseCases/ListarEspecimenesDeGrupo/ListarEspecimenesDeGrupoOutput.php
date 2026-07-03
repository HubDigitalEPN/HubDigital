<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesDeGrupo;

final readonly class ListarEspecimenesDeGrupoOutput
{
    /**
     * @param  array<int, array{
     *   id: string,
     *   codigoCatalogo: string,
     *   taxonNombre: ?string,
     *   colector: string,
     *   localidad: string,
     *   fechaColecta: string,
     *   fechaVerbatim: ?string,
     *   taxonVerbatim: ?string,
     *   localidadVerbatim: ?string
     * }>  $items
     */
    public function __construct(
        public array $items,
    ) {}
}
