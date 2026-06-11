<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarOrdenEsperadoFamilias;

/**
 * DTO de entrada con la secuencia de familias en el orden esperado por el curador.
 */
final readonly class ActualizarOrdenEsperadoFamiliasInput
{
    /**
     * @param  string[]  $familias  secuencia de familias en el orden esperado por el curador
     */
    public function __construct(
        public array $familias,
    ) {}
}
