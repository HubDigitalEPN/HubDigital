<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;

final class RanuraYaOcupadaException extends \DomainException
{
    public function __construct(RanuraId $ranuraId, CajaId $cajaOcupante)
    {
        parent::__construct(
            "La ranura '{$ranuraId}' ya está ocupada por la caja '{$cajaOcupante}'."
        );
    }
}
