<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Value object inmutable que representa el número legible de una solicitud de
 * depósito, con formato secuencial `MEPN-INV-DEP-00001` (el prefijo DEP la
 * distingue de los códigos de préstamo).
 *
 * Reconstruir desde texto con {@see from()} o derivar desde una secuencia numérica
 * con {@see fromSecuencia()}.
 */
final readonly class NumeroSolicitudDeposito
{
    private const PREFIJO = 'MEPN-INV-DEP-';

    private const LONGITUD_SECUENCIA = 5;

    private function __construct(private string $value) {}

    /**
     * Reconstruye el número desde su representación textual.
     *
     * @throws \DomainException Si el valor no coincide con el formato `MEPN-INV-DEP-00001`.
     */
    public static function from(string $numero): self
    {
        if (! preg_match('/^MEPN-INV-DEP-\d{5}$/', $numero)) {
            throw new \DomainException(
                sprintf('"%s" no es un número de solicitud válido. Formato esperado: MEPN-INV-DEP-00001', $numero)
            );
        }

        return new self($numero);
    }

    /**
     * Construye el número rellenando con ceros la secuencia indicada.
     *
     * @throws \DomainException Si la secuencia es menor que 1.
     */
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
