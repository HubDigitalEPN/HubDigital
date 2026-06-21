<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarRetiroCaja;

/**
 * DTO de entrada con la caja que se retira (y, opcionalmente, la ranura de la que sale).
 */
final readonly class RegistrarRetiroCajaInput
{
    public function __construct(
        public string $cajaId,
        public ?string $ranuraId = null,
    ) {}
}
