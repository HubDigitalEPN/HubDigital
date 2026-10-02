<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure;

/** Comparación de nombres geográficos; no modifica el texto de la colección. */
final class NormalizacionGeografica
{
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
}
