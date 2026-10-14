<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Services;

final class ReglaCantidadesMaterial
{
    public const MAXIMO = 2147483647;

    public static function esValida(?string $valor): bool
    {
        return $valor !== null && ctype_digit($valor)
            && (float) $valor >= 1 && (float) $valor <= self::MAXIMO;
    }
}
