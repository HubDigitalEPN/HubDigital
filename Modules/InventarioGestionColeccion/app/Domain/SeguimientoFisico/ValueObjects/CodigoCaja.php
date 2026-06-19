<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

final readonly class CodigoCaja
{
    private function __construct(private string $value)
    {
        if (trim($value) === '' || mb_strlen($value) > 100) {
            throw new \InvalidArgumentException('CodigoCaja inválido: debe ser no vacío y máximo 100 caracteres.');
        }
    }

    public static function desde(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
