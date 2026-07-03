<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearLocalidadYEnlazarVerbatim;

final readonly class CrearLocalidadYEnlazarVerbatimInput
{
    /**
     * @param  string[]|null  $especimenIds  Si se provee (no vacío), el enlace se
     *                                       aplica solo a esos especímenes; si es
     *                                       null, a todo el grupo del verbatim.
     */
    public function __construct(
        public string $verbatim,
        public string $nombreCanonico,
        public string $rango,
        public ?array $especimenIds = null,
    ) {}
}
