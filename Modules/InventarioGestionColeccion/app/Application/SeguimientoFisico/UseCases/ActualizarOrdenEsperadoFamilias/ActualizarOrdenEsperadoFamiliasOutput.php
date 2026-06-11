<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarOrdenEsperadoFamilias;

/**
 * DTO de salida con la secuencia de familias finalmente persistida.
 */
final readonly class ActualizarOrdenEsperadoFamiliasOutput
{
    /**
     * @param  string[]  $familias  secuencia persistida (normalizada y deduplicada)
     */
    public function __construct(
        public array $familias,
    ) {}
}
