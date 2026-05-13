<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarTaxon;

final readonly class RegistrarTaxonInput
{
    public function __construct(
        public string $nombreCientifico,
        public string $rango,
        public string $autor,
        public int $anioDescripcion,
        public ?string $padreId = null,
    ) {}
}
