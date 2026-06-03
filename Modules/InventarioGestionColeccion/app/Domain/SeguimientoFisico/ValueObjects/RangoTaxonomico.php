<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

/**
 * Jerarquía taxonómica de Hub Digital.
 *
 * Cadena padre_id típica:
 *   phylum → class → order → suborder → family → subfamily → tribe → genus → species
 *
 * Notas:
 *  - `morfoespecie` queda al mismo nivel que `especie` (90): es una variante para
 *    grupos provisionales sin determinación específica firme. Es hijo válido de genus.
 *  - `subespecie` (100) cuelga debajo de species.
 *  - `clade` NO es un rango válido (Darwin Core no lo reconoce). Si llega del Excel,
 *    el importador debe descartarlo o enviarlo a `especimen.taxonomic_notes`.
 *  - `infraspecificEpithet` NO es un rango propio sino un atributo del taxón species
 *    (columna `taxones.epiteto_infraespecifico`).
 */
enum RangoTaxonomico: string
{
    case Especie = 'especie';
    case Genero = 'genero';
    case Familia = 'familia';
    case Orden = 'orden';
    case Suborden = 'suborden';
    case Clase = 'clase';
    case Phylum = 'phylum';
    case Reino = 'reino';
    case Subespecie = 'subespecie';
    case Tribu = 'tribu';
    case Subfamilia = 'subfamilia';
    case Morfoespecie = 'morfoespecie';

    public static function desdeValorFlexible(string $valor): ?self
    {
        $normalizado = mb_strtolower(trim($valor));

        return match ($normalizado) {
            'kingdom', 'reino' => self::Reino,
            'phylum', 'filo', 'division' => self::Phylum,
            'class', 'clase' => self::Clase,
            'order', 'orden' => self::Orden,
            'suborder', 'suborden' => self::Suborden,
            'family', 'familia' => self::Familia,
            'subfamily', 'subfamilia' => self::Subfamilia,
            'tribe', 'tribu' => self::Tribu,
            'genus', 'genero', 'género' => self::Genero,
            'species', 'specie', 'especie' => self::Especie,
            'subspecies', 'subespecie' => self::Subespecie,
            'morphospecies', 'morphospecie', 'morfoespecie' => self::Morfoespecie,
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
            'suborder',
            'family',
            'subfamily',
            'tribe',
            'genus',
            'species',
            'subspecies',
            'morphospecies',
        ]));
    }

    public function nivelJerarquico(): int
    {
        return match ($this) {
            self::Reino => 10,
            self::Phylum => 20,
            self::Clase => 30,
            self::Orden => 40,
            self::Suborden => 45,
            self::Familia => 50,
            self::Subfamilia => 60,
            self::Tribu => 70,
            self::Genero => 80,
            self::Especie => 90,
            self::Morfoespecie => 90,
            self::Subespecie => 100,
        };
    }

    public function puedeSerPadreDe(self $hijo): bool
    {
        return $this->nivelJerarquico() < $hijo->nivelJerarquico();
    }

    public function admiteEpitetoInfraespecifico(): bool
    {
        return $this === self::Especie;
    }

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
