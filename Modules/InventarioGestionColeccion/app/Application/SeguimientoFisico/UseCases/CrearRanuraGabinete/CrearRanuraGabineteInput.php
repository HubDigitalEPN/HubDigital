<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearRanuraGabinete;

/**
 * DTO de entrada con el gabinete destino, el número de la ranura y su familia esperada opcional.
 */
final readonly class CrearRanuraGabineteInput
{
    public function __construct(
        public string $gabineteId,
        public int $numeroRanura,
        public ?string $familiaTaxonomicaEsperadaId = null,
    ) {}
}
