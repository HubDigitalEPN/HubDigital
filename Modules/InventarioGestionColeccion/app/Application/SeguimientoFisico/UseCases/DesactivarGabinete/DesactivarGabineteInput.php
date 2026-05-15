<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DesactivarGabinete;

final readonly class DesactivarGabineteInput
{
    public function __construct(
        public string $gabineteId,
    ) {}
}
