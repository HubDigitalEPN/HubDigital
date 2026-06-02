<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarOrdenEsperadoFamilias;

final readonly class ActualizarOrdenEsperadoFamiliasInput
{
    /**
     * @param  string[]  $familias  secuencia de familias en el orden esperado por el curador
     */
    public function __construct(
        public array $familias,
    ) {}
}
