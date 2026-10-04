<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

/** Alias de filtros: sólo traduce términos conocidos, sin adjudicar un tipo a datos desconocidos. */
final class CondicionMaterialPublica
{
    public static function tipoFiltro(?string $valor): ?string
    {
        return match (mb_strtolower(trim($valor ?? ''))) {
            'holotipo' => 'holotype',
            'paratipo' => 'paratype',
            'alotipo' => 'allotype',
            'sintipo' => 'syntype',
            'lectotipo' => 'lectotype',
            'paralectotipo' => 'paralectotype',
            'neotipo' => 'neotype',
            default => $valor,
        };
    }

    public static function disposicionFiltro(?string $valor): ?string
    {
        return match (mb_strtolower(trim($valor ?? ''))) {
            'en la colección', 'en la coleccion', 'en colección', 'en coleccion', 'in collection' => 'in_collection',
            'en préstamo', 'en prestamo', 'on loan' => 'on_loan',
            'desaparecido', 'missing' => 'missing',
            'consumido', 'used up' => 'used_up',
            'destruido', 'destroyed' => 'destroyed',
            'dado de baja', 'deaccessioned' => 'deaccessioned',
            default => $valor,
        };
    }
}
