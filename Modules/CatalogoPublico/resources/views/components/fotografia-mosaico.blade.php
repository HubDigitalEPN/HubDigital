@props(['taxon' => [], 'fotos' => [], 'descripcionInicial' => '', 'limite' => 4, 'retrato' => false, 'contexto' => 'mosaico', 'compacto' => false])
@php
    $fotosPublicas = array_values(array_filter(array_slice($fotos, 0, 4), static fn (array $foto): bool => ($foto['foto_real'] ?? false) && ($foto['morfologia'] ?? false) && is_string($foto['url'] ?? null) && $foto['url'] !== ''));
    $limiteFotografias = max(1, min(4, (int) $limite));
    $claveFotografias = hash('sha256', serialize([$contexto, $taxon, $fotosPublicas, $limiteFotografias, (bool) $retrato, (bool) $compacto]));
@endphp
<div wire:key="fotografias-{{ $claveFotografias }}" x-data="portalFotografias(@js($taxon), @js($fotosPublicas))" :aria-busy="cargando.toString()">
    @if($contexto === 'composicion')
        <div class="collection-loading" x-show="cargando" x-cloak><span class="collection-loading-indicator" role="status"><span class="atlas-spinner" aria-hidden="true"></span><span class="sr-only">Buscando fotografías de la selección</span></span></div>
    @endif
    <div class="{{ $retrato ? 'collection-reference-photograph' : 'atlas-taxon-mosaic' }}" aria-label="Fotografías identificadas de referencia">
        <template x-for="foto in fotos.slice(0, {{ $limiteFotografias }})" :key="foto.url">
            <figure class="{{ $retrato ? 'collection-reference-photograph' : 'atlas-taxon-illustration' }} {{ $compacto ? 'collection-photograph-compact' : '' }}" :data-fotografia-taxonomica="foto.species">
                <img class="{{ $retrato ? 'collection-representation-portrait' : '' }}" :src="foto.url" :alt="foto.alt" width="320" height="320" loading="lazy" decoding="async">
                <figcaption>
                    @if(!$compacto)
                        <span x-show="foto.family"><span x-text="foto.family"></span> — </span><span x-show="foto.genus"><em x-text="foto.genus"></em> — </span><em x-text="foto.species"></em>
                        <p>Fotografía de referencia</p>
                    @endif
                    <small class="collection-photograph-credit" :title="foto.cambios || ''" :data-cambios="foto.cambios || ''">
                        <span x-show="foto.credito_ecuador && foto.autor">Ecuador · <span x-text="foto.autor"></span> · </span>
                        <a :href="foto.fuente_url || foto.fuente" target="_blank" rel="noopener noreferrer">Referencia fotográfica</a>
                        <span x-show="foto.licencia"> · <a :href="foto.licencia_url" target="_blank" rel="noopener noreferrer" x-text="foto.licencia"></a></span>
                    </small>
                </figcaption>
            </figure>
        </template>
    </div>
    @if(!$compacto)
    <p class="atlas-mosaic-note" x-show="cargando" role="status">Buscando fotografías identificadas de este taxón…</p>
    <p class="atlas-mosaic-note" x-show="!cargando && fotos.length === 0" x-cloak>No hay una fotografía identificada disponible para este taxón.</p>
    <p class="atlas-mosaic-note" x-show="error" x-text="error" x-cloak role="status"></p>
    @if($retrato)
        <p class="atlas-taxa-note" x-show="!cargando && fotos.length > 0" x-text="fotos[0]?.descripcion || 'Fotografía identificada de referencia externa.'"></p>
    @else
        <p class="atlas-taxa-note" x-show="!cargando && (descripcion || @js($descripcionInicial))" x-text="descripcion || @js($descripcionInicial)">{{ $descripcionInicial }}</p>
    @endif
    @endif
</div>
