<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

final readonly class DatosIntegradosDocumento
{
    public function __construct(
        public readonly ?string $nroPermisoRecoleccion,
        public readonly ?string $nroPermisoMovilizacion,
        public readonly ?string $grupoAnimal,
        public readonly ?string $provinciaOrigen,
        public readonly ?string $localidad,
        public readonly ?string $origenDonacion,
        public readonly ?string $nombreInvestigador = null,
    ) {}
}
