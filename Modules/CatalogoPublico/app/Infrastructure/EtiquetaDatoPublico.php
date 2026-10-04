<?php

namespace Modules\CatalogoPublico\Infrastructure;

/** Traduce la presentación de valores conocidos; conserva el dato original. */
final class EtiquetaDatoPublico
{
    public static function tipo(?string $valor): string
    {
        return match (strtolower(trim($valor ?? ''))) {
            'holotype' => 'Holotipo',
            'paratype' => 'Paratipo',
            'allotype' => 'Alotipo',
            'syntype' => 'Sintipo',
            'lectotype' => 'Lectotipo',
            'paralectotype' => 'Paralectotipo',
            'neotype' => 'Neotipo',
            '' => 'No informado',
            default => (string) $valor,
        };
    }

    public static function disposicion(?string $valor): string
    {
        return match (strtolower(str_replace('_', ' ', trim($valor ?? '')))) {
            'in collection' => 'En la colección',
            'on loan' => 'En préstamo',
            'missing' => 'Desaparecido',
            'used up' => 'Consumido',
            'destroyed' => 'Destruido',
            'deaccessioned' => 'Dado de baja',
            '' => 'No informado',
            default => (string) $valor,
        };
    }

    public static function estado(?string $valor): string
    {
        return match (strtolower(trim($valor ?? ''))) {
            'present' => 'Presente',
            'absent' => 'Ausente',
            'in_collection', 'in collection' => 'En la colección',
            '' => 'No informado',
            default => (string) $valor,
        };
    }

    public static function etapa(?string $valor): string
    {
        return match (strtolower(trim($valor ?? ''))) {
            'adult' => 'Adulto',
            'juvenile' => 'Juvenil',
            'larva', 'larval' => 'Larva',
            'pupa', 'pupal' => 'Pupa',
            'egg' => 'Huevo',
            'nymph' => 'Ninfa',
            '' => 'No informado',
            default => (string) $valor,
        };
    }
}
