<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DefinirReubicacionVisitante;

/**
 * DTO de entrada para habilitar o revocar la capacidad de un visitante de reubicar especímenes.
 */
final readonly class DefinirReubicacionVisitanteInput
{
    public function __construct(
        public string $visitanteId,
        public bool $habilitado,
    ) {}
}
