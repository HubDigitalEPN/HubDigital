<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReordenarFamiliasEnGabinete;

/**
 * DTO de salida con el gabinete reordenado y el número de cajas efectivamente movidas.
 */
final readonly class ReordenarFamiliasEnGabineteOutput
{
    private function __construct(
        public string $gabineteId,
        public int $cajasMovidas,
    ) {}

    public static function fromPrimitives(array $data): self
    {
        return new self(
            gabineteId: $data['gabineteId'],
            cajasMovidas: $data['cajasMovidas'],
        );
    }
}
