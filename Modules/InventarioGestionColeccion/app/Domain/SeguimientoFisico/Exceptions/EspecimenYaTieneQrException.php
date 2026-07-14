<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions;

final class EspecimenYaTieneQrException extends \DomainException
{
    public function __construct(string $especimenId)
    {
        parent::__construct(
            "El espécimen '{$especimenId}' ya tiene un código QR generado."
        );
    }
}
