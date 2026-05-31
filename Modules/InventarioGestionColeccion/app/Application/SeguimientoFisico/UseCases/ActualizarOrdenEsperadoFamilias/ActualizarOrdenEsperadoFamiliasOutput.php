<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarOrdenEsperadoFamilias;

final readonly class ActualizarOrdenEsperadoFamiliasOutput
{
    /**
     * @param  string[]  $familias  secuencia persistida (normalizada y deduplicada)
     */
    public function __construct(
        public array $familias,
    ) {}
}
