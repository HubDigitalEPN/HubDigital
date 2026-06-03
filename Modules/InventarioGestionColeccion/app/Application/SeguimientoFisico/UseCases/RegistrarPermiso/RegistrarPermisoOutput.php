<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarPermiso;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\PermisoId;

final readonly class RegistrarPermisoOutput
{
    public function __construct(
        public PermisoId $id,
        public string $tipo,
        public ?string $numero,
        public ?string $responsable,
        public ?string $detalles,
    ) {}
}
