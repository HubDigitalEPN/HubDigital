<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerCodigoQr;

final readonly class ObtenerCodigoQrInput
{
    public function __construct(
        public string $especimenId,
    ) {}
}
