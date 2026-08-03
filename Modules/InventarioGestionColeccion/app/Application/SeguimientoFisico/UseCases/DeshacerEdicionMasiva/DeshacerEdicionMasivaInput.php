<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DeshacerEdicionMasiva;

final readonly class DeshacerEdicionMasivaInput
{
    public function __construct(
        public string $edicionId,
    ) {}
}
