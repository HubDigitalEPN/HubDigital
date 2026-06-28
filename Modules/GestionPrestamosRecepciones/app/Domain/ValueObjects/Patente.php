<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Código de patente del laboratorio ante el MAATE (ej. MAATE-MCMEVS-2023-276).
 * Se renueva cada año; este VO solo normaliza y valida que no esté vacío.
 */
final readonly class Patente
{
    private function __construct(private string $value) {}

    public static function desde(string $value): self
    {
        $normalizado = trim($value);

        // ponytail: validación laxa (solo no-vacío). Endurecer a regex MAATE-... si se exige formato estricto.
        if ($normalizado === '') {
            throw new InvalidArgumentException('La patente no puede estar vacía.');
        }

        return new self($normalizado);
    }

    public function valor(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
