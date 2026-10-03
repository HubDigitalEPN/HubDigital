@props(['imagen' => [], 'taxon' => [], 'retrato' => false, 'mostrarDescripcion' => true, 'compacto' => false])

@php
    $tieneFotografia = ($imagen['foto_real'] ?? false) && ($imagen['morfologia'] ?? false)
        && is_string($imagen['url'] ?? null) && trim($imagen['url']) !== '';
    $fuenteFotografia = $imagen['fuente_url'] ?? $imagen['fuente'] ?? null;
    $licenciaFotografia = $imagen['licencia_url'] ?? null;
    $enlaceFuenteFotografia = is_string($fuenteFotografia) && in_array(parse_url($fuenteFotografia, PHP_URL_SCHEME), ['https', 'http'], true);
    $enlaceLicenciaFotografia = is_string($licenciaFotografia) && in_array(parse_url($licenciaFotografia, PHP_URL_SCHEME), ['https', 'http'], true);
@endphp

@if($tieneFotografia)
    <figure class="{{ $retrato ? 'collection-reference-photograph' : 'atlas-taxon-illustration' }} {{ $compacto ? 'collection-photograph-compact' : '' }}" data-fotografia-taxonomica="{{ $imagen['species'] ?? '' }}">
        <img class="{{ $retrato ? 'collection-representation-portrait' : '' }}" src="{{ $imagen['url'] }}" alt="{{ $imagen['alt'] }}" width="320" height="320" loading="lazy" decoding="async">
        <figcaption>
            @if(!$compacto)
                <span>@if(($imagen['family'] ?? '') !== ''){{ $imagen['family'] }} — @endif @if(($imagen['genus'] ?? '') !== '')<em>{{ $imagen['genus'] }}</em> — @endif <em>{{ $imagen['species'] ?? '' }}</em></span>
                <p>Fotografía de referencia</p>
                @if($mostrarDescripcion && ($imagen['descripcion'] ?? '') !== '')<p>{{ $imagen['descripcion'] }}</p>@endif
            @endif
            <small class="collection-photograph-credit" title="{{ $imagen['cambios'] ?? '' }}" data-cambios="{{ $imagen['cambios'] ?? '' }}">
                @if(($imagen['credito_ecuador'] ?? false) && ($imagen['autor'] ?? '') !== '')<span>Ecuador · {{ $imagen['autor'] }}</span> · @endif
                @if($enlaceFuenteFotografia)<a href="{{ $fuenteFotografia }}" target="_blank" rel="noopener noreferrer">Referencia fotográfica</a>@endif
                @if(($imagen['licencia'] ?? '') !== '') · @if($enlaceLicenciaFotografia)<a href="{{ $licenciaFotografia }}" target="_blank" rel="noopener noreferrer">{{ $imagen['licencia'] }}</a>@else{{ $imagen['licencia'] }}@endif @endif
            </small>
        </figcaption>
    </figure>
@elseif(!$compacto)
    <p class="atlas-tree-hint">No hay una fotografía identificada disponible para este taxón.</p>
@endif
