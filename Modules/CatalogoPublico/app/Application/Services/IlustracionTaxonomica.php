<?php

namespace Modules\CatalogoPublico\Application\Services;

/** Assets compartidos: una especie nueva no requiere generar archivos ni cargar la colección. */
final class IlustracionTaxonomica
{
    private const VERSION_RECURSOS = '20261002-foto1';

    private const GRUPOS = [
        'formicidae' => ['formicidae', 'Formicidae'],
        'coleoptera' => ['coleoptera', 'Coleoptera'],
        'lepidoptera' => ['lepidoptera', 'Lepidoptera'],
        'diptera' => ['diptera', 'Diptera'],
        'orthoptera' => ['orthoptera', 'Orthoptera'],
        'odonata' => ['odonata', 'Odonata'],
        'phasmatodea' => ['phasmatodea', 'Phasmatodea'],
        'phasmida' => ['phasmatodea', 'Phasmatodea'],
        'hemiptera' => ['hemiptera', 'Hemiptera'],
        'araneae' => ['araneae', 'Araneae'],
        'scorpiones' => ['scorpiones', 'Scorpiones'],
        'decapoda' => ['decapoda', 'Decapoda'],
        'isopoda' => ['isopoda', 'Isopoda'],
        'gastropoda' => ['gastropoda', 'Gastropoda'],
        'annelida' => ['annelida', 'Annelida'],
        'nematoda' => ['nematoda', 'Nematoda'],
        'nematomorpha' => ['nematoda', 'gusanos no segmentados'],
        'diplopoda' => ['diplopoda', 'Diplopoda'],
    ];

    public static function paraTaxon(array $taxon): array
    {
        $nombres = [];
        foreach (['species', 'genus', 'family', 'order', 'class', 'phylum', 'kingdom', 'nombre', 'nombre_cientifico', 'scientific_name', 'taxon'] as $campo) {
            if (isset($taxon[$campo]) && is_string($taxon[$campo])) {
                $nombres[] = mb_strtolower(trim($taxon[$campo]));
            }
        }
        foreach (['ancestros', 'jerarquia', 'taxonomia'] as $campo) {
            foreach ((is_array($taxon[$campo] ?? null) ? $taxon[$campo] : []) as $nodo) {
                if (is_array($nodo) && is_string($nodo['nombre'] ?? null)) {
                    $nombres[] = mb_strtolower(trim($nodo['nombre']));
                }
            }
        }
        // El grupo más específico disponible prevalece sobre el filo.
        foreach (self::GRUPOS as $nombre => [$archivo, $grupo]) {
            if (in_array($nombre, $nombres, true)) {
                return self::descripcion($archivo.'.webp', $grupo);
            }
        }

        // No inventar una morfología de especie cuando solo conocemos su nombre.
        return self::descripcion('invertebrados.svg', 'invertebrados', false);
    }

    public static function catalogo(): array
    {
        return array_values(array_unique(array_column(self::GRUPOS, 0)));
    }

    /** Apoyo visual cuando no hay fotografías publicadas; nunca se presenta como evidencia. */
    public static function mosaicoParaTaxon(array $taxon): array
    {
        $imagen = self::paraTaxon($taxon);
        if ($imagen['grupo'] === 'Formicidae') {
            return array_map(static fn (int $variante) => self::descripcion('formicidae-'.$variante.'.webp', 'Formicidae'), range(1, 4));
        }
        return [$imagen];
    }

    /** Hasta cuatro grupos presentes en la selección; nunca un surtido global supuesto. */
    public static function mosaicoParaSeleccion(iterable $linajes): array
    {
        $grupos = [];
        foreach ($linajes as $linaje) {
            $imagen = self::paraTaxon($linaje);
            if (! $imagen['morfologia']) continue;
            $clave = $imagen['grupo'];
            $grupos[$clave] ??= ['imagen' => $imagen, 'total' => 0];
            $grupos[$clave]['total'] += (int) ($linaje['total'] ?? 1);
        }
        if ($grupos === []) return self::mosaicoParaTaxon([]);
        if (count($grupos) === 1 && isset($grupos['Formicidae'])) return self::mosaicoParaTaxon(['family' => 'Formicidae']);
        uasort($grupos, static fn (array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp($a['imagen']['grupo'], $b['imagen']['grupo']));
        return array_column(array_slice($grupos, 0, 4), 'imagen');
    }

    private static function descripcion(string $archivo, string $grupo, bool $morfologia = true): array
    {
        return [
            'url' => '/images/taxonomia/'.$archivo.($morfologia ? '?v='.self::VERSION_RECURSOS : ''),
            'alt' => $morfologia
                ? 'Representación fotorrealista generada de '.$grupo.'. No es una fotografía de un ejemplar ni una identificación de la especie.'
                : 'Diagrama taxonómico de invertebrados. Este taxón aún no dispone de una ilustración morfológica específica.',
            'grupo' => $grupo,
            'representativa' => true,
            'morfologia' => $morfologia,
            'licencia' => 'Recurso educativo generado para HubDigital',
            'clase' => 'ilustracion-taxonomica',
        ];
    }
}
