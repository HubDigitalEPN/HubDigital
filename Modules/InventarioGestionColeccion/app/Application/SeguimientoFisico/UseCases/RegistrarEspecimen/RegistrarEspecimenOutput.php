<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;

final readonly class RegistrarEspecimenOutput
{
    public function __construct(
        public EspecimenId $id,
        public string $codigoCatalogo,
        public string $taxonId,
        public string $localidad,
        public string $fechaColecta,
        public string $colector,
        public string $estado,
        public ?string $entidadDepositanteId,
    ) {}
}
