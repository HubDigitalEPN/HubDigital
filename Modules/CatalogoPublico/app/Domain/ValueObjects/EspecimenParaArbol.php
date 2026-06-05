<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

final readonly class EspecimenParaArbol
{
    private function __construct(
        public string $occurrenceID,
        public JerarquiaTaxonomica $jerarquia,
        public bool $genusVisible,
        public bool $scientificNameVisible,
    ) {}

    public static function crear(
        string $occurrenceID,
        JerarquiaTaxonomica $jerarquia,
        bool $genusVisible,
        bool $scientificNameVisible,
    ): self {
        if (trim($occurrenceID) === '') {
            throw new \InvalidArgumentException('El occurrenceID del EspecimenParaArbol no puede ser vacío');
        }

        return new self(
            occurrenceID: $occurrenceID,
            jerarquia: $jerarquia,
            genusVisible: $genusVisible,
            scientificNameVisible: $scientificNameVisible,
        );
    }

    public function esDivulgableEnArbol(): bool
    {
        return $this->genusVisible && $this->scientificNameVisible;
    }
}
