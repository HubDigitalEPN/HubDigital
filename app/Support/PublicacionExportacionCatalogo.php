<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Modules\CatalogoPublico\Infrastructure\CalidadDatoPublico;

final class PublicacionExportacionCatalogo
{
    /** Solo las exportaciones externas exigen la ficha revisada y los nueve datos mínimos. */
    public static function aplicar(Builder $consulta, string $alias = 'te'): Builder
    {
        if (! in_array($alias, ['te', 'e'], true)) throw new \InvalidArgumentException('Alias de exportación no válido.');
        $consulta->where($alias.'.estado_revision', 'confirmada');
        foreach (['occurrence_id', 'located_at', 'preparations', 'country', 'state_province', 'locality_name'] as $campo) {
            $consulta->whereRaw(CalidadDatoPublico::textoValido($alias.'.'.$campo));
        }
        $consulta->whereBetween($alias.'.decimal_latitude', [-90, 90])->whereBetween($alias.'.decimal_longitude', [-180, 180]);
        $nombreValido = CalidadDatoPublico::textoValido('linaje.nombre_cientifico');

        return $consulta->whereRaw("EXISTS (WITH RECURSIVE linaje AS (
            SELECT id, padre_id, rango, nombre_cientifico FROM taxonomia.taxones WHERE id = {$alias}.taxon_id
            UNION SELECT t.id, t.padre_id, t.rango, t.nombre_cientifico FROM taxonomia.taxones t JOIN linaje l ON t.id = l.padre_id
        ) SELECT 1 FROM linaje WHERE rango = 'orden' AND {$nombreValido})");
    }
}
