<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports;

/**
 * Ancla una jerarquía Darwin Core declarada al árbol taxonómico canónico.
 *
 * Es el camino inverso a {@see ClasificacionTaxonomicaPort}, que parte de un taxón ya
 * conocido: aquí se parte de los nombres tal como los escribió el depositante y se
 * devuelve el taxón al que corresponden, creándolo si hace falta.
 *
 * Existe como puerto y no como dependencia directa porque quien lo implementa vive en
 * Infrastructure y arrastra el catálogo entero de taxones; la capa de aplicación solo
 * necesita la pregunta, no la maquinaria.
 */
interface ResolverTaxonomiaDwCPort
{
    /**
     * Taxón al que se engancha el espécimen, o null si la jerarquía no trae nada usable.
     *
     * @param  array<string, string|null>  $jerarquia  Claves snake_case: kingdom, phylum,
     *                                                 class, order, suborder, family,
     *                                                 subfamily, tribe, genus,
     *                                                 specific_epithet, taxon_rank.
     */
    public function resolver(array $jerarquia): ?string;
}
