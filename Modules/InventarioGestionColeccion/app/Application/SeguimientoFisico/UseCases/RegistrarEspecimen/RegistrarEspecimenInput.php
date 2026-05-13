<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen;

final readonly class RegistrarEspecimenInput
{
    public function __construct(
        public string $codigoCatalogo,
        public string $taxonId,
        public string $localidad,
        public string $fechaColecta,
        public string $colector,
        public ?string $entidadDepositanteId = null,
    ) {}
}
