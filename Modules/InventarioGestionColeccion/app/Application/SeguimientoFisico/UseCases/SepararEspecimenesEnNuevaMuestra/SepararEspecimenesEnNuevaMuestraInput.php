<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\SepararEspecimenesEnNuevaMuestra;

final readonly class SepararEspecimenesEnNuevaMuestraInput
{
    /** @param string[] $especimenIds Subconjunto a separar de la muestra de origen. */
    public function __construct(
        public string $muestraOrigenId,
        public array $especimenIds,
    ) {}
}
