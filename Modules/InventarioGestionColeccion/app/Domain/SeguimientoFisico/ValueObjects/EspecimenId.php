<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

final readonly class EspecimenId
{
    private const PATRON = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    private function __construct(private string $value)
    {
        if (! preg_match(self::PATRON, $value)) {
            throw new \InvalidArgumentException("EspecimenId inválido: '{$value}'");
        }
    }

    /** Indica si una cadena tiene el formato de un EspecimenId válido, sin construirlo ni lanzar. */
    public static function esValido(string $value): bool
    {
        return preg_match(self::PATRON, $value) === 1;
    }

    public static function generar(): self
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0F | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3F | 0x80);

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
