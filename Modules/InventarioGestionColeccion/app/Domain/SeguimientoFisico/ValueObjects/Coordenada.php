<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

final readonly class Coordenada
{
    private function __construct(
        public float $latitud,
        public float $longitud,
    ) {}

    public static function desde(float $latitud, float $longitud): self
    {
        if ($latitud < -90.0 || $latitud > 90.0) {
            throw new \InvalidArgumentException(
                "Latitud fuera de rango: {$latitud}. Debe estar entre -90 y 90."
            );
        }

        if ($longitud < -180.0 || $longitud > 180.0) {
            throw new \InvalidArgumentException(
                "Longitud fuera de rango: {$longitud}. Debe estar entre -180 y 180."
            );
        }

        return new self($latitud, $longitud);
    }

    public function equals(self $other): bool
    {
        return $this->latitud === $other->latitud && $this->longitud === $other->longitud;
    }

    public function __toString(): string
    {
        return "{$this->latitud}, {$this->longitud}";
    }
}
