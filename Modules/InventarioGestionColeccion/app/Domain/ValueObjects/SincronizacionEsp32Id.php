<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\ValueObjects;

final readonly class SincronizacionEsp32Id
{
    private function __construct(private string $value)
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
            throw new \InvalidArgumentException("SincronizacionEsp32Id inválido: '{$value}'");
        }
    }

    public static function generar(): self
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return new self(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4)));
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
