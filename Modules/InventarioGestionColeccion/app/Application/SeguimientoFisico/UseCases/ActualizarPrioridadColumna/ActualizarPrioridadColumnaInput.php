<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarPrioridadColumna;

final readonly class ActualizarPrioridadColumnaInput
{
    public function __construct(
        public string $pantalla,
        public string $claveColumna,
        public string $prioridad,
        public ?string $actualizadoPorId = null,
    ) {}
}
