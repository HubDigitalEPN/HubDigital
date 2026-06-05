<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\VerificarTiemposExtraccion;

final readonly class VerificarTiemposExtraccionInput
{
    public function __construct(
        public string $cajaId,
        public int $limiteDiasHabiles = 1,
    ) {}
}
