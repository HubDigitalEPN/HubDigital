<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\ValueObjects;

enum TipoNotificacion: string
{
    case MovimientoNoAutorizado = 'movimiento_no_autorizado';
    case ExtraccionProximaAlLimite = 'extraccion_proxima_al_limite';

    public function valor(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
