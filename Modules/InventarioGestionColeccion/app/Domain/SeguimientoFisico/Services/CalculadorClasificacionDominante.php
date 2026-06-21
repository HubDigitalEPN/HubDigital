<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;

/**
 * Determina la ClasificacionTaxonomica dominante de un conjunto.
 * Criterio primario: subfamilia + género más frecuentes.
 * Desempate determinista (case-insensitive) entre clasificaciones de igual frecuencia.
 */
final class CalculadorClasificacionDominante
{
    /**
     * Calcula la clasificación dominante del conjunto agrupando por subfamilia+género,
     * eligiendo la combinación más frecuente y desempatando de forma determinista por
     * orden alfabético insensible a mayúsculas. Devuelve null si el conjunto está vacío.
     *
     * Conserva el nivel de UnitTray cohesivo: un tray expone su combinación dominante y los
     * especímenes que discrepan se reportan como "fuera de lugar" (alerta suave).
     *
     * @param  ClasificacionTaxonomica[]  $clasificaciones
     */
    public function calcular(array $clasificaciones): ?ClasificacionTaxonomica
    {
        if ($clasificaciones === []) {
            return null;
        }

        $frecuencias = [];
        foreach ($clasificaciones as $cls) {
            $key = ($cls->subfamilia() ?? '').'|'.($cls->genero() ?? '');
            if (! isset($frecuencias[$key])) {
                $frecuencias[$key] = ['cls' => $cls, 'count' => 0];
            }
            $frecuencias[$key]['count']++;
        }

        usort($frecuencias, static function (array $a, array $b): int {
            if ($b['count'] !== $a['count']) {
                return $b['count'] - $a['count'];
            }

            return strcasecmp(
                ($a['cls']->subfamilia() ?? '').($a['cls']->genero() ?? ''),
                ($b['cls']->subfamilia() ?? '').($b['cls']->genero() ?? ''),
            );
        });

        return $frecuencias[0]['cls'];
    }

    /**
     * Calcula la clasificación AGREGADA de una Caja: conserva la combinación dominante
     * (vía {@see calcular()}) pero amplía los conjuntos de subfamilias y géneros con todos
     * los valores distintos presentes en sus UnitTrays. Así una caja que alberga varias
     * subfamilias o géneros los muestra todos sin perder cuál es el dominante. Devuelve null
     * si el conjunto está vacío.
     *
     * @param  ClasificacionTaxonomica[]  $clasificaciones
     */
    public function calcularAgregado(array $clasificaciones): ?ClasificacionTaxonomica
    {
        $dominante = $this->calcular($clasificaciones);

        if ($dominante === null) {
            return null;
        }

        $subfamilias = [];
        $generos = [];
        foreach ($clasificaciones as $cls) {
            array_push($subfamilias, ...$cls->subfamilias());
            array_push($generos, ...$cls->generos());
        }

        return $dominante->conSubfamiliasYGeneros($subfamilias, $generos);
    }
}
