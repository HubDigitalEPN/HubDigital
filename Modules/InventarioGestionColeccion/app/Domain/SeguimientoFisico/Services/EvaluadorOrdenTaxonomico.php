<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoAlerta;

/**
 * Evalúa si una Caja está en el orden taxonómico alfabético correcto
 * respecto a sus vecinas en el mismo Gabinete.
 *
 * Reglas de alerta (soft alerts — el ingreso siempre procede):
 * - FamiliaNoAsignada: la Caja no tiene clasificación taxonómica.
 * - IncongruenciaTaxonomica: la Caja rompe el orden alfabético con una vecina.
 *
 * Las Cajas especiales (esEspecial = true) están exentas de toda verificación.
 * La comparación usa subfamilia como criterio primario y genero como desempate.
 * Si una dimensión es null en cualquiera de las dos cajas, esa dimensión se omite.
 */
final class EvaluadorOrdenTaxonomico
{
    public function evaluar(
        Caja $cajaAInsertar,
        ?Caja $cajaAnterior,
        ?Caja $cajaSiguiente,
    ): ?TipoAlerta {
        if ($cajaAInsertar->esEspecial()) {
            return null;
        }

        $clasificacion = $cajaAInsertar->clasificacionTaxonomica();

        if ($clasificacion === null || $clasificacion->estaVacia()) {
            return TipoAlerta::FamiliaNoAsignada;
        }

        if ($cajaAnterior !== null && ! $cajaAnterior->esEspecial()) {
            if ($this->estanInvertidas($cajaAnterior, $cajaAInsertar)) {
                return TipoAlerta::OrdenTaxonomicoFueraDeSecuencia;
            }
        }

        if ($cajaSiguiente !== null && ! $cajaSiguiente->esEspecial()) {
            if ($this->estanInvertidas($cajaAInsertar, $cajaSiguiente)) {
                return TipoAlerta::OrdenTaxonomicoFueraDeSecuencia;
            }
        }

        return null;
    }

    /**
     * Retorna true si $primera viene estrictamente DESPUÉS de $segunda
     * en orden alfabético, es decir, están en orden incorrecto.
     *
     * Compara subfamilia primero; usa genero como desempate.
     * Si una dimensión es null en cualquiera de las cajas, se omite esa dimensión.
     */
    private function estanInvertidas(Caja $primera, Caja $segunda): bool
    {
        $clsA = $primera->clasificacionTaxonomica();
        $clsB = $segunda->clasificacionTaxonomica();

        if ($clsA === null || $clsB === null) {
            return false;
        }

        return $this->clasificacionEsMayor($clsA, $clsB);
    }

    private function clasificacionEsMayor(ClasificacionTaxonomica $a, ClasificacionTaxonomica $b): bool
    {
        $subfamiliaA = $a->subfamilia();
        $subfamiliaB = $b->subfamilia();

        if ($subfamiliaA !== null && $subfamiliaB !== null) {
            $cmp = strcasecmp($subfamiliaA, $subfamiliaB);

            if ($cmp > 0) {
                return true;
            }

            if ($cmp < 0) {
                return false;
            }
        }

        $generoA = $a->genero();
        $generoB = $b->genero();

        if ($generoA !== null && $generoB !== null) {
            return strcasecmp($generoA, $generoB) > 0;
        }

        return false;
    }
}
