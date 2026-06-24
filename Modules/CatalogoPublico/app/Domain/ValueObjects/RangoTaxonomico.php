<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

enum RangoTaxonomico: string
{
    case Phylum = 'phylum';
    case Class_ = 'class';
    case Order = 'order';
    case Family = 'family';
    case Genus = 'genus';
    case Species = 'species';

    public function label(): string
    {
        return $this->value;
    }

    public function orden(): int
    {
        return match ($this) {
            self::Phylum => 1,
            self::Class_ => 2,
            self::Order => 3,
            self::Family => 4,
            self::Genus => 5,
            self::Species => 6,
        };
    }

    public function esSuperiorA(self $otro): bool
    {
        return $this->orden() < $otro->orden();
    }

    /**
     * Niveles que admiten imagen por defecto: únicamente género y especie.
     * Filo/clase/orden/familia quedan excluidos por completo.
     */
    public function admiteImagen(): bool
    {
        return $this === self::Genus || $this === self::Species;
    }

    public static function desde(string $valor): self
    {
        return self::from($valor);
    }

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
