<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions;

final class EspecimenNoEncontradoException extends \DomainException
{
    public function __construct(?string $referencia = null)
    {
        $detalle = $referencia !== null ? " (referencia: '{$referencia}')" : '';

        parent::__construct(
            "No se encontró ningún espécimen{$detalle}."
        );
    }
}
