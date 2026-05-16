<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions;

final class CodigoCatalogoDuplicadoException extends \DomainException
{
    public function __construct(string $codigo)
    {
        parent::__construct(
            "Código de catálogo duplicado: ya existe un especímen con código '{$codigo}'."
        );
    }
}
