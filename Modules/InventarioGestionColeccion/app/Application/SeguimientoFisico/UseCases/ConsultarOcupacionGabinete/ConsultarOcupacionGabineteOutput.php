<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarOcupacionGabinete;

/**
 * DTO de salida con el estado de ocupación de todas las ranuras del gabinete.
 */
final readonly class ConsultarOcupacionGabineteOutput
{
    /** @param ConsultarOcupacionGabineteItemOutput[] $items */
    public function __construct(
        public array $items,
    ) {}
}
