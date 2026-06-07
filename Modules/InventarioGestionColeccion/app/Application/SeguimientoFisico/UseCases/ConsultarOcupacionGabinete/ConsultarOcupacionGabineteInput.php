<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarOcupacionGabinete;

final readonly class ConsultarOcupacionGabineteInput
{
    public function __construct(
        public string $gabineteId,
    ) {}
}
