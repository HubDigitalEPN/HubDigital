<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\DesgloseLocalidad;

/** La autorización de publicación y localidad se aplica antes de entregar este texto. */
final class LocalidadPublica
{
    public static function desdeFila(object $fila): ?string
    {
        // Sin desglose, conservar el campo público usado por las releases anteriores.
        $original = trim($fila->locality_name ?? '') ?: (trim($fila->localidad ?? '') ?: null);
        if (! ($fila->localidad_desglosada ?? false)) return $original;
        return DesgloseLocalidad::unir($fila->localidad_area ?? null, $fila->localidad2 ?? null, $fila->localidad3 ?? null) ?? $original;
    }

    public static function sql(string $alias = 'e'): string
    {
        if (! in_array($alias, ['e', 'te'], true)) throw new \InvalidArgumentException('Alias de localidad no permitido.');
        $original = "COALESCE(NULLIF(btrim({$alias}.locality_name), ''), NULLIF(btrim({$alias}.localidad), ''))";
        return "CASE WHEN {$alias}.localidad_desglosada THEN COALESCE(NULLIF(concat_ws(', ', NULLIF(btrim({$alias}.localidad_area), ''), NULLIF(btrim({$alias}.localidad2), ''), NULLIF(btrim({$alias}.localidad3), '')), ''), {$original}) ELSE {$original} END";
    }
}
