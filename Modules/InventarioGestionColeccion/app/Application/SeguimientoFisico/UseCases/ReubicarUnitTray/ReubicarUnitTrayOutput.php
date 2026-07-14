<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReubicarUnitTray;

final readonly class ReubicarUnitTrayOutput
{
    public function __construct(
        public bool $reubicado,
        public string $unitTrayId,
        public string $cajaDestinoId,
        public string $actorRol,
    ) {}
}
