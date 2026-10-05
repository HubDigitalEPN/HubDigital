@props(['nombre' => null, 'familiaVisible' => false, 'familiaOriginal' => null])
@php($referencia = app(\Modules\CatalogoPublico\Application\Services\ReferenciaTaxonomicaPublica::class)->para($nombre, $familiaVisible))
@if($referencia)
    <details class="atlas-taxonomic-reference">
        <summary>Autoridad y revisión del nombre</summary>
        <dl>
            <div><dt>Nombre publicado</dt><dd>{{ $referencia['original'] }}</dd></div>
            @if($familiaVisible && $familiaOriginal)<div><dt>Familia publicada en el catálogo</dt><dd>{{ $familiaOriginal }}</dd></div>@endif
            <div><dt>Nombre de referencia externo</dt><dd>{{ $referencia['candidato'] ?? 'Sin correspondencia resuelta' }} {{ $referencia['rango_referencia'] ? '('.$referencia['rango_referencia'].')' : '' }}</dd></div>
            @if($referencia['familia_referencia'])<div><dt>Familia de referencia externa</dt><dd>{{ $referencia['familia_referencia'] }}</dd></div>@endif
            <div><dt>Fuente y fecha</dt><dd>{{ $referencia['fuente'] }} · {{ $referencia['fecha'] }}</dd></div>
            <div><dt>Decisión curatorial</dt><dd>{{ $referencia['decision'] }}</dd></div>
        </dl>
        <p>{{ $referencia['interpretacion'] }}</p>
        <p>{{ $referencia['limite'] }}</p>
        <a href="{{ $referencia['url'] }}" target="_blank" rel="noopener noreferrer">Consulta de referencia {{ $referencia['evidencia'] }}</a>
    </details>
@endif
