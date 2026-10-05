<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

/** Una clave compartida para etiquetas y códigos; nunca modifica el estadio fuente. */
final class EstadioVidaPublico
{
    public static function clave(?string $valor): ?string
    {
        $valor = mb_strtolower(trim($valor ?? ''));
        return match ($valor) {
            '' => null,
            'adulto', 'adulta', 'adult' => 'adult',
            'juvenil', 'juvenile' => 'juvenile',
            'larva', 'larval' => 'larva',
            'pupa', 'pupal' => 'pupa',
            'huevo', 'egg' => 'egg',
            'ninfa', 'nymph' => 'nymph',
            default => $valor,
        };
    }

    public static function claveSql(string $campo): string
    {
        return "CASE LOWER(btrim({$campo})) WHEN 'adulto' THEN 'adult' WHEN 'adulta' THEN 'adult' "
            ."WHEN 'juvenil' THEN 'juvenile' WHEN 'larval' THEN 'larva' WHEN 'pupal' THEN 'pupa' "
            ."WHEN 'huevo' THEN 'egg' WHEN 'ninfa' THEN 'nymph' ELSE LOWER(btrim({$campo})) END";
    }
}
