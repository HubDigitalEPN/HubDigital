<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Services;

/** Referencia del grupo público; nunca se presenta como identificación del ejemplar. */
final class ReferenciaVisualSeleccion
{
    public static function para(array $taxon): ?array
    {
        $filo = $taxon['phylum'] ?? null;
        if (! is_string($filo) || trim($filo) === '') return null;
        $fotografia = IlustracionTaxonomica::paraTaxon(['phylum' => $filo]);
        if (($fotografia['foto_real'] ?? false) && ($fotografia['url'] ?? '') !== '') {
            return ['url' => $fotografia['url'], 'alt' => $fotografia['alt'] ?? 'Fotografía de referencia del filo '.$filo,
                'texto' => 'Fotografía de referencia del filo '.$filo.'. No corresponde necesariamente al taxón consultado ni a un ejemplar del laboratorio.',
                'fuente' => $fotografia['fuente_url'] ?? $fotografia['fuente'] ?? null,
                'autor' => $fotografia['autor'] ?? null, 'licencia' => $fotografia['licencia'] ?? null,
                'licencia_url' => $fotografia['licencia_url'] ?? null];
        }
        if (strcasecmp(trim($filo), 'Nematomorpha') !== 0) return null;
        return ['url' => asset('images/nematomorpha-reference-generated-20261005.png'),
            'alt' => 'Imagen fotorrealista generada de un nematomorfo delgado enrollado sobre una piedra húmeda',
            'texto' => 'Nematomorpha: gusanos de cuerpo largo y fino, semejante a una crin. En las formas de agua dulce, las larvas crecen dentro de insectos; los adultos salen al agua para reproducirse. Ilustración generada con IA.',
            'origen' => 'No es una fotografía de un ejemplar de la colección ni acredita una identificación.',
            'fuente' => 'https://entomology.mgcafe.uky.edu/ef613', 'autor' => null, 'licencia' => null, 'licencia_url' => null];
    }
}
