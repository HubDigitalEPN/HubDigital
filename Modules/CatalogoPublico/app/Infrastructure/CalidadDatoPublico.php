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

    /** Prefijo confirmado: una nota original no se convierte en padre científico. */
    public static function rutaConfirmada(array $ruta, string $campoNombre = 'nombre'): array
    {
        $confirmada = [];
        foreach ($ruta as $nodo) {
            if (! self::esTextoValido($nodo[$campoNombre] ?? null)) break;
            $confirmada[] = $nodo;
        }
        return $confirmada;
    }

    /** Taxones con cadena conocida válida; reutiliza el mapa, sin consultar ejemplares. */
    public static function taxonesConLinajeValido(iterable $taxones): array
    {
        $porId = [];
        foreach ($taxones as $nodo) $porId[$nodo->id] = $nodo;
        $validos = [];
        foreach ($porId as $origen => $nodo) {
            $id = $origen;
            $visitados = [];
            $valido = true;
            while ($id && isset($porId[$id])) {
                if (isset($visitados[$id]) || count($visitados) === 30
                    || ! self::esTextoValido($porId[$id]->nombre_cientifico)) {
                    $valido = false;
                    break;
                }
                $visitados[$id] = true;
                $id = $porId[$id]->padre_id;
            }
            if ($valido) $validos[] = (string) $origen;
        }
        return $validos;
    }

    /** Indicador acotado para las doce fichas: no devuelve nombres ni IDs de ancestros. */
    public static function revisionTaxonomicaSql(string $columnaTaxonId): string
    {
        $nombreValido = self::textoValido('c.nombre_cientifico');
        // La columna es una constante interna. El recorrido se detiene antes de repetir un UUID.
        return "({$columnaTaxonId} IS NULL OR NOT EXISTS (SELECT 1 FROM taxonomia.taxones conocido WHERE conocido.id = {$columnaTaxonId}) OR EXISTS (
            WITH RECURSIVE cadena AS (
                SELECT t.id, t.padre_id, t.nombre_cientifico, ARRAY[t.id] AS camino, 0 AS profundidad
                FROM taxonomia.taxones t WHERE t.id = {$columnaTaxonId}
                UNION ALL
                SELECT p.id, p.padre_id, p.nombre_cientifico, c.camino || p.id, c.profundidad + 1
                FROM cadena c JOIN taxonomia.taxones p ON p.id = c.padre_id
                WHERE c.profundidad < 29 AND NOT p.id = ANY(c.camino)
            ) SELECT 1 FROM cadena c WHERE NOT {$nombreValido}
                OR c.padre_id = ANY(c.camino)
                OR (c.profundidad = 29 AND EXISTS (SELECT 1 FROM taxonomia.taxones pendiente WHERE pendiente.id = c.padre_id))
        ))";
    }

    public static function fechaValida(string $columna): string
    {
        return "({$columna} BETWEEN DATE '1800-01-01' AND CURRENT_DATE)";
    }
}
