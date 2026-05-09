<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarRanura;

final readonly class ActualizarRanuraInput
{
    public function __construct(
        public string $ranuraId,
        public ?string $familiaTaxonomicaEsperadaId,
        public bool $activa,
    ) {}
}
