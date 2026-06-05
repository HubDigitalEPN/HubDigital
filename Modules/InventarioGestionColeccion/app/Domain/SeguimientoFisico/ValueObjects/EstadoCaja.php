<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

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

    /**
     * Estados en los que la caja está físicamente alojada en una ranura del gabinete.
     * EnGabinete es el estado normal; UbicacionIncorrecta y PendienteClasificacion son
     * banderas de negocio sobre una caja que sigue presente en su ranura. Desde cualquiera
     * de los tres, un retiro físico detectado por el ESP32 es válido.
     */
    public function estaAlojadaEnRanura(): bool
    {
        return match ($this) {
            self::EnGabinete, self::UbicacionIncorrecta, self::PendienteClasificacion => true,
            default => false,
        };
    }
}
