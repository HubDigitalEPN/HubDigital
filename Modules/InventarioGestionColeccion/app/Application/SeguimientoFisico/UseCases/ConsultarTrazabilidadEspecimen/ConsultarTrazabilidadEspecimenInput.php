<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarTrazabilidadEspecimen;

/**
 * DTO de entrada con el espécimen cuya trazabilidad de movimientos se quiere consultar.
 */
final readonly class ConsultarTrazabilidadEspecimenInput
{
    public function __construct(
        public string $especimenId,
    ) {}
}
