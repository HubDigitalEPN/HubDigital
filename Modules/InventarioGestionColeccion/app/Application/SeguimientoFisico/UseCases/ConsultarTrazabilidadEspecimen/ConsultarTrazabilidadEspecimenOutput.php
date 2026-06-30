<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarTrazabilidadEspecimen;

/**
 * DTO de salida con los movimientos del espécimen y de sus contenedores, en orden cronológico.
 */
final readonly class ConsultarTrazabilidadEspecimenOutput
{
    /** @param MovimientoTrazabilidadOutput[] $movimientos */
    public function __construct(
        public array $movimientos,
    ) {}
}
