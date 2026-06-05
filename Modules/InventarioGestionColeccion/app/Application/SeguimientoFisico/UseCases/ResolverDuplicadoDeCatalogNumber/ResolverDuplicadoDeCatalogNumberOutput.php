<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverDuplicadoDeCatalogNumber;

final readonly class ResolverDuplicadoDeCatalogNumberOutput
{
    public function __construct(
        public string $catalogNumber,
        public string $decisionAplicada,
        public int $especimenesAfectados,
    ) {}
}
