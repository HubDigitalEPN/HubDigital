<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions;

final class EntidadSinEspecimenesException extends \DomainException
{
    public function __construct(string $entidadId)
    {
        parent::__construct(
            "No es posible generar el acta: la entidad '{$entidadId}' no tiene especímenes vinculados."
        );
    }
}
