<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

/** Solo los campos declarados numéricos pueden evitar la protección de texto. */
final class NumeroExportacion
{
    public static function decimal(mixed $valor, ?float $minimo = null, ?float $maximo = null, bool $entero = false): ?string
    {
        if (! is_int($valor) && ! is_float($valor) && ! is_string($valor)) return null;
        $texto = trim((string) $valor);
        $patron = $entero ? '/\A\+?\d+\z/D' : '/\A[+-]?(?:\d+(?:\.\d+)?|\.\d+)(?:[eE][+-]?\d+)?\z/D';
        if (! preg_match($patron, $texto)) return null;
        $numero = (float) $texto;
        if (! is_finite($numero) || ($minimo !== null && $numero < $minimo) || ($maximo !== null && $numero > $maximo)) return null;

        // Conservar los dígitos de la fuente; el float se usa solo para acotar.
        return ltrim($texto, '+');
    }
}
