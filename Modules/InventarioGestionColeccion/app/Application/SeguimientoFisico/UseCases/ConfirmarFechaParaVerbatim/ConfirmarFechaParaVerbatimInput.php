<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarFechaParaVerbatim;

final readonly class ConfirmarFechaParaVerbatimInput
{
    public function __construct(
        public string $verbatim,
        public string $fechaInicio,
        public ?string $fechaFin = null,
    ) {}
}
