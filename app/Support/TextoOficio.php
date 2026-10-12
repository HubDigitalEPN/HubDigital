<?php

declare(strict_types=1);

namespace App\Support;

final class TextoOficio
{
    public static function nombre(string $texto): string
    {
        $texto = trim($texto);
        return $texto === mb_strtoupper($texto) || $texto === mb_strtolower($texto)
            ? mb_convert_case($texto, MB_CASE_TITLE, 'UTF-8') : $texto;
    }

    public static function oracion(string $texto): string
    {
        $texto = trim($texto);
        if ($texto === '') return '';
        if ($texto === mb_strtoupper($texto)) {
            $texto = mb_strtolower($texto);
            // Recuperar nombres geográficos habituales cuando el original llega en mayúsculas.
            $texto = preg_replace_callback('/\b(laguna|parroquia|cantón|provincia|río|volcán)\s+((?:el\s+|la\s+|san\s+|santa\s+)?[\p{L}]+)/iu',
                static fn (array $m): string => $m[1].' '.self::nombre($m[2]), $texto);
            $texto = preg_replace_callback('/\b(epn|mepn|ruc|pdf)\b/iu', static fn (array $m): string => mb_strtoupper($m[0]), $texto);
            $texto = str_replace('google drive', 'Google Drive', $texto);
        }

        return mb_strtoupper(mb_substr($texto, 0, 1)).mb_substr($texto, 1);
    }
}
