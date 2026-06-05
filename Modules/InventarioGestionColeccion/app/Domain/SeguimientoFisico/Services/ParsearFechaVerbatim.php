<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services;

/**
 * Servicio de dominio para parsear fechas en formato verbatim del Excel.
 *
 * `parsear()` devuelve UNA fecha (la primera que reconoce); útil para casos
 * donde sólo importa la inicial. `parsearRango()` devuelve `[inicio, fin]`
 * cubriendo los formatos de rango comunes en colecciones entomológicas:
 *
 *  - dd-dd/mes/yyyy           ej. "14-26/feb/2001"  → [14-feb-2001, 26-feb-2001]
 *  - dd-mes/dd-mes/yyyy       ej. "30-jun/02-jul/2005" → [30-jun-2005, 02-jul-2005]
 *  - mes-mes/yyyy             ej. "Jun-Jul-1998"    → [01-jun-1998, 31-jul-1998]
 *  - dd-mes-yyyy / dd-mes-yyyy   rango con dos fechas completas
 *
 * Política de siglo (decisión P0.1): por defecto el pivot es el año actual a
 * 2 dígitos; el importador pasa `pivotYearDosDigitos: 25` explícito para que
 * cualquier yy ≤ 25 se interprete como 20xx y >25 como 19xx.
 *
 * Devuelve `null` o `[null, null]` cuando el formato no se reconoce.
 * Nunca lanza; el parsing es best-effort.
 */
final class ParsearFechaVerbatim
{
    /** @var array<string, int> */
    private const MESES = [
        'ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'ago' => 8, 'sep' => 9, 'set' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12,
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
        'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9,
        'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
        'jan' => 1, 'apr' => 4, 'aug' => 8, 'dec' => 12,
    ];

    public static function parsear(string $verbatim, ?int $pivotYearDosDigitos = null): ?\DateTimeImmutable
    {
        [$inicio] = self::parsearRango($verbatim, $pivotYearDosDigitos);

        return $inicio;
    }

    /**
     * Devuelve `[inicio, fin]`. Si el verbatim es una fecha única, `fin` es null.
     *
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable}
     */
    public static function parsearRango(string $verbatim, ?int $pivotYearDosDigitos = null): array
    {
        $verbatim = trim($verbatim);
        if ($verbatim === '') {
            return [null, null];
        }

        $pivotYearDosDigitos ??= (int) date('y');

        // ISO completo YYYY-MM-DD (camino feliz).
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $verbatim, $m)) {
            return [self::construir((int) $m[1], (int) $m[2], (int) $m[3]), null];
        }

        // RANGO 1: dd-dd/mes/yyyy  ej. "14-26/feb/2001"
        if (preg_match('/^(\d{1,2})\s*[-–]\s*(\d{1,2})[\/\-\s.]+(\p{L}+)[\/\-\s.]+(\d{2,4})$/u', $verbatim, $m)) {
            $mes = self::MESES[self::normalizarMes($m[3])] ?? null;
            if ($mes === null) {
                return [null, null];
            }
            $anio = self::resolverAnio($m[4], $pivotYearDosDigitos);
            $inicio = self::construir($anio, $mes, (int) $m[1]);
            $fin = self::construir($anio, $mes, (int) $m[2]);

            return $inicio !== null && $fin !== null ? [$inicio, $fin] : [null, null];
        }

        // RANGO 2: dd-mes/dd-mes/yyyy  ej. "30-jun/02-jul/2005"
        if (preg_match('/^(\d{1,2})[\/\-\s.]+(\p{L}+)[\/\-\s]+(\d{1,2})[\/\-\s.]+(\p{L}+)[\/\-\s.]+(\d{2,4})$/u', $verbatim, $m)) {
            $mes1 = self::MESES[self::normalizarMes($m[2])] ?? null;
            $mes2 = self::MESES[self::normalizarMes($m[4])] ?? null;
            if ($mes1 === null || $mes2 === null) {
                return [null, null];
            }
            $anio = self::resolverAnio($m[5], $pivotYearDosDigitos);
            $inicio = self::construir($anio, $mes1, (int) $m[1]);
            $fin = self::construir($anio, $mes2, (int) $m[3]);

            return $inicio !== null && $fin !== null ? [$inicio, $fin] : [null, null];
        }

        // RANGO 3: mes-mes/yyyy  ej. "Jun-Jul-1998"  → [01-jun-1998, último-jul-1998]
        if (preg_match('/^(\p{L}+)\s*[-–\/]\s*(\p{L}+)[\/\-\s.]+(\d{2,4})$/u', $verbatim, $m)) {
            $mes1 = self::MESES[self::normalizarMes($m[1])] ?? null;
            $mes2 = self::MESES[self::normalizarMes($m[2])] ?? null;
            if ($mes1 === null || $mes2 === null) {
                return [null, null];
            }
            $anio = self::resolverAnio($m[3], $pivotYearDosDigitos);
            $inicio = self::construir($anio, $mes1, 1);
            $fin = self::construir($anio, $mes2, self::ultimoDiaDelMes($anio, $mes2));

            return $inicio !== null && $fin !== null ? [$inicio, $fin] : [null, null];
        }

        // FECHA SIMPLE 1: dd<sep>mes<sep>yy(yy)  ej. "21-Jul-01"
        if (preg_match('/^(\d{1,2})[\/\-\s.]+(\p{L}+)[\/\-\s.]+(\d{2,4})$/u', $verbatim, $m)) {
            $mes = self::MESES[self::normalizarMes($m[2])] ?? null;
            if ($mes === null) {
                return [null, null];
            }
            $anio = self::resolverAnio($m[3], $pivotYearDosDigitos);

            return [self::construir($anio, $mes, (int) $m[1]), null];
        }

        // FECHA SIMPLE 2: dd<sep>mm<sep>yy(yy)  ej. "21/07/01"
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})$/', $verbatim, $m)) {
            $anio = self::resolverAnio($m[3], $pivotYearDosDigitos);

            return [self::construir($anio, (int) $m[2], (int) $m[1]), null];
        }

        return [null, null];
    }

    private static function resolverAnio(string $anioStr, int $pivotYearDosDigitos): int
    {
        $anio = (int) $anioStr;
        if (strlen($anioStr) === 2) {
            $anio = $anio > $pivotYearDosDigitos ? 1900 + $anio : 2000 + $anio;
        }

        return $anio;
    }

    private static function normalizarMes(string $mesTexto): string
    {
        $normalizado = mb_strtolower($mesTexto);

        return strtr($normalizado, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);
    }

    private static function ultimoDiaDelMes(int $anio, int $mes): int
    {
        try {
            $primero = new \DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes));

            return (int) $primero->format('t');
        } catch (\Exception) {
            return 28;
        }
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
