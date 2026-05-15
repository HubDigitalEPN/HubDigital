<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

enum ActorRol: string
{
    case Sistema = 'sistema';
    case Esp32 = 'esp32';
    case Curador = 'curador';

    public function valor(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
