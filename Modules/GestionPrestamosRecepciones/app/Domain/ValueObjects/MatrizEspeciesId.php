<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

use Ramsey\Uuid\Uuid;

/**
 * Identificador de valor (UUID) de una {@see \Modules\GestionPrestamosRecepciones\Domain\Entities\MatrizEspecies}.
 * Inmutable; crear con {@see generate()} o reconstruir con {@see from()}.
 */
final readonly class MatrizEspeciesId
{
    private function __construct(private string $value) {}

    public static function from(string $uuid): self
    {
        if (! Uuid::isValid($uuid)) {
            throw new \DomainException(
                sprintf('"%s" no es un UUID válido para MatrizEspeciesId', $uuid)
            );
        }

        return new self($uuid);
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
