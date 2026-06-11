<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;

/**
 * Resuelve la ClasificacionTaxonomica completa (7 niveles) dado un taxonId,
 * recorriendo el árbol taxonómico hacia la raíz.
 *
 * La implementación real consulta el módulo de Taxonomía.
 * Durante el desarrollo se puede sustituir con FakeClasificacionTaxonomicaAdapter.
 */
interface ClasificacionTaxonomicaPort
{
    /** Devuelve la clasificación taxonómica completa del taxón indicado, o null si no se puede resolver. */
    public function resolverParaTaxon(string $taxonId): ?ClasificacionTaxonomica;
}
