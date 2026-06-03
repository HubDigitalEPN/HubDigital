<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarMuestra;

final readonly class ConfirmarMuestraInput
{
    public function __construct(
        public string $muestraId,
    ) {}
}
