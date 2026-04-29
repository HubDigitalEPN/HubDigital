<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\ValueObjects;

enum EstadoAlerta: string
{
    case Activa = 'activa';
    case Resuelta = 'resuelta';
    case Ignorada = 'ignorada';

    public function valor(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
