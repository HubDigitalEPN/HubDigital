@props(['nombre', 'nivel', 'jerarquia' => [], 'url' => null, 'compacto' => false])
@php
    $linajeFoto = array_replace($jerarquia, [$nivel => $nombre]);
    $referenciaTarjeta = \Modules\CatalogoPublico\Application\Services\FotografiaTarjeta::para($nombre, $nivel, $linajeFoto);
    $fotosTarjeta = \Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::mosaicoParaTaxon($linajeFoto);
    $referenciaFilo = $nivel === 'phylum' ? \Modules\CatalogoPublico\Application\Services\ReferenciaVisualSeleccion::para($linajeFoto) : null;
@endphp
<div wire:key="tarjeta-foto-{{ sha1(json_encode([$nivel, $linajeFoto, $url, $compacto])) }}" class="collection-card-photograph {{ $compacto ? 'collection-card-photograph-small' : '' }}" data-foto-tarjeta="{{ $nombre }}">
    @if($url)
        <figure><img src="{{ $url }}" alt="Ejemplar publicado de {{ $nombre }}" width="480" height="360" loading="lazy" decoding="async"><figcaption>Ejemplar de la colección</figcaption></figure>
    @elseif($referenciaTarjeta)
        <figure><img src="{{ $referenciaTarjeta['url'] }}" alt="{{ $referenciaTarjeta['alt'] }}" width="480" height="360" loading="lazy" decoding="async">
            <figcaption><span>{{ ($referenciaTarjeta['ilustracion'] ?? false) ? 'Ilustración de' : 'Referencia:' }} <em>{{ $referenciaTarjeta['species'] }}</em></span>@if(!($referenciaTarjeta['ilustracion'] ?? false))<small>@if($referenciaTarjeta['autor'] !== ''){{ $referenciaTarjeta['autor'] }} · @endif<a href="{{ $referenciaTarjeta['fuente'] }}" target="_blank" rel="noopener noreferrer">Fuente</a> · <a href="{{ $referenciaTarjeta['licencia_url'] }}" target="_blank" rel="noopener noreferrer">{{ $referenciaTarjeta['licencia'] }}</a></small>@endif</figcaption>
        </figure>
    @else
        <div x-data="portalFotografiaTarjeta(@js($linajeFoto), @js($fotosTarjeta))" :aria-busy="cargando.toString()">
            <template x-if="fotografia && !fallo"><figure>
                <img :src="fotografia.url" :alt="fotografia.alt" width="480" height="360" loading="lazy" decoding="async" x-on:error="fallo = true">
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
