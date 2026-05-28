<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Application\Ports\DatosEspecimenProveedor;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;

/**
 * ACL — Anti-Corruption Layer entre CatalogoPublico (Customer) e InventarioGestionColeccion (Supplier).
 * Toda la traducción del lenguaje del Supplier al lenguaje de CatalogoPublico vive aquí.
 * Si taxonomia.especimenes cambia su schema, solo este archivo se modifica.
 */
final class InventarioGestionColeccionEspecimenAdapter implements ProveedorEspecimenesPort
{
    public function buscarPorOccurrenceId(string $occurrenceId): ?DatosEspecimenProveedor
    {
        $fila = TaxonomiaEspecimenReadModel::query()
            ->select([
                'taxonomia.especimenes.id',
                'taxonomia.especimenes.taxon_id',
                'taxonomia.especimenes.occurrence_id',
                'taxonomia.especimenes.colector',
                'taxonomia.especimenes.individual_count',
                'taxonomia.especimenes.disposition',
                'taxonomia.especimenes.occurrence_status',
                'taxonomia.especimenes.specimen_notes',
                'taxonomia.especimenes.country',
                'taxonomia.especimenes.locality_name',
                'taxonomia.especimenes.decimal_latitude',
                'taxonomia.especimenes.decimal_longitude',
                'taxonomia.taxones.nombre_cientifico',
            ])
            ->join('taxonomia.taxones', 'taxonomia.taxones.id', '=', 'taxonomia.especimenes.taxon_id')
            ->where('taxonomia.especimenes.occurrence_id', $occurrenceId)
            ->first();

        if ($fila === null) {
            return null;
        }

        [$family, $genus] = $this->obtenerFamiliaYGenero($fila->taxon_id);

        return $this->traducir($fila, $family, $genus);
    }

    /** @return DatosEspecimenProveedor[] */
    public function obtenerTodos(): array
    {
        $filas = TaxonomiaEspecimenReadModel::query()
            ->select([
                'taxonomia.especimenes.id',
                'taxonomia.especimenes.taxon_id',
                'taxonomia.especimenes.occurrence_id',
                'taxonomia.especimenes.colector',
                'taxonomia.especimenes.individual_count',
                'taxonomia.especimenes.disposition',
                'taxonomia.especimenes.occurrence_status',
                'taxonomia.especimenes.specimen_notes',
                'taxonomia.especimenes.country',
                'taxonomia.especimenes.locality_name',
                'taxonomia.especimenes.decimal_latitude',
                'taxonomia.especimenes.decimal_longitude',
                'taxonomia.taxones.nombre_cientifico',
            ])
            ->join('taxonomia.taxones', 'taxonomia.taxones.id', '=', 'taxonomia.especimenes.taxon_id')
            ->whereNotNull('taxonomia.especimenes.occurrence_id')
            ->get();

        return $filas
            ->map(function ($fila): DatosEspecimenProveedor {
                [$family, $genus] = $this->obtenerFamiliaYGenero($fila->taxon_id);

                return $this->traducir($fila, $family, $genus);
            })
            ->all();
    }

    private function traducir(mixed $fila, ?string $family, ?string $genus): DatosEspecimenProveedor
    {
        return new DatosEspecimenProveedor(
            especimenId: $fila->id,
            occurrenceId: $fila->occurrence_id,
            scientificName: $fila->nombre_cientifico,
            individualCount: (int) ($fila->individual_count ?? 0),
            typeStatus: $fila->disposition,      // ACL: disposition del Supplier → typeStatus del Customer
            typeNotes: null,                      // sin campo equivalente en el Supplier
            specimenNotes: $fila->specimen_notes,
            samplingProtocol: null,               // sin campo equivalente en el Supplier
            recordedBy: $fila->colector,          // ACL: colector del Supplier → recordedBy del Customer
            occurrenceStatus: $fila->occurrence_status ?? 'present',
            family: $family,
            genus: $genus,
            country: $fila->country,
            localityName: $fila->locality_name,
            decimalLatitude: $fila->decimal_latitude !== null ? (float) $fila->decimal_latitude : null,
            decimalLongitude: $fila->decimal_longitude !== null ? (float) $fila->decimal_longitude : null,
        );
    }

    /** @return array{0: ?string, 1: ?string} */
    private function obtenerFamiliaYGenero(string $taxonId): array
    {
        $ancestors = DB::select('
            WITH RECURSIVE ancestors AS (
                SELECT id, nombre_cientifico, rango, padre_id
                FROM taxonomia.taxones
                WHERE id = ?
                UNION ALL
                SELECT t.id, t.nombre_cientifico, t.rango, t.padre_id
                FROM taxonomia.taxones t
                INNER JOIN ancestors a ON t.id = a.padre_id
            )
            SELECT nombre_cientifico, rango FROM ancestors
            WHERE rango IN (\'familia\', \'genero\')
        ', [$taxonId]);

        $family = null;
        $genus = null;

        foreach ($ancestors as $row) {
            if ($row->rango === 'familia') {
                $family = $row->nombre_cientifico;
            } elseif ($row->rango === 'genero') {
                $genus = $row->nombre_cientifico;
            }
        }

        return [$family, $genus];
    }
}
