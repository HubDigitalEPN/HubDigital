@props(['url' => null, 'nombre' => ''])
@if(is_string($url) && trim($url) !== '')
    <figure class="collection-photograph-compact" data-fotografia-ejemplar="{{ $nombre }}">
        <img src="{{ $url }}" alt="Fotografía publicada de {{ $nombre }}" width="320" height="320" loading="lazy" decoding="async">
    </figure>
@endif
