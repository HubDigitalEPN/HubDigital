<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

final readonly class NodoEspecie
{
    private function __construct(
        public string $especie,
        public string $genus,
        public string $specificEpithet,
        public string $padreGenus,
    ) {}

    public static function desdeJerarquia(JerarquiaTaxonomica $jerarquia): self
    {
        return new self(
            especie: $jerarquia->genus.' '.$jerarquia->specificEpithet,
            genus: $jerarquia->genus,
            specificEpithet: $jerarquia->specificEpithet,
            padreGenus: $jerarquia->genus,
        );
    }

    public function equals(self $otro): bool
    {
        return $this->especie === $otro->especie && $this->padreGenus === $otro->padreGenus;
    }
}
