<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions;

final class NombreEntidadDuplicadoException extends \DomainException
{
    public function __construct(string $nombre)
    {
        parent::__construct(
            "Nombre duplicado: ya existe una entidad depositante con el nombre '{$nombre}'."
        );
    }
}
