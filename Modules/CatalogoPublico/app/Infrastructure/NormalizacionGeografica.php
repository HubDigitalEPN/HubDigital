<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure;

/** Comparación de nombres geográficos; no modifica el texto de la colección. */
final class NormalizacionGeografica
{
    /** Excluye opciones visualmente vacías sin modificar el nombre original. */
    public static function contieneNombre(?string $texto): bool
    {
        return $texto !== null && preg_match('/[^\p{Z}\p{Cf}\s]/u', $texto) === 1;
    }

    public static function sql(string $columna): string
    {
        return "translate(lower(btrim(regexp_replace({$columna}, '[[:space:]]+', ' ', 'g'))), 'áéíóúüñ', 'aeiouun')";
    }

    public static function normalizar(string $texto): string
    {
        return strtr(mb_strtolower(trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }

    /** Una opción por clave del filtro; las grafías de la colección se conservan. */
    public static function nombresDisponibles(array $nombres): array
    {
        $opciones = [];
        foreach ($nombres as $nombre) {
            if (! is_string($nombre) || ! self::contieneNombre($nombre)) continue;
            $clave = self::normalizar($nombre);
            $opciones[$clave] ??= match ($clave) {
                'narino' => 'Nariño', 'choco' => 'Chocó',
                default => trim($nombre),
            };
        }
        ksort($opciones, SORT_NATURAL);
        return array_values($opciones);
    }

    /** Resuelve grafías equivalentes contra opciones realmente publicadas. */
    public static function nombreDisponible(string $valor, array $opciones): ?string
    {
        if (! self::contieneNombre($valor)) return null;
        $clave = self::normalizar($valor);
        foreach ($opciones as $opcion) {
            if (is_string($opcion) && self::normalizar($opcion) === $clave) return $opcion;
        }
        return null;
    }
}
