<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

enum EstadoSincronizacion: string
{
    case Procesada = 'procesada';
    case ConIncongruencias = 'con_incongruencias';
    case Fallida = 'fallida';

    public function valor(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
