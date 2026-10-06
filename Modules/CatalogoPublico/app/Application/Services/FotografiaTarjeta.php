<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Services;

/** Referencias verificadas para nombres publicados e históricos, sin cambiar la clasificación local. */
final class FotografiaTarjeta
{
    public static function para(string $nombre, string $nivel, array $jerarquia = []): ?array
    {
        if ($nivel !== 'family') return null;
        foreach (['phylum' => 'Arthropoda', 'class' => 'Insecta', 'order' => 'Coleoptera'] as $rango => $esperado) {
            if (isset($jerarquia[$rango]) && strcasecmp($jerarquia[$rango], $esperado) !== 0) return null;
        }
        if (strcasecmp(trim($nombre), 'Melaryidae') === 0) {
            return ['url' => '/images/taxonomia/tarjetas/melaryidae-ilustracion.webp',
                'alt' => 'Representación de un escarabajo del orden Coleoptera', 'species' => 'Coleoptera',
                'ilustracion' => true, 'autor' => '', 'fuente' => null, 'licencia' => null, 'licencia_url' => null];
        }
        static $fuentes;
        $fuentes ??= require __DIR__.'/FotografiasTarjetas.php';
        $foto = $fuentes[mb_strtolower(trim($nombre))] ?? null;
        if ($foto === null) return null;
        $licencias = [
            'cc0' => ['CC0 1.0', 'https://creativecommons.org/publicdomain/zero/1.0/'],
            'cc-by' => ['CC BY 4.0', 'https://creativecommons.org/licenses/by/4.0/'],
            'cc-by-sa' => ['CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0/'],
        ];
        [$licencia, $urlLicencia] = $licencias[$foto['licencia']];
        return [
            'url' => '/images/taxonomia/tarjetas/'.$foto['archivo'].'.webp',
            'alt' => 'Fotografía de referencia de '.$foto['species'].' para '.$nombre,
            'species' => $foto['species'], 'autor' => trim(preg_replace('/^\(c\)\s*|\s*,?\s*(?:some|all|no) rights reserved.*$/i', '', $foto['autor']) ?? $foto['autor']),
            'fuente' => 'https://www.inaturalist.org/observations/'.$foto['observacion'],
            'licencia' => $licencia, 'licencia_url' => $urlLicencia,
        ];
    }
}
