<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarGabinete;

final readonly class ActualizarGabineteInput
{
    public function __construct(
        public string $gabineteId,
        public string $nombre,
        public int $totalRanuras,
    ) {}
}
