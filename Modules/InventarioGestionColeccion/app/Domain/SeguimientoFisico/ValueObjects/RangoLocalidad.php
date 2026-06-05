<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

enum RangoLocalidad: string
{
    case Pais = 'pais';
    case Provincia = 'provincia';
    case AreaProtegida = 'area_protegida';
    case Sector = 'sector';
    case Sitio = 'sitio';

    public static function desdeValorFlexible(string $valor): ?self
    {
        $normalizado = mb_strtolower(trim($valor));

        return match ($normalizado) {
            'pais', 'país', 'country' => self::Pais,
            'provincia', 'state', 'state_province', 'stateprovince' => self::Provincia,
            'area_protegida', 'area protegida', 'protected_area', 'reserva' => self::AreaProtegida,
            'sector' => self::Sector,
            'sitio', 'site', 'locality' => self::Sitio,
            default => self::tryFrom($normalizado),
        };
    }

    /** @return string[] */
    public static function valoresAceptados(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function nivelJerarquico(): int
    {
        return match ($this) {
            self::Pais => 10,
            self::Provincia => 20,
            self::AreaProtegida => 30,
            self::Sector => 40,
            self::Sitio => 50,
        };
    }

    public function puedeSerPadreDe(self $hijo): bool
    {
        return $this->nivelJerarquico() < $hijo->nivelJerarquico();
    }

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
