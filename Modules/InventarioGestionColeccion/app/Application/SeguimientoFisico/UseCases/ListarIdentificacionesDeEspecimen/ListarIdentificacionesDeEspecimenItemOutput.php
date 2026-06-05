<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarIdentificacionesDeEspecimen;

final readonly class ListarIdentificacionesDeEspecimenItemOutput
{
    public function __construct(
        public string $id,
        public ?string $taxonId,
        public ?string $taxonNombre,
        public ?string $identificadoPor,
        public ?string $fechaDeterminacion,
        public ?string $calificador,
        public ?string $observaciones,
        public bool $esActual,
    ) {}
}
