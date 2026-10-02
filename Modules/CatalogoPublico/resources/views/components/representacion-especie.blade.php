@props(['nombre', 'jerarquia' => [], 'ilustracion' => null])
@php
    // Conservar toda la clasificación pública recibida, incluidos rangos intermedios.
    $fuenteLinajeRepresentacion = is_array($jerarquia['ancestros'] ?? null) ? $jerarquia['ancestros'] : $jerarquia;
    $linajeRepresentacion = [];
    $incluyeEspecieRepresentacion = false;
    foreach ($fuenteLinajeRepresentacion as $claveRepresentacion => $datoRepresentacion) {
        $rangoRepresentacion = is_array($datoRepresentacion) ? ($datoRepresentacion['rango'] ?? $datoRepresentacion['nivel'] ?? null) : $claveRepresentacion;
        $nombreRepresentacion = is_array($datoRepresentacion) ? ($datoRepresentacion['nombre'] ?? $datoRepresentacion['taxon'] ?? null) : $datoRepresentacion;
        if (! is_string($rangoRepresentacion) || ! is_string($nombreRepresentacion) || trim($nombreRepresentacion) === '') continue;
        $etiquetaRepresentacion = \Modules\CatalogoPublico\Application\Services\DendrogramaTaxonomico::etiquetaRango($rangoRepresentacion);
        $linajeRepresentacion[] = ['rango' => $etiquetaRepresentacion, 'nombre' => $nombreRepresentacion];
        $incluyeEspecieRepresentacion = $incluyeEspecieRepresentacion || ($etiquetaRepresentacion === 'Especie' && $nombreRepresentacion === $nombre);
    }
    if (! $incluyeEspecieRepresentacion) {
        $linajeRepresentacion[] = ['rango' => 'Especie', 'nombre' => $nombre];
    }
    $imagenRepresentacion = $ilustracion ?? \Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::paraTaxon(['species' => $nombre, 'ancestros' => $linajeRepresentacion]);
    $tieneMorfologiaRepresentacion = (bool) ($imagenRepresentacion['morfologia'] ?? false);
@endphp
<figure class="collection-taxonomic-representation" data-representacion-taxon="{{ $nombre }}" aria-label="Representación y clasificación pública de {{ $nombre }}">
    @if($tieneMorfologiaRepresentacion)
        <img class="collection-representation-portrait" src="{{ $imagenRepresentacion['url'] }}" alt="{{ $imagenRepresentacion['alt'] }}" width="320" height="320" loading="lazy" decoding="async">
    @endif
    <figcaption><strong class="collection-representation-name">{{ $nombre }}</strong><span>@if($tieneMorfologiaRepresentacion)Representación fotorrealista generada de {{ $imagenRepresentacion['grupo'] }}; no identifica la especie ni reproduce el ejemplar.@else No hay una ilustración morfológica disponible; se muestra su linaje público real.@endif</span></figcaption>
    <details class="collection-representation-lineage" @if(!$tieneMorfologiaRepresentacion) open @endif>
        <summary>Clasificación pública <span>{{ count($linajeRepresentacion) }} rangos</span></summary>
        <dl>
        @foreach($linajeRepresentacion as $nodoRepresentacion)
            <div><dt>{{ $nodoRepresentacion['rango'] }}</dt><dd>{{ $nodoRepresentacion['nombre'] }}</dd></div>
        @endforeach
        </dl>
    </details>
</figure>
