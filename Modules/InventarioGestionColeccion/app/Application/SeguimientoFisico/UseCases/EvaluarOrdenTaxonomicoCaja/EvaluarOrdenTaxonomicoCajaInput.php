<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EvaluarOrdenTaxonomicoCaja;

/**
 * DTO de entrada con la caja a evaluar y la ranura que ocupa dentro del gabinete.
 */
final readonly class EvaluarOrdenTaxonomicoCajaInput
{
    public function __construct(
        public string $cajaId,
        public string $ranuraId,
    ) {}
}
