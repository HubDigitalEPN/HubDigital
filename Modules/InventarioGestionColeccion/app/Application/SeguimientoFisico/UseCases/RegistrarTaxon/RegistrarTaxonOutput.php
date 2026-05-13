<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarTaxon;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

final readonly class RegistrarTaxonOutput
{
    public function __construct(
        public TaxonId $id,
        public string $nombreCientifico,
        public string $rango,
        public string $autor,
        public int $anioDescripcion,
        public string $estado,
    ) {}
}
