<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

final readonly class EstadisticaTaxon
{
    private function __construct(
        public RangoTaxonomico $rango,
        public string $nombre,
        public int $cantidadRegistros,
    ) {}

    public static function crear(RangoTaxonomico $rango, string $nombre, int $cantidadRegistros): self
    {
        if (trim($nombre) === '') {
            throw new \InvalidArgumentException('El nombre del taxón no puede ser vacío');
        }

        if ($cantidadRegistros < 0) {
            throw new \InvalidArgumentException('La cantidad de registros no puede ser negativa');
        }

        return new self(rango: $rango, nombre: $nombre, cantidadRegistros: $cantidadRegistros);
    }
}
