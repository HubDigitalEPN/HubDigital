<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

enum TipoEntidadDepositante: string
{
    case Institucion = 'institucion';
    case Persona = 'persona';

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
