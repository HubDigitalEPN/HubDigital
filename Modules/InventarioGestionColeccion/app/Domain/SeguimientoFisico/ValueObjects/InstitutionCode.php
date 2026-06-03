<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

final readonly class InstitutionCode
{
    private function __construct(public string $value) {}

    public static function desde(string $value): self
    {
        $normalizado = trim($value);

        if ($normalizado === '') {
            throw new \InvalidArgumentException('InstitutionCode no puede estar vacío.');
        }

        if (mb_strlen($normalizado) > 120) {
            throw new \InvalidArgumentException('InstitutionCode no puede exceder 120 caracteres.');
        }

        return new self($normalizado);
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
