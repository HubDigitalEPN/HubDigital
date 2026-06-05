<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarMuestraParaRevision;

final readonly class MarcarMuestraParaRevisionInput
{
    public function __construct(
        public string $muestraId,
        public string $motivo,
    ) {}
}
