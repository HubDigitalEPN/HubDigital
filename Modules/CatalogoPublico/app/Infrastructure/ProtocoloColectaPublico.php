<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure;

/** Conserva la técnica fuente; agregado y filtro comparten una clave sin distinción de mayúsculas. */
final class ProtocoloColectaPublico
{
    public static function sql(string $especimen = 'te', string $muestra = 'mc'): string
    {
        return "COALESCE(NULLIF(btrim({$especimen}.sampling_protocol), ''), NULLIF(btrim({$muestra}.sampling_protocol), ''))";
    }

    public static function claveSql(string $especimen = 'te', string $muestra = 'mc'): string
    {
        return 'LOWER('.self::sql($especimen, $muestra).')';
    }

    public static function clave(string $protocolo): string
    {
        return mb_strtolower(trim($protocolo), 'UTF-8');
    }
}
