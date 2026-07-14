<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

final readonly class DatosEspecimenParaContexto
{
    /** @param array<string, scalar> $camposVisibles */
    private function __construct(
        public string $occurrenceID,
        public array $camposVisibles,
    ) {}

    /** @param array<string, scalar|null> $camposVisibles */
    public static function crear(string $occurrenceID, array $camposVisibles): self
    {
        if (trim($occurrenceID) === '') {
            throw new \InvalidArgumentException('El occurrenceID no puede ser vacío en el contexto del LLM');
        }

        $sinNulos = array_filter(
            $camposVisibles,
            static fn (mixed $valor): bool => $valor !== null && $valor !== '',
        );

        return new self(occurrenceID: $occurrenceID, camposVisibles: $sinNulos);
    }

    public function tieneCampo(string $campo): bool
    {
        return array_key_exists($campo, $this->camposVisibles);
    }

    public function valor(string $campo): int|float|string|bool|null
    {
        return $this->camposVisibles[$campo] ?? null;
    }
}
