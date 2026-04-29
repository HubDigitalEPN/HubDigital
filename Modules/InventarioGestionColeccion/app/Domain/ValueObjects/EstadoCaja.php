<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\ValueObjects;

enum EstadoCaja: string
{
    case EnGabinete = 'en_gabinete';
    case EnTransito = 'en_transito';
    case UbicacionIncorrecta = 'ubicacion_incorrecta';
    case PendienteClasificacion = 'pendiente_clasificacion';
    case ExtraccionProlongada = 'extraccion_prolongada';
    case Extraviada = 'extraviada';

    public function valor(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
