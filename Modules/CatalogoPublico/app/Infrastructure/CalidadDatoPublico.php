<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure;

/** Política analítica: conserva el dato original y excluye marcadores curatoriales. */
final class CalidadDatoPublico
{
    public static function textoValido(string $columna): string
    {
        // Los nombres de columna son constantes internas, nunca entrada del visitante.
        return "({$columna} IS NOT NULL AND btrim({$columna}) <> '' AND lower({$columna}) !~ '(^|[[:space:]])(dañad[oa]s?|danad[oa]s?|ilegible|desconocid[oa]|sin datos|no identificado|indeterminado)([[:space:]]|$)')";
    }

    public static function fechaValida(string $columna): string
    {
        return "({$columna} BETWEEN DATE '1800-01-01' AND CURRENT_DATE)";
    }
}
