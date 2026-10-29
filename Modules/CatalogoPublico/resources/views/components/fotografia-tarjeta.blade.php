@props(['nombre', 'nivel', 'jerarquia' => [], 'url' => null, 'compacto' => false, 'datos' => []])
@php
    $linajeFoto = array_replace($jerarquia, [$nivel => $nombre]);
    $referenciaTarjeta = \Modules\CatalogoPublico\Application\Services\FotografiaTarjeta::para($nombre, $nivel, $linajeFoto);
    $fotosTarjeta = \Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::mosaicoParaTaxon($linajeFoto);
    $referenciaFilo = $nivel === 'phylum' ? \Modules\CatalogoPublico\Application\Services\ReferenciaVisualSeleccion::para($linajeFoto) : null;
@endphp
<div wire:key="tarjeta-foto-{{ sha1(json_encode([$nivel, $linajeFoto, $url, $compacto])) }}" class="collection-card-photograph {{ $compacto ? 'collection-card-photograph-small' : '' }}" data-foto-tarjeta="{{ $nombre }}">
    @if($url)
        <figure><button type="button" class="block w-full cursor-zoom-in" aria-label="Ampliar fotografía de {{ $nombre }}" x-on:click.prevent.stop="$dispatch('lightbox-open', {url: @js($url), alt: @js($nombre), nombreArchivo: @js($datos['nombre'] ?? ''), atribuciones: @js($datos['atribuciones'] ?? []), invocador: $event.currentTarget})"><img src="{{ $url }}" alt="Ejemplar publicado de {{ $nombre }}" width="480" height="360" loading="lazy" decoding="async"></button><figcaption><button type="button" x-on:click.prevent.stop="$dispatch('lightbox-open', {url: @js($url), alt: @js($nombre), nombreArchivo: @js($datos['nombre'] ?? ''), atribuciones: @js($datos['atribuciones'] ?? []), invocador: $event.currentTarget})" class="collection-photo-credit-button">Ejemplar de la colección</button></figcaption></figure>
    @elseif($referenciaTarjeta)
        <figure><img src="{{ $referenciaTarjeta['url'] }}" alt="{{ $referenciaTarjeta['alt'] }}" width="480" height="360" loading="lazy" decoding="async">
            <figcaption><span>{{ ($referenciaTarjeta['ilustracion'] ?? false) ? 'Ilustración de' : 'Referencia:' }} <em>{{ $referenciaTarjeta['species'] }}</em></span>@if(!($referenciaTarjeta['ilustracion'] ?? false))<small>@if($referenciaTarjeta['autor'] !== ''){{ $referenciaTarjeta['autor'] }} · @endif<a href="{{ $referenciaTarjeta['fuente'] }}" target="_blank" rel="noopener noreferrer">Fuente</a> · <a href="{{ $referenciaTarjeta['licencia_url'] }}" target="_blank" rel="noopener noreferrer">{{ $referenciaTarjeta['licencia'] }}</a></small>@endif</figcaption>
        </figure>
    @else
        <div x-data="portalFotografiaTarjeta(@js($linajeFoto), @js($fotosTarjeta))" :aria-busy="cargando.toString()">
            <template x-if="fotografia && !fallo"><figure>
                <img :src="fotografia.urlRapida || fotografia.url" :alt="fotografia.alt" width="480" height="360" loading="lazy" decoding="async" x-on:error="if (!$el.dataset.alternativa && fotografia.url !== $el.getAttribute('src')) { $el.dataset.alternativa = '1'; $el.src = fotografia.url } else fallo = true">
                <figcaption><span>Referencia: <em x-text="fotografia.species"></em></span><small><span x-text="fotografia.autor_fuente || fotografia.autor"></span> · <a :href="fotografia.fuente_url || fotografia.fuente" target="_blank" rel="noopener noreferrer">Fuente</a> · <a :href="fotografia.licencia_url" x-text="fotografia.licencia" target="_blank" rel="noopener noreferrer"></a></small></figcaption>
            </figure></template>
            @if($referenciaFilo)
                <figure x-show="!fotografia || fallo"><img src="{{ $referenciaFilo['url'] }}" alt="{{ $referenciaFilo['alt'] }}" width="480" height="360" loading="lazy" decoding="async"><figcaption>{{ $nombre }}</figcaption></figure>
            @else
                <div class="collection-card-photo-empty" x-show="!fotografia || fallo" role="status"><span x-text="cargando ? 'Buscando fotografía…' : 'No hay imagen disponible'"></span></div>
            @endif
        </div>
    @endif
</div>
