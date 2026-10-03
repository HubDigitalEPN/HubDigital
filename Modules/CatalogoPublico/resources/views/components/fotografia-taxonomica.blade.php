@props(['imagen' => [], 'taxon' => [], 'retrato' => false, 'mostrarDescripcion' => true])

@php
    $tieneFotografia = ($imagen['foto_real'] ?? false) && ($imagen['morfologia'] ?? false)
        && is_string($imagen['url'] ?? null) && trim($imagen['url']) !== '';
    $fuenteFotografia = $imagen['fuente_url'] ?? $imagen['fuente'] ?? null;
    $licenciaFotografia = $imagen['licencia_url'] ?? null;
    $enlaceFuenteFotografia = is_string($fuenteFotografia) && in_array(parse_url($fuenteFotografia, PHP_URL_SCHEME), ['https', 'http'], true);
    $enlaceLicenciaFotografia = is_string($licenciaFotografia) && in_array(parse_url($licenciaFotografia, PHP_URL_SCHEME), ['https', 'http'], true);
@endphp

@if($tieneFotografia)
    <figure class="{{ $retrato ? 'collection-reference-photograph' : 'atlas-taxon-illustration' }}" data-fotografia-taxonomica="{{ $imagen['species'] ?? '' }}">
        <img class="{{ $retrato ? 'collection-representation-portrait' : '' }}" src="{{ $imagen['url'] }}" alt="{{ $imagen['alt'] }}" width="320" height="320" loading="lazy" decoding="async">
        <figcaption>
            <span>@if(($imagen['family'] ?? '') !== ''){{ $imagen['family'] }} — @endif @if(($imagen['genus'] ?? '') !== '')<em>{{ $imagen['genus'] }}</em> — @endif <em>{{ $imagen['species'] ?? '' }}</em></span>
            <p>Fotografía de referencia</p>
            @if($mostrarDescripcion && ($imagen['descripcion'] ?? '') !== '')<p>{{ $imagen['descripcion'] }}</p>@endif
            <details class="collection-representation-lineage">
                <summary>Fuente y licencia</summary>
                <p>Referencia de una fuente externa.</p>
                @if(($imagen['credito_ecuador'] ?? false) && ($imagen['autor'] ?? '') !== '')<p>Fotografía de referencia registrada en Ecuador. Autoría: {{ $imagen['autor'] }}</p>@endif
                @if($enlaceFuenteFotografia)<p><a href="{{ $fuenteFotografia }}" target="_blank" rel="noopener noreferrer">Fuente de la fotografía de {{ $imagen['species'] ?? 'este taxón' }}</a></p>@endif
                @if(($imagen['licencia'] ?? '') !== '')<p>Licencia: @if($enlaceLicenciaFotografia)<a href="{{ $licenciaFotografia }}" target="_blank" rel="noopener noreferrer">{{ $imagen['licencia'] }}</a>@else{{ $imagen['licencia'] }}@endif</p>@endif
                @if(($imagen['cambios'] ?? '') !== '')<p>{{ $imagen['cambios'] }}</p>@endif
            </details>
        </figcaption>
    </figure>
@else
    <p class="atlas-tree-hint">No hay una fotografía identificada disponible para este taxón.</p>
@endif
