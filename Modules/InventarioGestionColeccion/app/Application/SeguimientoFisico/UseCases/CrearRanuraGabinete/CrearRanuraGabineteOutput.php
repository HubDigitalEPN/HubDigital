<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearRanuraGabinete;

/**
 * DTO de salida con los datos de la ranura recién creada.
 */
final readonly class CrearRanuraGabineteOutput
{
    public function __construct(
        public string $id,
        public string $gabineteId,
        public int $numeroRanura,
        public bool $activa,
        public ?string $cajaActualId = null,
    ) {}
}
