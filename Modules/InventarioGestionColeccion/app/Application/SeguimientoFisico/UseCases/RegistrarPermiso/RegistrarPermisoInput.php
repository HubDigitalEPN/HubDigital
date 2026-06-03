<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarPermiso;

final readonly class RegistrarPermisoInput
{
    public function __construct(
        public string $tipo,
        public ?string $numero = null,
        public ?string $responsable = null,
        public ?string $detalles = null,
    ) {}
}
