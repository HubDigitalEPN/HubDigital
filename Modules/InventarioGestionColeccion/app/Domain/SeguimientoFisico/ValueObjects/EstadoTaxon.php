<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

enum EstadoTaxon: string
{
    case Activo = 'activo';
    case Inactivo = 'inactivo';

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
