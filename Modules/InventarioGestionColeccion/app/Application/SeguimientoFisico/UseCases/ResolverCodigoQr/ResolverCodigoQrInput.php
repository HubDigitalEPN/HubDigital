<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverCodigoQr;

final readonly class ResolverCodigoQrInput
{
    public function __construct(
        /** Contenido escaneado del QR físico. */
        public string $payload,
    ) {}
}
