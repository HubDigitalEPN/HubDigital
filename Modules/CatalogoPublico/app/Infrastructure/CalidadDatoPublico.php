<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure;

/** Política analítica: conserva el dato original y excluye marcadores curatoriales. */
final class CalidadDatoPublico
{
    private const MARCADORES = '(^|[[:space:][:punct:]])(dañad[oa]s?|danad[oa]s?|ilegible|desconocid[oa]|sin datos|no identificado|indeterminado|muestra[[:space:]]*reubicada|reubicad[oa]s?|trasladad[oa]s?|pendiente de revisión|pendiente de revision)([[:space:][:punct:]]|$)';

    public static function textoValido(string $columna): string
    {
        // Los nombres de columna son constantes internas, nunca entrada del visitante.
        return "({$columna} IS NOT NULL AND btrim({$columna}) <> '' AND lower({$columna}) !~ '".self::MARCADORES."')";
    }

    public static function esTextoValido(?string $texto): bool
    {
        return $texto !== null && trim($texto) !== '' && preg_match('/'.self::MARCADORES.'/iu', $texto) === 0;
    }

    public static function fechaValida(string $columna): string
    {
        return "({$columna} BETWEEN DATE '1800-01-01' AND CURRENT_DATE)";
    }
}
