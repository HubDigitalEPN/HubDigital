<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DesactivarGabinete;

/**
 * DTO de entrada con el gabinete que se quiere desactivar.
 */
final readonly class DesactivarGabineteInput
{
    public function __construct(
        public string $gabineteId,
    ) {}
}
