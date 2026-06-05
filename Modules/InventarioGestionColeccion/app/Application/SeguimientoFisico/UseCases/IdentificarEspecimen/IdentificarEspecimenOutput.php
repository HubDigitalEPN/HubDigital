<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IdentificarEspecimen;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\IdentificacionId;

final readonly class IdentificarEspecimenOutput
{
    public function __construct(
        public IdentificacionId $id,
        public string $especimenId,
        public ?string $taxonId,
        public ?string $identificadoPor,
        public ?string $fechaDeterminacion,
        public ?string $calificador,
        public ?string $observaciones,
        public bool $esActual,
    ) {}
}
