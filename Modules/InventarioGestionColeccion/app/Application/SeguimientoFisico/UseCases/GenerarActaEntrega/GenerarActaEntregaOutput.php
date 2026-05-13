<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarActaEntrega;

final readonly class GenerarActaEntregaOutput
{
    public function __construct(
        public string $entidadId,
        public string $entidadNombre,
        public int $totalEspecimenes,
        public string $pdfRuta,
    ) {}
}
