<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions;

final class NombreTaxonDuplicadoException extends \DomainException
{
    public function __construct(string $nombre, string $rango)
    {
        parent::__construct(
            "Nombre duplicado: ya existe un taxón con nombre científico '{$nombre}' en el rango '{$rango}'."
        );
    }
}
