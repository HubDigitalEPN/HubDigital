<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Application\Ports\ProveedorJerarquiaDeEspecimenPort;
use Modules\CatalogoPublico\Domain\ValueObjects\JerarquiaTaxonomica;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;

final class JerarquiaDeEspecimenAdapter implements ProveedorJerarquiaDeEspecimenPort
{
    public function obtener(string $occurrenceID): ?JerarquiaTaxonomica
    {
        $rangosCanonicos = [
            RangoTaxonomico::Species->rangoBD(),
            RangoTaxonomico::Genus->rangoBD(),
            RangoTaxonomico::Family->rangoBD(),
            RangoTaxonomico::Order->rangoBD(),
            RangoTaxonomico::Class_->rangoBD(),
            RangoTaxonomico::Phylum->rangoBD(),
        ];

        // CTE recursivo: sube desde el taxón del espécimen hasta su raíz,
        // conservando únicamente los nodos cuyo `rango` es canónico.
        $sql = <<<'SQL'
            WITH RECURSIVE cadena AS (
                SELECT tx.id, tx.rango, tx.nombre_cientifico, tx.padre_id, 0 AS profundidad
                FROM taxonomia.especimenes te
                JOIN divulgacion.especimenes_divulgables ed ON ed.especimen_id = te.id
                JOIN taxonomia.taxones tx ON tx.id = te.taxon_id
                WHERE te.occurrence_id = ?
                UNION ALL
                SELECT p.id, p.rango, p.nombre_cientifico, p.padre_id, c.profundidad + 1
                FROM cadena c
                JOIN taxonomia.taxones p ON p.id = c.padre_id
                WHERE c.profundidad < 20
            )
            SELECT rango, nombre_cientifico
            FROM cadena
            WHERE rango = ANY(?)
        SQL;

        $filas = DB::select($sql, [$occurrenceID, '{'.implode(',', $rangosCanonicos).'}']);

        if ($filas === []) {
            return null;
        }

        $porRango = [];
        foreach ($filas as $fila) {
            $porRango[$fila->rango] = $fila->nombre_cientifico;
        }

        foreach ($rangosCanonicos as $rangoBD) {
            if (! isset($porRango[$rangoBD])) {
                return null;
            }
        }

        $genus = $porRango[RangoTaxonomico::Genus->rangoBD()];
        $scientificName = $porRango[RangoTaxonomico::Species->rangoBD()];

        if (! str_starts_with($scientificName, $genus.' ')) {
            $scientificName = $genus.' '.$scientificName;
        }

        $epithet = substr($scientificName, strlen($genus) + 1);

        try {
            return JerarquiaTaxonomica::desde(
                phylum: $porRango[RangoTaxonomico::Phylum->rangoBD()],
                class: $porRango[RangoTaxonomico::Class_->rangoBD()],
                order: $porRango[RangoTaxonomico::Order->rangoBD()],
                family: $porRango[RangoTaxonomico::Family->rangoBD()],
                genus: $genus,
                specificEpithet: $epithet,
                scientificName: $scientificName,
            );
        } catch (\Throwable) {
            return null;
        }
    }
}
