<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarMuestraColecta;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\MuestraColectaId;

final readonly class RegistrarMuestraColectaOutput
{
    public function __construct(
        public MuestraColectaId $id,
        public ?string $codigoMuestra,
        public ?string $fechaColecta,
        public ?string $localidadId,
        public ?string $colector,
        public string $estadoRevision,
        public ?string $motivoRevision,
    ) {}
}
