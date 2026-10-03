<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure;

use InvalidArgumentException;

/** La reserva de cualquiera de las dos coordenadas excluye el registro del portal. */
final class ElegibilidadGeograficaPortal
{
    public static function sql(string $especimen = 'te', string $divulgable = 'ed'): string
    {
        foreach ([$especimen, $divulgable] as $alias) {
            if (! preg_match('/\A[a-z_][a-z0-9_.]*\z/D', $alias)) throw new InvalidArgumentException('Alias de consulta no válido.');
        }

        return "($especimen.coordenadas_otras_regiones = false"
            ." AND $especimen.decimal_latitude IS NOT NULL AND $especimen.decimal_longitude IS NOT NULL"
            ." AND $divulgable.decimal_latitude_visible IS TRUE AND $divulgable.decimal_longitude_visible IS TRUE"
            ." AND $especimen.decimal_latitude BETWEEN -90 AND 90"
            ." AND $especimen.decimal_longitude BETWEEN -180 AND 180)";
    }
}
