<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Application\Ports\ProveedorJerarquiaDeEspecimenPort;
use Modules\CatalogoPublico\Domain\ValueObjects\JerarquiaTaxonomica;

final class JerarquiaDeEspecimenAdapter implements ProveedorJerarquiaDeEspecimenPort
{
    public function obtener(string $occurrenceID): ?JerarquiaTaxonomica
    {
        // La jerarquía se resuelve igual que el árbol de divulgación: taxonomia.especimenes
        // (solo divulgados) + la cadena de padres en taxonomia.taxones.
        $fila = DB::table('taxonomia.especimenes as te')
            ->join('divulgacion.especimenes_divulgables as ed', 'ed.especimen_id', '=', 'te.id')
            ->join('taxonomia.taxones as tx_species', 'tx_species.id', '=', 'te.taxon_id')
            ->leftJoin('taxonomia.taxones as tx_genus', 'tx_genus.id', '=', 'tx_species.padre_id')
            ->leftJoin('taxonomia.taxones as tx_family', 'tx_family.id', '=', 'tx_genus.padre_id')
            ->leftJoin('taxonomia.taxones as tx_order', 'tx_order.id', '=', 'tx_family.padre_id')
            ->leftJoin('taxonomia.taxones as tx_class', 'tx_class.id', '=', 'tx_order.padre_id')
            ->leftJoin('taxonomia.taxones as tx_phylum', 'tx_phylum.id', '=', 'tx_class.padre_id')
            ->where('te.occurrence_id', $occurrenceID)
            ->select([
                'tx_species.nombre_cientifico as scientific_name',
                'tx_genus.nombre_cientifico as genus',
                'tx_family.nombre_cientifico as family',
                'tx_order.nombre_cientifico as order',
                'tx_class.nombre_cientifico as class',
                'tx_phylum.nombre_cientifico as phylum',
            ])
            ->first();

        if ($fila === null
            || $fila->genus === null || $fila->family === null
            || $fila->order === null || $fila->class === null || $fila->phylum === null) {
            return null;
        }

        $genus = $fila->genus;
        $scientificName = str_starts_with($fila->scientific_name, $genus.' ')
            ? $fila->scientific_name
            : $genus.' '.$fila->scientific_name;
        $epithet = substr($scientificName, strlen($genus) + 1);

        try {
            return JerarquiaTaxonomica::desde(
                phylum: $fila->phylum,
                class: $fila->class,
                order: $fila->order,
                family: $fila->family,
                genus: $genus,
                specificEpithet: $epithet,
                scientificName: $scientificName,
            );
        } catch (\Throwable) {
            return null;
        }
    }
}
