<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services;

/**
 * Servicio de dominio para parsear fechas en formato verbatim del Excel.
 *
 * Soporta:
 *  - dd-MMM-yy / dd-MMM-yyyy (separadores `-`, `/`, `.`, espacio)
 *  - Meses en español (con o sin tilde), abreviados o completos.
 *  - Aliases en inglés para tolerar mezclas (`Jan`, `Apr`, etc.).
 *  - ISO completo (YYYY-MM-DD) como camino feliz.
 *
 * Política de siglo (decisión P0.1):
 *  Si el año viene en 2 dígitos: si `YY > pivot_year_actual_2d` → `19YY`,
 *  en caso contrario → `20YY`. El pivot por defecto es el año actual a 2 dígitos.
 *
 * Devuelve `null` cuando el formato no se reconoce o la fecha es inválida.
 * NO lanza excepciones — el parsing es best-effort.
 */
final class ParsearFechaVerbatim
{
    /** @var array<string, int> */
    private const MESES = [
        // Español abreviado
        'ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'ago' => 8, 'sep' => 9, 'set' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12,
        // Español completo
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
        'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9,
        'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
        // Inglés abreviado (común en Excel mixto)
        'jan' => 1, 'apr' => 4, 'aug' => 8, 'dec' => 12,
    ];

    public static function parsear(string $verbatim, ?int $pivotYearDosDigitos = null): ?\DateTimeImmutable
    {
        $verbatim = trim($verbatim);
        if ($verbatim === '') {
            return null;
        }

        $pivotYearDosDigitos ??= (int) date('y');

        // Camino feliz: ISO YYYY-MM-DD.
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $verbatim, $m)) {
            return self::construir((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // Formato dd<sep>mes<sep>yy(yy) con separadores -, /, ., espacio.
        if (preg_match('/^(\d{1,2})[\/\-.\s]+(\p{L}+)[\/\-.\s]+(\d{2,4})$/u', $verbatim, $m)) {
            $dia = (int) $m[1];
            $mesTexto = self::normalizarMes($m[2]);
            $anioStr = $m[3];

            $mes = self::MESES[$mesTexto] ?? null;
            if ($mes === null) {
                return null;
            }

            $anio = (int) $anioStr;
            if (strlen($anioStr) === 2) {
                $anio = $anio > $pivotYearDosDigitos ? 1900 + $anio : 2000 + $anio;
            }

            return self::construir($anio, $mes, $dia);
        }

        // Formato dd<sep>mm<sep>yy(yy) numérico también — para verbatims tipo "21/07/01".
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})$/', $verbatim, $m)) {
            $dia = (int) $m[1];
            $mes = (int) $m[2];
            $anio = (int) $m[3];
            if (strlen($m[3]) === 2) {
                $anio = $anio > $pivotYearDosDigitos ? 1900 + $anio : 2000 + $anio;
            }

            return self::construir($anio, $mes, $dia);
        }

        return null;
    }

    private static function normalizarMes(string $mesTexto): string
    {
        $normalizado = mb_strtolower($mesTexto);

        return strtr($normalizado, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);
    }

    private static function construir(int $anio, int $mes, int $dia): ?\DateTimeImmutable
    {
        if (! checkdate($mes, $dia, $anio)) {
            return null;
        }

        try {
            return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $anio, $mes, $dia));
        } catch (\Exception) {
            return null;
        }
    }
}
