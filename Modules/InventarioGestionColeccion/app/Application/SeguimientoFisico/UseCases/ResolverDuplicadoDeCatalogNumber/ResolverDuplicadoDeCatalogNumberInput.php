<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverDuplicadoDeCatalogNumber;

final readonly class ResolverDuplicadoDeCatalogNumberInput
{
    public const DECISION_EVENTOS_DISTINTOS = 'eventos_distintos';

    public const DECISION_ERROR_CATALOGACION = 'error_catalogacion';

    public function __construct(
        public string $catalogNumber,
        public string $decision,
        public string $motivo,
    ) {}
}
