<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions;

final class GuidEspecimenDuplicadoException extends \DomainException
{
    public function __construct(string $guid)
    {
        parent::__construct(
            "GUID duplicado: ya existe un espécimen con el GUID '{$guid}'."
        );
    }
}
