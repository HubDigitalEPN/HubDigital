<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarIngresoCaja;

/**
 * DTO de entrada con la caja que ingresa y la ranura destino.
 */
final readonly class RegistrarIngresoCajaInput
{
    public function __construct(
        public string $cajaId,
        public string $ranuraId,
    ) {}
}
