<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Exceptions;

use Modules\InventarioGestionColeccion\Domain\ValueObjects\UbicacionCajaId;

final class UbicacionYaCerradaException extends \DomainException
{
    public function __construct(UbicacionCajaId $ubicacionId)
    {
        parent::__construct(
            "La ubicación '{$ubicacionId}' ya fue cerrada y no puede cerrarse nuevamente."
        );
    }
}
