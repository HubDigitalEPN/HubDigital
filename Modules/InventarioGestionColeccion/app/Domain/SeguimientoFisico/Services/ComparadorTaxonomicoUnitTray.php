<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;

/**
 * Ordena y verifica la pertenencia taxonómica de los UnitTrays dentro de una Caja.
 *
 * A diferencia de las Cajas vecinas (que siguen la secuencia de familias del curador),
 * los UnitTrays se organizan alfabéticamente con la taxonomía descendiendo hasta el
 * desempate más fino: subfamilia → género → especie. La familia no participa porque
 * todos los trays de una misma caja comparten clasificación dominante a ese nivel.
 *
 * Regla de nulos: una dimensión null se trata como "posterior" en el orden y se omite
 * en la verificación de pertenencia (no puede contradecir lo que no se conoce).
 */
final class ComparadorTaxonomicoUnitTray
{
    private const NIVELES_ORDEN = ['subfamilia', 'genero', 'especie'];

    /**
     * Comparador alfabético determinista para usort(): subfamilia → género → especie.
     * Los trays sin clasificación (o con la dimensión null) quedan al final.
     */
    public function comparar(?ClasificacionTaxonomica $a, ?ClasificacionTaxonomica $b): int
    {
        if ($a === null || $a->estaVacia()) {
            return ($b === null || $b->estaVacia()) ? 0 : 1;
        }

        if ($b === null || $b->estaVacia()) {
            return -1;
        }

        foreach (self::NIVELES_ORDEN as $nivel) {
            $valorA = $a->{$nivel}();
            $valorB = $b->{$nivel}();

            if ($valorA === $valorB) {
                continue;
            }

            if ($valorA === null) {
                return 1;
            }

            if ($valorB === null) {
                return -1;
            }

            $cmp = strcasecmp($valorA, $valorB);

            if ($cmp !== 0) {
                return $cmp;
            }
        }

        return 0;
    }

    /**
     * Retorna true si la clasificación de un especimen pertenece al grupo dominante
     * del tray, comparando hasta especie. Cada dimensión null (en cualquiera de las dos)
     * se omite: solo una diferencia explícita marca al especimen como fuera de lugar.
     */
    public function perteneceAlGrupo(
        ClasificacionTaxonomica $especimen,
        ClasificacionTaxonomica $dominante,
    ): bool {
        foreach (self::NIVELES_ORDEN as $nivel) {
            $valorEspecimen = $especimen->{$nivel}();
            $valorDominante = $dominante->{$nivel}();

            if ($valorEspecimen === null || $valorDominante === null) {
                continue;
            }

            if (strcasecmp($valorEspecimen, $valorDominante) !== 0) {
                return false;
            }
        }

        return true;
    }
}
