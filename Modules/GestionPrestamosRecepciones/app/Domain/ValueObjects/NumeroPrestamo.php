<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class NumeroPrestamo
{
    private const PREFIX = 'act_';

    private const CHARSET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    private const SUFFIX_LENGTH = 6;

    private const PATTERN = '/^act_[A-Z0-9]{6}$/';

    private function __construct(private string $value) {}

    public static function generate(): self
    {
        $suffix = '';
        $charset = self::CHARSET;
        $max = strlen($charset) - 1;

        for ($i = 0; $i < self::SUFFIX_LENGTH; $i++) {
            $suffix .= $charset[random_int(0, $max)];
        }

        return new self(self::PREFIX.$suffix);
    }

    public static function fromString(string $value): self
    {
        if (! preg_match(self::PATTERN, $value)) {
            throw new InvalidArgumentException(
                "Formato inválido para NumeroPrestamo: '{$value}'. Se esperaba 'act_' seguido de 6 caracteres alfanuméricos en mayúsculas."
            );
        }

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
