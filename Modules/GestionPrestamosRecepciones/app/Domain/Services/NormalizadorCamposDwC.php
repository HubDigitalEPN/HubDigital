<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Services;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoNormalizacion;

/**
 * Normaliza campos DwC con vocabulario cerrado en los registros del Excel
 * antes de persistirlos.
 *
 * Estrategia conservadora:
 *  - Alias conocido (case-insensitive) → reemplaza con el canónico.
 *  - Valor desconocido               → lo deja intacto, nunca rechaza.
 *
 * Campos normalizados:
 *  - sex          : aliases en español/abreviatura → vocabulario DwC
 *  - lifeStage    : aliases en español             → vocabulario DwC
 *  - decimalLatitude / decimalLongitude : coma → punto; exige rango válido
 *    (lat −90/90, lon −180/180) y entre 1 y 4 decimales (marca inválido si no)
 */
final class NormalizadorCamposDwC
{
    /** @var array<string, array<string, string>> campo → [alias_lower => canónico] */
    private const ALIAS_MAPS = [
        'sex' => [
            'male' => 'male',
            'm' => 'male',
            'macho' => 'male',
            'female' => 'female',
            'f' => 'female',
            'hembra' => 'female',
            'h' => 'female',
            'gyne' => 'gyne',
            'male/female' => 'male/female',
            'm/f' => 'male/female',
            'nd' => 'unknown',
            '?' => 'unknown',
        ],
        'lifeStage' => [
            'adult' => 'adult',
            'adult?' => 'adult',
            'juvenil' => 'juvenile',
            'juvenile' => 'juvenile',
            'ninfa' => 'nymph',
            'nympha' => 'nymph',
            'nymph' => 'nymph',
            'larva' => 'larva',
            'pupa' => 'pupa',
            'nd' => 'unknown',
        ],
    ];

    /**
     * Normaliza los campos DwC normalizables de un registro y registra los cambios.
     *
     * @param  array<string, mixed>  $registro
     */
    public function normalizar(array $registro): ResultadoNormalizacion
    {
        $cambios = [];

        foreach (self::ALIAS_MAPS as $campo => $mapa) {
            if (! isset($registro[$campo]) || $registro[$campo] === null || $registro[$campo] === '') {
                continue;
            }

            $original = $registro[$campo];
            $clave = mb_strtolower(trim((string) $original));

            if (isset($mapa[$clave]) && $mapa[$clave] !== $original) {
                $registro[$campo] = $mapa[$clave];
                $cambios[] = [
                    'campo' => $campo,
                    'original' => $original,
                    'normalizado' => $mapa[$clave],
                ];
            }
        }

        [$registro, $cambiosCoordenadas] = $this->normalizarCoordenada($registro, 'decimalLatitude');
        $cambios = array_merge($cambios, $cambiosCoordenadas);

        [$registro, $cambiosCoordenadas] = $this->normalizarCoordenada($registro, 'decimalLongitude');
        $cambios = array_merge($cambios, $cambiosCoordenadas);

        foreach (['recordedBy', 'identifiedBy'] as $campoPersona) {
            [$registro, $cambiosPersona] = $this->validarFormatoPersona($registro, $campoPersona);
            $cambios = array_merge($cambios, $cambiosPersona);
        }

        [$registro, $cambiosFecha] = $this->validarFecha($registro);
        $cambios = array_merge($cambios, $cambiosFecha);

        [$registro, $cambiosIdioma] = $this->normalizarIdioma($registro);
        $cambios = array_merge($cambios, $cambiosIdioma);

        return new ResultadoNormalizacion(
            registro: $registro,
            cambios: $cambios,
        );
    }

    /**
     * @param  array<string, mixed>  $registro
     * @return array{array<string, mixed>, list<array{campo: string, original: mixed, normalizado: mixed}>}
     */
    private function normalizarCoordenada(array $registro, string $campo): array
    {
        if (! isset($registro[$campo]) || $registro[$campo] === null || $registro[$campo] === '') {
            return [$registro, []];
        }

        $original = $registro[$campo];
        $valor = is_string($original) ? str_replace(',', '.', trim($original)) : $original;

        if (! is_numeric($valor)) {
            return [$registro, [[
                'campo' => $campo,
                'original' => $original,
                'normalizado' => null,
                'invalido' => true,
                'mensaje' => 'No es un valor numérico válido',
            ]]];
        }

        // Precisión geográfica exigida: entre 1 y 4 decimales.
        $decimales = $this->contarDecimales((string) $valor);
        if ($decimales < 1 || $decimales > 4) {
            return [$registro, [[
                'campo' => $campo,
                'original' => $original,
                'normalizado' => null,
                'invalido' => true,
                'mensaje' => 'Debe tener entre 1 y 4 decimales',
            ]]];
        }

        $normalizado = round((float) $valor, 4);

        $limites = [
            'decimalLatitude' => [-90.0,  90.0],
            'decimalLongitude' => [-180.0, 180.0],
        ];

        [$min, $max] = $limites[$campo];

        if ($normalizado < $min || $normalizado > $max) {
            $registro[$campo] = $normalizado;

            return [$registro, [[
                'campo' => $campo,
                'original' => $original,
                'normalizado' => $normalizado,
                'invalido' => true,
                'mensaje' => "Fuera de rango válido ({$min} a {$max})",
            ]]];
        }

        if ((string) $normalizado === (string) $original) {
            return [$registro, []];
        }

        $registro[$campo] = $normalizado;

        return [$registro, [[
            'campo' => $campo,
            'original' => $original,
            'normalizado' => $normalizado,
        ]]];
    }

