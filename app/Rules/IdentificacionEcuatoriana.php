<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Valida formato; no atribuye existencia ni identidad a una consulta local. */
final class IdentificacionEcuatoriana implements ValidationRule
{
    public function __construct(private readonly string $tipo) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $numero = trim((string) $value);
        $valido = match ($this->tipo) {
            'cedula' => self::cedulaValida($numero),
            // El SRI no exige algoritmo para sociedades ni extranjeros sin cédula.
            'ruc' => preg_match('/^[0-9]{10}001$/D', $numero) === 1
                && substr($numero, 0, 10) !== '0000000000',
            'pasaporte' => preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{3,19}$/D', $numero) === 1,
            default => false,
        };
        if (! $valido) {
            $fail(match ($this->tipo) {
                'cedula' => 'La cédula debe tener 10 dígitos y un dígito verificador correcto; se admite el código 30.',
                'ruc' => 'El RUC debe tener 13 dígitos y terminar en 001. Su existencia se comprueba en el SRI.',
                'pasaporte' => 'Ingresa un pasaporte de 4 a 20 caracteres, con letras, números o guiones.',
                default => 'Selecciona un tipo de identificación válido.',
            });
        }
    }

    public static function cedulaValida(string $numero): bool
    {
        if (preg_match('/^[0-9]{10}$/D', $numero) !== 1) return false;
        $codigo = (int) substr($numero, 0, 2);
        if (! (($codigo >= 1 && $codigo <= 24) || $codigo === 30) || (int) $numero[2] > 5) return false;
        $suma = 0;
        for ($i = 0; $i < 9; $i++) {
            $valor = (int) $numero[$i] * ($i % 2 === 0 ? 2 : 1);
            $suma += $valor > 9 ? $valor - 9 : $valor;
        }

        return (10 - $suma % 10) % 10 === (int) $numero[9];
    }
}
