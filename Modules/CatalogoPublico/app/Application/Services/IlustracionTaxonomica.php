<?php

namespace Modules\CatalogoPublico\Application\Services;

/** Fotografías identificadas de referencia; no reconstruye ni identifica ejemplares locales. */
final class IlustracionTaxonomica
{
    private const VERSION_RECURSOS = '20261002-fuentes1';

    private const RANGOS = [
        'kingdom' => 'kingdom', 'reino' => 'kingdom', 'phylum' => 'phylum', 'filo' => 'phylum',
        'class' => 'class', 'clase' => 'class', 'order' => 'order', 'orden' => 'order',
        'family' => 'family', 'familia' => 'family', 'genus' => 'genus', 'genero' => 'genus', 'género' => 'genus',
        'species' => 'species', 'especie' => 'species',
        'subspecies' => 'subspecies', 'subespecie' => 'subspecies',
        'subgenus' => 'subgenus', 'subgenero' => 'subgenus', 'subgénero' => 'subgenus',
        'subtribe' => 'subtribe', 'subtribu' => 'subtribe', 'tribe' => 'tribe', 'tribu' => 'tribe',
        'subfamily' => 'subfamily', 'subfamilia' => 'subfamily', 'superfamily' => 'superfamily', 'superfamilia' => 'superfamily',
        'infraorder' => 'infraorder', 'infraorden' => 'infraorder', 'suborder' => 'suborder', 'suborden' => 'suborder',
        'superorder' => 'superorder', 'superorden' => 'superorder',
        'infraclass' => 'infraclass', 'infraclase' => 'infraclass', 'subclass' => 'subclass', 'subclase' => 'subclass',
        'superclass' => 'superclass', 'superclase' => 'superclass',
        'subphylum' => 'subphylum', 'subfilo' => 'subphylum', 'subkingdom' => 'subkingdom', 'subreino' => 'subkingdom',
        'epifamily' => 'epifamily', 'epifamilia' => 'epifamily', 'supertribe' => 'supertribe', 'supertribu' => 'supertribe',
        'variety' => 'variety', 'variedad' => 'variety', 'form' => 'form', 'forma' => 'form',
    ];

    public static function paraTaxon(array $taxon): array
    {
        return self::fotografiasParaTaxon($taxon)[0] ?? self::sinFotografia($taxon);
    }

    public static function catalogo(): array
    {
        return array_column(self::fuentes(), 'archivo');
    }

    public static function mosaicoParaTaxon(array $taxon): array
    {
        $fotos = self::fotografiasParaTaxon($taxon);
        return $fotos === [] ? [self::sinFotografia($taxon)] : array_slice($fotos, 0, 4);
    }

