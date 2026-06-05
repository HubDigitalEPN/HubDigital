<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IdentificarEspecimen;

final readonly class IdentificarEspecimenInput
{
    public function __construct(
        public string $especimenId,
        public ?string $taxonId = null,
        public ?string $identificadoPor = null,
        public ?string $fechaDeterminacion = null,
        public ?string $calificador = null,
        public ?string $observaciones = null,
    ) {}
}
