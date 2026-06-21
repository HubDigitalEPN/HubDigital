<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\VerificarTiemposExtraccion;

/**
 * DTO de entrada con la caja a verificar y el límite de días hábiles tolerado fuera de su ranura.
 */
final readonly class VerificarTiemposExtraccionInput
{
    public function __construct(
        public string $cajaId,
        public int $limiteDiasHabiles = 1,
    ) {}
}
