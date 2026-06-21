<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegenerarAccesoVisitante;

final readonly class RegenerarAccesoVisitanteInput
{
    public function __construct(
        public string $visitanteId,
    ) {}
}
