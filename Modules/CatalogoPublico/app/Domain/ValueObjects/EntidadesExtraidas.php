<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

final readonly class EntidadesExtraidas
{
    private function __construct(
        public ?string $genero,
        public ?string $especie,
        public ?string $occurrenceID,
        public ?string $localidad,
        public ?string $country,
    ) {}

    public static function vacio(): self
    {
        return new self(
            genero: null,
            especie: null,
            occurrenceID: null,
            localidad: null,
            country: null,
        );
    }

    public static function desde(
        ?string $genero = null,
        ?string $especie = null,
        ?string $occurrenceID = null,
        ?string $localidad = null,
        ?string $country = null,
    ): self {
        return new self(
            genero: self::normalizar($genero),
            especie: self::normalizar($especie),
            occurrenceID: self::normalizar($occurrenceID),
            localidad: self::normalizar($localidad),
            country: self::normalizar($country),
        );
    }

    public function estaVacio(): bool
    {
        return $this->genero === null
            && $this->especie === null
            && $this->occurrenceID === null
            && $this->localidad === null
            && $this->country === null;
    }

    private static function normalizar(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $limpio = trim($valor);

        return $limpio === '' ? null : $limpio;
    }
}
