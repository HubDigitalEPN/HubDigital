<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarGabinete;

/**
 * DTO de entrada con el gabinete a actualizar, su nuevo nombre y su nuevo total de ranuras.
 */
final readonly class ActualizarGabineteInput
{
    public function __construct(
        public string $gabineteId,
        public string $nombre,
        public int $totalRanuras,
    ) {}
}
