<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

final readonly class NumeroSolicitudDeposito
{
    private const PREFIJO = 'MEPN-INV-';

    private const LONGITUD_SECUENCIA = 5;

    private function __construct(private string $value) {}

    public static function from(string $numero): self
    {
        if (! preg_match('/^MEPN-INV-\d{5}$/', $numero)) {
            throw new \DomainException(
                sprintf('"%s" no es un número de solicitud válido. Formato esperado: MEPN-INV-00001', $numero)
            );
        }

        return new self($numero);
    }

    public static function fromSecuencia(int $secuencia): self
    {
        if ($secuencia < 1) {
            throw new \DomainException('La secuencia del número de solicitud debe ser mayor a 0');
        }

        return new self(self::PREFIJO.str_pad((string) $secuencia, self::LONGITUD_SECUENCIA, '0', STR_PAD_LEFT));
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
