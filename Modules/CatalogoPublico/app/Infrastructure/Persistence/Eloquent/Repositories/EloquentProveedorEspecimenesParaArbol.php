<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesParaArbolPort;
use Modules\CatalogoPublico\Domain\ValueObjects\EspecimenParaArbol;
use Modules\CatalogoPublico\Domain\ValueObjects\JerarquiaTaxonomica;

final class EloquentProveedorEspecimenesParaArbol implements ProveedorEspecimenesParaArbolPort
{
    /** @return list<EspecimenParaArbol> */
    public function obtenerTodos(): array
    {
        // Traverses taxonomia.taxones upward (6 levels: species → genus → family → order → class → phylum)
        // using the self-referential padre_id tree. Does not depend on flat columns in divulgacion.especimenes.
        $filas = DB::table('taxonomia.especimenes as te')
            ->join('divulgacion.especimenes_divulgables as ed', 'ed.especimen_id', '=', 'te.id')
            ->join('taxonomia.taxones as tx_species', 'tx_species.id', '=', 'te.taxon_id')
            ->leftJoin('taxonomia.taxones as tx_genus', 'tx_genus.id', '=', 'tx_species.padre_id')
            ->leftJoin('taxonomia.taxones as tx_family', 'tx_family.id', '=', 'tx_genus.padre_id')
            ->leftJoin('taxonomia.taxones as tx_order', 'tx_order.id', '=', 'tx_family.padre_id')
            ->leftJoin('taxonomia.taxones as tx_class', 'tx_class.id', '=', 'tx_order.padre_id')
            ->leftJoin('taxonomia.taxones as tx_phylum', 'tx_phylum.id', '=', 'tx_class.padre_id')
            ->whereNotNull('tx_genus.id')
            ->whereNotNull('tx_family.id')
            ->whereNotNull('tx_order.id')
            ->whereNotNull('tx_class.id')
            ->whereNotNull('tx_phylum.id')
            ->select([
                'te.occurrence_id',
                'tx_species.nombre_cientifico as scientific_name',
                'tx_genus.nombre_cientifico as genus',
                'tx_family.nombre_cientifico as family',
                'tx_order.nombre_cientifico as order',
                'tx_class.nombre_cientifico as class',
                'tx_phylum.nombre_cientifico as phylum',
                'ed.genus_visible',
                'ed.scientific_name_visible',
            ])
            ->get();

        $result = [];

        foreach ($filas as $fila) {
            $genus = $fila->genus;

            // If the taxon's scientific name lacks the genus prefix (malformed data),
            // prepend it so the binomial invariant in JerarquiaTaxonomica is satisfied.
            $scientificName = str_starts_with($fila->scientific_name, $genus.' ')
                ? $fila->scientific_name
                : $genus.' '.$fila->scientific_name;

            $specificEpithet = substr($scientificName, strlen($genus) + 1);

            try {
                $jerarquia = JerarquiaTaxonomica::desde(
                    phylum: $fila->phylum,
                    class: $fila->class,
                    order: $fila->order,
                    family: $fila->family,
                    genus: $genus,
                    specificEpithet: $specificEpithet,
                    scientificName: $scientificName,
                );

                $result[] = EspecimenParaArbol::crear(
                    occurrenceID: $fila->occurrence_id,
                    jerarquia: $jerarquia,
                    genusVisible: (bool) $fila->genus_visible,
                    scientificNameVisible: (bool) $fila->scientific_name_visible,
                );
            } catch (\Throwable) {
                continue;
            }
        }

        return $result;
    }
}
