<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

enum TipoAlerta: string
{
    case IncongruenciaTaxonomica = 'incongruencia_taxonomica';
    case MovimientoNoAutorizado = 'movimiento_no_autorizado';
    case ExtraccionProlongada = 'extraccion_prolongada';
    case FamiliaNoAsignada = 'familia_no_asignada';

    public function valor(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
