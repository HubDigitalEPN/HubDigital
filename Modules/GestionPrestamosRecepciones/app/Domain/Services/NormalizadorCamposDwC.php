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
 *  - decimalLatitude / decimalLongitude : coma → punto + redondeo a 4 decimales
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
        $valor = $original;

        if (is_string($valor)) {
            $valor = str_replace(',', '.', trim($valor));
        }

        if (! is_numeric($valor)) {
            return [$registro, []];
        }

        $normalizado = round((float) $valor, 4);

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
}
