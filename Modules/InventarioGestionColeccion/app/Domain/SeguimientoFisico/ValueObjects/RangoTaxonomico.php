<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

enum RangoTaxonomico: string
{
    case Especie = 'especie';
    case Genero = 'genero';
    case Familia = 'familia';
    case Orden = 'orden';
    case Clase = 'clase';
    case Phylum = 'phylum';
    case Reino = 'reino';
    case Subespecie = 'subespecie';
    case Tribu = 'tribu';
    case Subfamilia = 'subfamilia';

    public static function desdeValorFlexible(string $valor): ?self
    {
        $normalizado = mb_strtolower(trim($valor));

        return match ($normalizado) {
            'kingdom', 'reino' => self::Reino,
            'phylum', 'filo', 'division' => self::Phylum,
            'class', 'clase' => self::Clase,
            'order', 'orden' => self::Orden,
            'family', 'familia' => self::Familia,
            'subfamily', 'subfamilia' => self::Subfamilia,
            'tribe', 'tribu' => self::Tribu,
            'genus', 'genero', 'género' => self::Genero,
            'species', 'specie', 'especie' => self::Especie,
            'subspecies', 'subespecie' => self::Subespecie,
            default => self::tryFrom($normalizado),
        };
    }

    /** @return string[] */
    public static function valoresAceptados(): array
    {
        return array_values(array_unique([
            ...array_column(self::cases(), 'value'),
            'kingdom',
            'phylum',
            'class',
            'order',
            'family',
            'subfamily',
            'tribe',
            'genus',
            'species',
            'subspecies',
        ]));
    }

    public function nivelJerarquico(): int
    {
        return match ($this) {
            self::Reino => 10,
            self::Phylum => 20,
            self::Clase => 30,
            self::Orden => 40,
            self::Familia => 50,
            self::Subfamilia => 60,
            self::Tribu => 70,
            self::Genero => 80,
            self::Especie => 90,
            self::Subespecie => 100,
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
