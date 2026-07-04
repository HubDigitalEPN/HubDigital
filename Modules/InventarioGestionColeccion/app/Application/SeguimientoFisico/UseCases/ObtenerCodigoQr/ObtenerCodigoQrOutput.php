<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerCodigoQr;

final readonly class ObtenerCodigoQrOutput
{
    public function __construct(
        public bool $existe,
        public ?string $codigoQrId = null,
        public ?string $payload = null,
    ) {}
}
