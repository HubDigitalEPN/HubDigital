<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

final readonly class CodigoRfid
{
    /** Formato RC-522: 8 caracteres hexadecimales (ej. A3F7B2C1) */
    private function __construct(private string $value)
    {
        if (! preg_match('/^[0-9A-Fa-f]{8}$/', $value)) {
            throw new \InvalidArgumentException("CodigoRfid inválido: '{$value}'. Se esperan 8 caracteres hexadecimales.");
        }
    }

    public static function desde(string $value): self
    {
        return new self(strtoupper($value));
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
