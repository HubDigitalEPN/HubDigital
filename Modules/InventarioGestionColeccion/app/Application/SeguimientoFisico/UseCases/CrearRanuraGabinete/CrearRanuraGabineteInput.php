<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearRanuraGabinete;

final readonly class CrearRanuraGabineteInput
{
    public function __construct(
        public string $gabineteId,
        public int $numeroRanura,
        public ?string $familiaTaxonomicaEsperadaId = null,
    ) {}
}
