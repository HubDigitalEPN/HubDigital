<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearRanuraGabinete;

final readonly class CrearRanuraGabineteOutput
{
    public function __construct(
        public string $id,
        public string $gabineteId,
        public int $numeroRanura,
        public ?string $familiaTaxonomicaEsperadaId,
        public bool $activa,
    ) {}
}
