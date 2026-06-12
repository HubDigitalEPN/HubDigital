<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\OrdenEsperadoFamilias;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoAlerta;

/**
 * Evalúa si una Caja está en el orden taxonómico correcto respecto a sus vecinas
 * en el mismo Gabinete.
 *
 * Reglas de alerta (soft alerts — el ingreso siempre procede):
 * - FamiliaNoAsignada: la Caja no tiene clasificación taxonómica.
 * - IncongruenciaTaxonomica: la Caja rompe el orden con una vecina.
 *
 * Las Cajas especiales (esEspecial = true) están exentas de toda verificación.
 *
 * Orden de comparación (mayor a menor precedencia):
 *   1. familia    — NO alfabética; sigue la secuencia esperada definida por el curador
 *                   (OrdenEsperadoFamilias). Si una familia no está en la secuencia, se omite.
 *   2. subfamilia — alfabética, por RANGO (una caja puede abarcar varias subfamilias).
 *   3. género     — alfabético, por RANGO (una caja puede abarcar varios géneros).
 *   4. especie    — alfabético (desempate fino, escalar dominante).
 * Si una dimensión es null en cualquiera de las dos cajas, esa dimensión se omite.
 *
 * Regla de rango (direccional): cuando una caja agrupa varias subfamilias o géneros, cada caja
 * se evalúa contra su vecina por el BORDE que la enfrenta. Como evaluar() compara (anterior,
 * caja) y (caja, siguiente), en ambas comparaciones la caja superior aporta su valor MÁXIMO y la
 * inferior su MÍNIMO: así la vecina de arriba se contrasta con la primera subfamilia de la caja y
 * la de abajo con la última. Hay desorden cuando el máximo de la caja superior supera al mínimo
 * de la inferior; si coinciden en el borde, se pasa al siguiente nivel.
 */
final class EvaluadorOrdenTaxonomico
{
    /**
     * Evalúa la caja a insertar contra sus vecinas inmediatas y devuelve el tipo de
     * alerta que corresponde, o null si todo está en orden. Las cajas especiales y las
     * sin clasificación se manejan según las reglas descritas en la clase. El ingreso
     * físico nunca se bloquea: el resultado es solo informativo (soft alert).
     */
    public function evaluar(
        Caja $cajaAInsertar,
        ?Caja $cajaAnterior,
        ?Caja $cajaSiguiente,
        ?OrdenEsperadoFamilias $ordenFamilias = null,
    ): ?TipoAlerta {
        if ($cajaAInsertar->esEspecial()) {
            return null;
        }

        $clasificacion = $cajaAInsertar->clasificacionTaxonomica();

        if ($clasificacion === null || $clasificacion->estaVacia()) {
            return TipoAlerta::FamiliaNoAsignada;
        }

        if ($cajaAnterior !== null && ! $cajaAnterior->esEspecial()) {
            if ($this->estanInvertidas($cajaAnterior, $cajaAInsertar, $ordenFamilias)) {
                return TipoAlerta::OrdenTaxonomicoFueraDeSecuencia;
            }
        }

        if ($cajaSiguiente !== null && ! $cajaSiguiente->esEspecial()) {
            if ($this->estanInvertidas($cajaAInsertar, $cajaSiguiente, $ordenFamilias)) {
                return TipoAlerta::OrdenTaxonomicoFueraDeSecuencia;
            }
        }

        return null;
    }

    /**
     * Retorna true si $primera viene estrictamente DESPUÉS de $segunda
     * en el orden esperado, es decir, están en orden incorrecto.
     */
    private function estanInvertidas(Caja $primera, Caja $segunda, ?OrdenEsperadoFamilias $ordenFamilias): bool
    {
        $clsA = $primera->clasificacionTaxonomica();
        $clsB = $segunda->clasificacionTaxonomica();

        if ($clsA === null || $clsB === null) {
            return false;
        }

        return $this->clasificacionEsMayor($clsA, $clsB, $ordenFamilias);
    }

