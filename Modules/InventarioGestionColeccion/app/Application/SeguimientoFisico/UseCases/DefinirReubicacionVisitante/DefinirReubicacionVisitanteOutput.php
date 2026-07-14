<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DefinirReubicacionVisitante;

final readonly class DefinirReubicacionVisitanteOutput
{
    public function __construct(
        public string $visitanteId,
        public bool $puedeReubicar,
    ) {}
}
