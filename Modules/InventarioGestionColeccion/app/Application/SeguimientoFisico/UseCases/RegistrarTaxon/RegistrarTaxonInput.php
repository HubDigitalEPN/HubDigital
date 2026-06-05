<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarTaxon;

final readonly class RegistrarTaxonInput
{
    public function __construct(
        public string $nombreCientifico,
        public string $rango,
        public ?string $autor = null,
        public ?int $anioDescripcion = null,
        public ?string $padreId = null,
        public ?string $epitetoInfraespecifico = null,
    ) {}
}
