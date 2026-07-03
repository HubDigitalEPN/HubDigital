<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarCodigoQr;

final readonly class GenerarCodigoQrOutput
{
    public function __construct(
        public string $codigoQrId,
        public string $especimenId,
        /** Contenido opaco que viaja codificado en el QR impreso. */
        public string $payload,
    ) {}
}
