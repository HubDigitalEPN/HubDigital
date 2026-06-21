<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearGabinete;

/**
 * DTO de entrada con los datos para dar de alta un gabinete (código, nombre y total de ranuras).
 */
final readonly class CrearGabineteInput
{
    public function __construct(
        public string $codigo,
        public string $nombre,
        public int $totalRanuras,
    ) {}
}