    /**
     * Cuenta los dígitos decimales de un valor numérico ya normalizado (con punto).
     */
    private function contarDecimales(string $valor): int
    {
        $pos = strpos($valor, '.');

        return $pos === false ? 0 : strlen(substr($valor, $pos + 1));
    }

    /**
     * Valida year, month y day: deben ser enteros, dentro de rango, y coherentes entre sí.
     * Si month y day están presentes, usa checkdate() para detectar combinaciones imposibles
     * (ej: 31 de febrero, 30 de noviembre). Si year está presente se valida también el año bisiesto.
     *
     * @param  array<string, mixed>  $registro
     * @return array{array<string, mixed>, list<array{campo: string, original: mixed, normalizado: null, invalido: bool, mensaje: string}>}
     */
    private function validarFecha(array $registro): array
    {
        $cambios = [];
        $erroresCampos = [];

        foreach (['year', 'month', 'day'] as $campo) {
            $val = $registro[$campo] ?? null;
            if ($val === null || $val === '') {
                continue;
            }

            if (! is_numeric($val) || floor((float) $val) != (float) $val) {
                $cambios[] = [
                    'campo' => $campo,
                    'original' => $val,
                    'normalizado' => null,
                    'invalido' => true,
                    'mensaje' => 'Debe ser un número entero',
                ];
                $erroresCampos[] = $campo;
            }
        }

        // Si algún campo base no era entero, no continuar con rangos ni combinaciones
        if (! empty($erroresCampos)) {
            return [$registro, $cambios];
        }

        $year = (isset($registro['year']) && $registro['year'] !== null && $registro['year'] !== '') ? (int) $registro['year'] : null;
        $month = (isset($registro['month']) && $registro['month'] !== null && $registro['month'] !== '') ? (int) $registro['month'] : null;
        $day = (isset($registro['day']) && $registro['day'] !== null && $registro['day'] !== '') ? (int) $registro['day'] : null;

        if ($month !== null && ($month < 1 || $month > 12)) {
            $cambios[] = [
                'campo' => 'month',
                'original' => $registro['month'],
                'normalizado' => null,
                'invalido' => true,
                'mensaje' => 'Mes inválido (1-12)',
            ];
            $erroresCampos[] = 'month';
        }

        if ($day !== null && ($day < 1 || $day > 31)) {
            $cambios[] = [
                'campo' => 'day',
                'original' => $registro['day'],
                'normalizado' => null,
                'invalido' => true,
                'mensaje' => 'Día fuera de rango (1-31)',
            ];
            $erroresCampos[] = 'day';
        }

        // Validación cruzada día + mes (+ año para años bisiestos)
        if ($month !== null && $day !== null
            && ! in_array('month', $erroresCampos, true)
            && ! in_array('day', $erroresCampos, true)
        ) {
            $yearCheck = $year ?? 2001; // 2001 = no bisiesto como referencia neutral
            if (! checkdate($month, $day, $yearCheck)) {
                $cambios[] = [
                    'campo' => 'day',
                    'original' => $registro['day'],
                    'normalizado' => null,
                    'invalido' => true,
                    'mensaje' => 'Día inválido para el mes',
                ];
            }
        }

        return [$registro, $cambios];
    }

    /**
     * Normaliza el campo language a minúsculas y valida que sea un código ISO 639-1 (2 letras).
     * Ej: "ES" → "es" (normalización silenciosa). "español" → advertencia.
     *
     * @param  array<string, mixed>  $registro
     * @return array{array<string, mixed>, list<array{campo: string, original: mixed, normalizado: mixed, invalido?: bool, mensaje?: string}>}
     */
    private function normalizarIdioma(array $registro): array
    {
        if (! isset($registro['language']) || $registro['language'] === null || $registro['language'] === '') {
            return [$registro, []];
        }

        $original = $registro['language'];
        $normalizado = mb_strtolower(trim((string) $original));

        if (! preg_match('/^[a-z]{2}$/', $normalizado)) {
            return [$registro, [[
                'campo' => 'language',
                'original' => $original,
                'normalizado' => null,
                'invalido' => true,
                'mensaje' => 'Formato incorrecto — Ej: es, en, fr',
            ]]];
        }

        if ($normalizado === (string) $original) {
            return [$registro, []];
        }

        $registro['language'] = $normalizado;

        return [$registro, [[
            'campo' => 'language',
            'original' => $original,
            'normalizado' => $normalizado,
        ]]];
    }

    /**
     * Valida que recordedBy / identifiedBy sigan el patrón "Apellido I" (o varios separados por ';').
     * Acepta: "García J", "García J.", "García, J", "García, J.", "De La Cruz M"
     *
     * @param  array<string, mixed>  $registro
     * @return array{array<string, mixed>, list<array{campo: string, original: mixed, normalizado: mixed, invalido: bool, mensaje: string}>}
     */
    private function validarFormatoPersona(array $registro, string $campo): array
    {
        if (! isset($registro[$campo]) || $registro[$campo] === null || $registro[$campo] === '') {
            return [$registro, []];
        }

        $original = $registro[$campo];
        $partes = array_map('trim', explode(';', (string) $original));

        // Apellido (posiblemente compuesto) + coma/espacio opcional + inicial sola con punto opcional
        $patron = '/^[\p{L}\-\' ]+[,]?\s+\p{L}\.?$/u';

        foreach ($partes as $parte) {
            if ($parte === '') {
                continue;
            }

            if (! preg_match($patron, $parte)) {
                return [$registro, [[
                    'campo' => $campo,
                    'original' => $original,
                    'normalizado' => null,
                    'invalido' => true,
                    'mensaje' => 'Formato incorrecto — Ej: García, J (separar con ;)',
                ]]];
            }
        }

        return [$registro, []];
    }
}
