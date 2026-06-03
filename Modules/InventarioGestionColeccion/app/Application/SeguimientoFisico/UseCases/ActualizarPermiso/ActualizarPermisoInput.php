<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarPermiso;

final readonly class ActualizarPermisoInput
{
    public function __construct(
        public string $permisoId,
        public string $tipo,
        public ?string $numero = null,
        public ?string $responsable = null,
        public ?string $detalles = null,
    ) {}
}
