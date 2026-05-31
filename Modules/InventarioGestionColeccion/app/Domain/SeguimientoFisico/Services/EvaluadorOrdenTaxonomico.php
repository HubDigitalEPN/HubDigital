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
 *   2. subfamilia — alfabética.
 *   3. género     — alfabético.
 *   4. especie    — alfabético (desempate fino).
 * Si una dimensión es null en cualquiera de las dos cajas, esa dimensión se omite.
 */
final class EvaluadorOrdenTaxonomico
{
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

        // 2-4. Dentro de la familia: alfabético subfamilia → género → especie.
        $niveles = [
            [$a->subfamilia(), $b->subfamilia()],
            [$a->genero(), $b->genero()],
            [$a->especie(), $b->especie()],
        ];

        foreach ($niveles as [$valorA, $valorB]) {
            if ($valorA === null || $valorB === null) {
                continue;
            }

            $cmp = strcasecmp($valorA, $valorB);

            if ($cmp !== 0) {
                return $cmp > 0;
            }
        }

        return false;
    }
}
