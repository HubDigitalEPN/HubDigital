<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarFechaParaVerbatim;

final readonly class ConfirmarFechaParaVerbatimOutput
{
    public function __construct(
        public string $verbatim,
        public string $fechaInicio,
        public ?string $fechaFin,
        public int $especimenesAfectados,
    ) {}
}
