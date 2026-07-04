<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarTaxonesPorNombre;

final readonly class BuscarTaxonesPorNombreInput
{
    public function __construct(
        public string $consulta,
        public int $limite = 20,
    ) {}
}
