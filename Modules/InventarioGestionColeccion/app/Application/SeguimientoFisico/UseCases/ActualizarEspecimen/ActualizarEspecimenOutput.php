<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimen;

final readonly class ActualizarEspecimenOutput
{
    public function __construct(
        public string $especimenId,
        public string $codigoCatalogo,
        public string $localidad,
        public string $fechaColecta,
        public string $colector,
        public ?string $entidadDepositanteId,
    ) {}
}
