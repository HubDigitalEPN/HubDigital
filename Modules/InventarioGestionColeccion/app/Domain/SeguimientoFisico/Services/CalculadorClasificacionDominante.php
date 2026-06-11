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
}
