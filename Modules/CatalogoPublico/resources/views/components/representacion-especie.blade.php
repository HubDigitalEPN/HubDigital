@props(['nombre', 'jerarquia' => [], 'ilustracion' => null, 'contexto' => 'ficha-especie'])
@php
    $linajeRepresentacion = \Modules\CatalogoPublico\Application\Services\DendrogramaTaxonomico::linajePublico($jerarquia, $nombre);
    $imagenRepresentacion = $ilustracion ?? \Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::paraTaxon(['species' => $nombre, 'ancestros' => $linajeRepresentacion]);
    $tieneMorfologiaRepresentacion = ($imagenRepresentacion['foto_real'] ?? false) && ($imagenRepresentacion['morfologia'] ?? false) && is_string($imagenRepresentacion['url'] ?? null) && $imagenRepresentacion['url'] !== '';
@endphp
<figure class="collection-taxonomic-representation" data-representacion-taxon="{{ $nombre }}" aria-label="Fotografía disponible y clasificación pública de {{ $nombre }}">
    <figcaption><strong class="collection-representation-name">{{ $nombre }}</strong></figcaption>
    @if($tieneMorfologiaRepresentacion)
        <x-catalogopublico::fotografia-taxonomica :imagen="$imagenRepresentacion" :taxon="$jerarquia" :retrato="true" />
    @else
        <x-catalogopublico::fotografia-mosaico :taxon="$imagenRepresentacion['taxon_consulta'] ?? ['species' => $nombre, 'ancestros' => $linajeRepresentacion]" :fotos="[]" :limite="1" :retrato="true" :contexto="$contexto" />
    @endif
    <details class="collection-representation-lineage" @if(!$tieneMorfologiaRepresentacion) open @endif>
        <summary>Clasificación pública <span>{{ count($linajeRepresentacion) }} rangos</span></summary>
        <dl>
        @foreach($linajeRepresentacion as $nodoRepresentacion)
            <div><dt>{{ $nodoRepresentacion['rango'] }}</dt><dd>{{ $nodoRepresentacion['nombre'] }}</dd></div>
        @endforeach
        </dl>
    </details>
</figure>
