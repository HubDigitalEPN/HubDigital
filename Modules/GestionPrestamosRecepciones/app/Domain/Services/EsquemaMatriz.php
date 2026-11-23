<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Services;

/** Reglas y nombres provienen de la configuración curatorial persistida. */
final class EsquemaMatriz
{
    public static function clave(string $valor): string
    {
        return mb_strtolower(trim(str_replace("\xEF\xBB\xBF", '', $valor)));
    }

    public static function mapa(array $columnas): array
    {
        $mapa = [];
        foreach ($columnas as $columna) {
            if (trim($columna['campo'] ?? '') === '' || trim($columna['cabecera'] ?? '') === '') {
                throw new \DomainException('El campo y la cabecera no pueden estar vacíos.');
            }
            if (! in_array($columna['requisito'] ?? '', ['obligatorio', 'recomendado', 'opcional', 'interno', 'predeterminado'], true)
                || ! in_array($columna['formato'] ?? '', ['texto', 'fecha'], true)) {
                throw new \DomainException('El requisito o el formato de la columna no es válido.');
            }
            foreach ([$columna['campo'], $columna['cabecera'], ...($columna['alias'] ?? [])] as $nombre) {
                $clave = self::clave($nombre);
                if ($clave === '') continue;
                if (isset($mapa[$clave]) && $mapa[$clave]['campo'] !== $columna['campo']) {
                    throw new \DomainException("La cabecera o alias «{$nombre}» ya pertenece a otra columna.");
                }
                $mapa[$clave] = $columna;
            }
        }
        return $mapa;
    }

    public static function validarRegistros(array $registros, array $filas, array $columnas): void
    {
        $faltantes = [];
        foreach ($registros as $i => $registro) {
            foreach ($columnas as $columna) {
                if ($columna['requisito'] !== 'obligatorio') continue;
                $excepcion = $columna['excepcion_campo'] ?? '';
                if ($excepcion !== '' && self::clave((string) ($registro[$excepcion] ?? '')) === self::clave($columna['excepcion_valor'] ?? '')) continue;
                if (self::faltaValorObligatorio($registro, $columna['campo'])) {
                    $faltantes[$columna['cabecera']][] = $filas[$i];
                }
            }
        }
        if ($faltantes !== []) {
            $detalles = [];
            foreach ($faltantes as $campo => $numeros) {
                $detalles[] = $campo.' (filas '.implode(', ', array_slice($numeros, 0, 5)).(count($numeros) > 5 ? ' y '.(count($numeros) - 5).' más' : '').')';
            }
            throw new \DomainException('Campos obligatorios sin completar: '.implode('; ', $detalles).'. Corrige el Excel y vuelve a subirlo.');
        }
    }

    public static function faltaValorObligatorio(array $registro, string $campo): bool
    {
        if (trim((string) ($registro[$campo] ?? '')) !== '') {
            return false;
        }

        return ! in_array($campo, [
            'kingdom', 'phylum', 'class', 'order', 'family', 'genus', 'specificEpithet', 'identifiedBy', 'dateIdentified',
        ], true) || ! self::identificacionParcial($registro, (string) ($registro['scientificName'] ?? ''));
    }

    public static function identificacionParcial(array $registro, string $nombre): bool
    {
        if (trim($nombre) === '') {
            return false;
        }
        if (preg_match('/(?:^|[\s_])(?:indet(?:erminado|erminada)?|spp?)\.?(?:$|\s)/iu', $nombre) === 1) {
            return true;
        }

        $rango = self::clave((string) ($registro['taxonRank'] ?? ''));
        if (in_array($rango, ['kingdom', 'phylum', 'class', 'order', 'family', 'genus', 'reino', 'filo', 'clase', 'orden', 'familia', 'género', 'genero'], true)) {
            return true;
        }

        return preg_match('/^[\p{L}-]+$/u', $nombre) === 1
            && in_array(self::clave($nombre), array_map(
                static fn (string $campo): string => self::clave((string) ($registro[$campo] ?? '')),
                ['genus', 'family', 'order', 'class', 'phylum', 'kingdom'],
            ), true);
    }
}
