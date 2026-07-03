<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverDuplicadoDeCatalogNumber;

final readonly class ResolverDuplicadoDeCatalogNumberInput
{
    public const DECISION_EVENTOS_DISTINTOS = 'eventos_distintos';

    public const DECISION_ERROR_CATALOGACION = 'error_catalogacion';

    /**
     * @param  string[]|null  $especimenIds  Si se provee (no vacío), la decisión
     *                                       se aplica SOLO a esos especímenes; si
     *                                       es null, a todo el grupo del catalog_number.
     */
    public function __construct(
        public string $catalogNumber,
        public string $decision,
        public string $motivo,
        public ?array $especimenIds = null,
    ) {}
}
