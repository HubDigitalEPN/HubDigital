<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure;

/** La técnica original de cada ejemplar prevalece sobre una muestra agrupada. */
final class ProtocoloColectaPublico
{
    public static function sql(string $especimen = 'te', string $muestra = 'mc'): string
    {
        return "COALESCE(NULLIF(btrim({$especimen}.sampling_protocol), ''), NULLIF(btrim({$muestra}.sampling_protocol), ''))";
    }
}