    /** Referencias de grupos públicos; una navegación específica restringe la identidad. */
    public static function mosaicoParaSeleccion(iterable $linajes, array $seleccion = []): array
    {
        if (self::normalizarLinaje($seleccion) !== []) return self::mosaicoParaTaxon($seleccion);
        $grupos = [];
        foreach ($linajes as $linaje) {
            $publico = self::normalizarLinaje($linaje);
            // El mosaico general ilustra grupos, sin atribuir fotos a los ejemplares locales.
            $publico = array_intersect_key($publico, array_flip(['kingdom', 'phylum', 'class', 'order', 'family']));
            $fotos = self::fotografiasParaTaxon($publico);
            // Un grupo de la composición debe constar en el linaje público, no deducirse de una foto.
            $gruposPublicos = array_map(self::clave(...), array_values($publico));
            $fotos = array_values(array_filter($fotos, static fn (array $foto): bool => in_array(self::clave($foto['grupo']), $gruposPublicos, true)));
            if ($fotos === []) continue;
            $clave = $fotos[0]['grupo'];
            $grupos[$clave] ??= ['fotos' => $fotos, 'total' => 0];
            $grupos[$clave]['total'] += (int) ($linaje['total'] ?? 1);
        }
        if ($grupos === []) return [self::sinFotografia()];
        uasort($grupos, static fn (array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp($a['fotos'][0]['grupo'], $b['fotos'][0]['grupo']));
        if (count($grupos) === 1) return array_slice(reset($grupos)['fotos'], 0, 4);
        return array_map(static fn (array $grupo): array => $grupo['fotos'][0], array_slice(array_values($grupos), 0, 4));
    }

    public static function describirMosaico(array $fotos): string
    {
        $descripciones = [];
        foreach ($fotos as $foto) {
            if (($foto['foto_real'] ?? false) && ($foto['descripcion'] ?? '') !== '') {
                $descripciones[$foto['species']] = $foto['descripcion'];
            }
        }
        return $descripciones === []
            ? 'Esta selección no dispone de fotografías identificadas de referencia. Puedes explorar su clasificación y los datos públicos de los ejemplares.'
            : implode(' ', $descripciones).' Fotografías de referencia de los taxones ilustrados; los ejemplares de la colección se consultan en sus registros.';
    }

    private static function fotografiasParaTaxon(array $taxon): array
    {
        $linaje = self::normalizarLinaje($taxon);
        if (isset($linaje['_rango_desconocido'])) return [];
        foreach (['form', 'variety', 'subspecies', 'species', 'subgenus', 'genus', 'subtribe', 'tribe', 'supertribe', 'subfamily', 'family', 'epifamily', 'superfamily', 'infraorder', 'suborder', 'order', 'superorder', 'infraclass', 'subclass', 'class', 'superclass', 'subphylum', 'phylum', 'subkingdom', 'kingdom'] as $rango) {
            if (! isset($linaje[$rango])) continue;
            $nombre = self::clave($linaje[$rango]);
            // El nivel más específico es obligatorio: nunca sustituir una especie por otra del grupo.
            $fuentes = array_filter(self::fuentes(), static function (array $foto) use ($rango, $nombre, $linaje): bool {
                if (self::clave($foto[$rango] ?? '') !== $nombre) return false;
                foreach ($linaje as $nivel => $valor) {
                    if (! isset($foto[$nivel]) || self::clave($foto[$nivel]) !== self::clave($valor)) return false;
                }
                return true;
            });
            return array_map(static fn (array $foto): array => self::descripcion($foto, $linaje), array_values($fuentes));
        }
        return [];
    }

    private static function normalizarLinaje(array $taxon): array
    {
        $linaje = [];
        $ancestros = $taxon['ancestros'] ?? $taxon['jerarquia'] ?? $taxon['taxonomia'] ?? null;
        $fuente = is_array($ancestros) ? $ancestros : $taxon;
        foreach ($fuente as $clave => $nodo) {
            $rango = is_array($nodo) ? ($nodo['rango'] ?? $nodo['nivel'] ?? '') : $clave;
            $nombre = is_array($nodo) ? ($nodo['nombre'] ?? $nodo['taxon'] ?? '') : $nodo;
            if (! is_string($rango) || ! is_string($nombre) || trim($nombre) === '') continue;
            $nivel = self::RANGOS[self::clave($rango)] ?? null;
            if ($nivel !== null) $linaje[$nivel] = trim($nombre);
            $ultimoRango = $nivel;
            $ultimoNombre = trim($nombre);
        }
        if (is_array($ancestros) && isset($ultimoNombre) && $ultimoRango === null) {
            $linaje['_rango_desconocido'] = $ultimoNombre;
        }
        if (is_string($taxon['species'] ?? null) && trim($taxon['species']) !== '') {
            $linaje['species'] = trim($taxon['species']);
        }
        return $linaje;
    }

    private static function fuentes(): array
    {
        // Catálogo pequeño y local: abrir una ficha nunca consulta servicios externos en la VM.
        static $fuentes;
        return $fuentes ??= require __DIR__.'/FotografiasTaxonomicas.php';
    }

    private static function descripcion(array $foto, array $linaje): array
    {
        if ((isset($linaje['species']) || isset($linaje['genus'])) && ! isset($linaje['family'])) {
            $foto['family'] = '';
            $foto['descripcion'] = 'Fotografía identificada de '.$foto['species'].'.';
        }
        if (isset($linaje['species']) && ! isset($linaje['genus'])) {
            $foto['genus'] = '';
            $foto['descripcion'] = 'Fotografía identificada de '.$foto['species'].'.';
        }
        return $foto + [
            'url' => '/images/taxonomia/fotografias/'.$foto['archivo'].'.webp?v='.self::VERSION_RECURSOS,
            'alt' => 'Fotografía de '.$foto['species'].($foto['family'] !== '' ? ' ('.$foto['family'].')' : '').'.',
            'representativa' => true, 'morfologia' => true, 'foto_real' => true, 'clase' => 'fotografia-taxonomica',
            'taxon_consulta' => $linaje,
        ];
    }

    private static function sinFotografia(array $taxon = []): array
    {
        return [
            'url' => null, 'alt' => '', 'grupo' => '', 'representativa' => false, 'morfologia' => false,
            'foto_real' => false, 'clase' => 'sin-fotografia', 'family' => '', 'genus' => '', 'species' => '',
            'autor' => '', 'fuente' => '', 'licencia' => '', 'licencia_url' => '', 'descripcion' => '',
            'taxon_consulta' => self::normalizarLinaje($taxon),
        ];
    }

    private static function clave(string $nombre): string
    {
        return mb_strtolower(trim($nombre));
    }
}
