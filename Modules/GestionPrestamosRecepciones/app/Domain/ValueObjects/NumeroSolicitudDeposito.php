<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

final readonly class NumeroSolicitudDeposito
{
    private const PREFIJO = 'sol_';

    private const LONGITUD_SUFIJO = 6;

    private const CARACTERES = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    private function __construct(private string $value) {}

    public static function from(string $numero): self
    {
        if (! preg_match('/^sol_[A-Z0-9]{6}$/', $numero)) {
            throw new \DomainException(
                sprintf('"%s" no es un número de solicitud válido. Formato esperado: sol_XXXXXX', $numero)
            );
        }

        return new self($numero);
    }

    public static function generate(): self
    {
        $sufijo = '';
        $max = strlen(self::CARACTERES) - 1;

        for ($i = 0; $i < self::LONGITUD_SUFIJO; $i++) {
            $sufijo .= self::CARACTERES[random_int(0, $max)];
        }

        return new self(self::PREFIJO.$sufijo);
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
