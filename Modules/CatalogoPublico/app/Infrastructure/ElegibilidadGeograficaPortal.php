<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure;

use InvalidArgumentException;

/** Ocurrencias de cualquier país, con coordenadas y nombre científico públicos. */
final class ElegibilidadGeograficaPortal
{
    public static function sql(string $especimen = 'te', string $divulgable = 'ed'): string
    {
        foreach ([$especimen, $divulgable] as $alias) {
            if (! preg_match('/\A[a-z_][a-z0-9_.]*\z/D', $alias)) throw new InvalidArgumentException('Alias de consulta no válido.');
        }

        return "($especimen.decimal_latitude IS NOT NULL AND $especimen.decimal_longitude IS NOT NULL"
            ." AND $divulgable.decimal_latitude_visible IS TRUE AND $divulgable.decimal_longitude_visible IS TRUE"
            ." AND $divulgable.scientific_name_visible IS TRUE"
            ." AND $especimen.decimal_latitude BETWEEN -90 AND 90"
            ." AND $especimen.decimal_longitude BETWEEN -180 AND 180"
            ." AND EXISTS (SELECT 1 FROM taxonomia.taxones AS portal_nombre_cientifico"
            ." WHERE portal_nombre_cientifico.id = $especimen.taxon_id"
            ." AND NULLIF(regexp_replace(translate(portal_nombre_cientifico.nombre_cientifico,"
            ." chr(160) || chr(8203) || chr(8239) || chr(65279), ''), '[[:space:]]', '', 'g'), '') IS NOT NULL))";
    }
}
