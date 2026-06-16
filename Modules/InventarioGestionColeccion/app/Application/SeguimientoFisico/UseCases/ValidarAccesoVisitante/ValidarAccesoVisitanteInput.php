<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ValidarAccesoVisitante;

final readonly class ValidarAccesoVisitanteInput
{
    public function __construct(
        public string $visitanteId,
        public int $version,
    ) {}
}
