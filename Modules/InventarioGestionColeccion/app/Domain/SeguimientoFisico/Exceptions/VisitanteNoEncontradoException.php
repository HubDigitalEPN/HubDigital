<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions;

final class VisitanteNoEncontradoException extends \DomainException
{
    public function __construct(string $visitanteId)
    {
        parent::__construct("No existe un visitante con el identificador '{$visitanteId}'.");
    }
}
