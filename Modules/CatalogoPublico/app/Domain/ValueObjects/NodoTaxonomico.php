<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

final readonly class NodoTaxonomico
{
    private function __construct(
        public RangoTaxonomico $rango,
        public string $taxon,
        public string $nombrePadre,
    ) {}

    public static function crear(RangoTaxonomico $rango, string $taxon, string $nombrePadre): self
    {
        if (trim($taxon) === '') {
            throw new \InvalidArgumentException('El taxon de un NodoTaxonomico no puede ser vacío');
        }

        if (trim($nombrePadre) === '') {
            throw new \InvalidArgumentException('El nombrePadre de un NodoTaxonomico no puede ser vacío');
        }

        return new self(rango: $rango, taxon: $taxon, nombrePadre: $nombrePadre);
    }

    public function esRaiz(): bool
    {
        return $this->nombrePadre === 'root';
    }

    public function clave(): string
    {
        return $this->rango->value.'::'.$this->taxon;
    }

    public function equals(self $otro): bool
    {
        return $this->clave() === $otro->clave() && $this->nombrePadre === $otro->nombrePadre;
    }
}
