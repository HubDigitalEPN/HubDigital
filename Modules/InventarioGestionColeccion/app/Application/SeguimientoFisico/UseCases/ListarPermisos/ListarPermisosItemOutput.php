<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarPermisos;

final readonly class ListarPermisosItemOutput
{
    public function __construct(
        public string $id,
        public string $tipo,
        public ?string $numero,
        public ?string $responsable,
        public ?string $detalles,
    ) {}
}
