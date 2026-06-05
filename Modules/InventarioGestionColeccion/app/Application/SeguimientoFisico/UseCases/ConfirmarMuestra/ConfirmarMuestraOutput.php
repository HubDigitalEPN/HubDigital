<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarMuestra;

final readonly class ConfirmarMuestraOutput
{
    public function __construct(
        public string $muestraId,
        public string $estadoRevision,
    ) {}
}
