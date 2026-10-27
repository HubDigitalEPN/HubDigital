@props(['url' => null, 'nombre' => '', 'mostrarAusencia' => false, 'datos' => []])
@if(is_string($url) && trim($url) !== '')
    <figure class="collection-photograph-compact" data-fotografia-ejemplar="{{ $nombre }}">
        <img src="{{ $url }}" alt="Fotografía publicada de {{ $nombre }}" width="320" height="320" loading="lazy" decoding="async">
        <x-catalogopublico::atribuciones-imagen :datos="$datos['atribuciones'] ?? []" />
    </figure>
@elseif($mostrarAusencia)
    <div class="collection-photograph-unavailable" role="img" aria-label="No hay imagen disponible de {{ $nombre }}"><span>No hay imagen disponible</span></div>
@endif