    /**
     * Decide si la clasificación $a es "mayor" que $b en el orden esperado: primero por
     * la secuencia de familias del curador (no alfabética) y, dentro de la misma familia,
     * alfabéticamente por subfamilia → género → especie, omitiendo dimensiones nulas.
     */
    private function clasificacionEsMayor(
        ClasificacionTaxonomica $a,
        ClasificacionTaxonomica $b,
        ?OrdenEsperadoFamilias $ordenFamilias,
    ): bool {
        // 1. Familia: orden NO alfabético, definido por la secuencia esperada del curador.
        //    Solo decide si ambas familias están en la secuencia y difieren; en cualquier
        //    otro caso (sin secuencia, familia ausente o misma familia) se omite este nivel.
        if ($ordenFamilias !== null) {
            $posA = $ordenFamilias->posicionDe($a->familia());
            $posB = $ordenFamilias->posicionDe($b->familia());

            if ($posA !== null && $posB !== null && $posA !== $posB) {
                return $posA > $posB;
            }
        }

        // 2-3. Dentro de la familia: subfamilia y género por rango, comparando el máximo de la
        //      caja superior contra el mínimo de la inferior (su borde de contacto).
        foreach ([[$a->subfamilias(), $b->subfamilias()], [$a->generos(), $b->generos()]] as [$rangoA, $rangoB]) {
            $relacion = $this->compararRangos($rangoA, $rangoB);

            if ($relacion === self::RANGO_POSTERIOR) {
                return true;
            }

            if ($relacion === self::RANGO_ANTERIOR) {
                return false;
            }
        }

        // 4. Especie: desempate fino sobre el valor escalar dominante.
        $especieA = $a->especie();
        $especieB = $b->especie();

        if ($especieA !== null && $especieB !== null) {
            $cmp = strcasecmp($especieA, $especieB);

            if ($cmp !== 0) {
                return $cmp > 0;
            }
        }

        return false;
    }

    private const RANGO_ANTERIOR = -1;

    private const RANGO_SOLAPADO = 0;

    private const RANGO_POSTERIOR = 1;

    /**
     * Relaciona dos rangos enfrentados por su borde: $a es la caja superior (aporta su MÁXIMO)
     * y $b la inferior (aporta su MÍNIMO). Devuelve POSTERIOR si el máximo de $a supera al mínimo
     * de $b (desorden), ANTERIOR si queda por debajo (orden correcto) y SOLAPADO si coinciden en
     * el borde (indeciso: decide el nivel siguiente). Un rango vacío (nivel ausente) se omite
     * tratándolo como solapado. Con valores únicos equivale a comparar escalar contra escalar.
     *
     * @param  string[]  $a
     * @param  string[]  $b
     */
    private function compararRangos(array $a, array $b): int
    {
        if ($a === [] || $b === []) {
            return self::RANGO_SOLAPADO;
        }

        $cmp = strcasecmp($this->maximo($a), $this->minimo($b));

        if ($cmp > 0) {
            return self::RANGO_POSTERIOR;
        }

        if ($cmp < 0) {
            return self::RANGO_ANTERIOR;
        }

        return self::RANGO_SOLAPADO;
    }

    /** @param  string[]  $valores */
    private function minimo(array $valores): string
    {
        return array_reduce(
            $valores,
            static fn (?string $acarreo, string $valor): string => $acarreo === null || strcasecmp($valor, $acarreo) < 0 ? $valor : $acarreo,
            null,
        ) ?? '';
    }

    /** @param  string[]  $valores */
    private function maximo(array $valores): string
    {
        return array_reduce(
            $valores,
            static fn (?string $acarreo, string $valor): string => $acarreo === null || strcasecmp($valor, $acarreo) > 0 ? $valor : $acarreo,
            null,
        ) ?? '';
    }
}
