<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarMuestraColecta;

final readonly class RegistrarMuestraColectaInput
{
    public function __construct(
        public ?string $codigoMuestra = null,
        public ?string $fechaColecta = null,
        public ?string $fechaVerbatim = null,
        public ?string $localidadId = null,
        public ?string $localidadVerbatim = null,
        public ?string $colector = null,
        public ?string $samplingProtocol = null,
        public ?string $motivoRevision = null,
    ) {}
}
