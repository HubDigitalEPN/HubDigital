<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarCodigoQr;

final readonly class GenerarCodigoQrInput
{
    public function __construct(
        public string $especimenId,
    ) {}
}
