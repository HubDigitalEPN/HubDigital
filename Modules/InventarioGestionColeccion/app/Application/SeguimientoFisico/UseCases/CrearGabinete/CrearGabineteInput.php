<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearGabinete;

final readonly class CrearGabineteInput
{
    public function __construct(
        public string $codigo,
        public string $nombre,
        public int $totalRanuras,
    ) {}
}
