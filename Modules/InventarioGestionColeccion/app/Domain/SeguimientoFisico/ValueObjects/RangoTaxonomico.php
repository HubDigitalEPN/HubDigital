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

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
