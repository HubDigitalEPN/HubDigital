@php
    $mostrarIdentificacion = $mostrarIdentificacion ?? true;
    $permitirFicha = $permitirFicha ?? true;
@endphp
<article class="atlas-record-card" wire:key="registro-mapa-{{ $registro->especimen_id }}">
    <h3>{{ $registro->occurrence_id ?: 'Código reservado' }}</h3>@if($mostrarIdentificacion)<p><em>{{ $registro->scientific_name ?: 'Identificación reservada o pendiente' }}</em></p>@endif
    @if($registro->taxon_en_revision ?? false)<p class="atlas-data-warning">Dato original por revisar; excluido de riqueza e identificación a especie.</p>@endif
    <x-catalogopublico::referencia-taxonomica :nombre="$registro->scientific_name" :familia-visible="!empty($familiaPublica)" :familia-original="$familiaPublica ?? null" />
    @if($registro->event_date && ((int) substr($registro->event_date, 0, 4) < 1800 || $registro->event_date > date('Y-m-d')))<p class="atlas-data-warning">Fecha original pendiente de revisión; excluida de indicadores temporales.</p>@endif
    @if($fotos !== [])
        <div class="atlas-record-photos">
            @foreach($fotos as $foto)
                <a href="{{ $foto['url'] }}" target="_blank" rel="noopener noreferrer" title="{{ $foto['nombre'] }}" aria-label="Ampliar foto de {{ $registro->occurrence_id }}"><img src="{{ $foto['url'] }}" alt="Fotografía publicada del espécimen {{ $registro->occurrence_id }}" loading="lazy" decoding="async" width="320" height="200"></a>
            @endforeach
        </div>
    @endif
    <dl>
        @foreach(['event_date' => 'Fecha original', 'recorded_by' => 'Colector', 'country' => 'País', 'state_province' => 'Provincia', 'locality_name' => 'Localidad registrada', 'locality_excel' => 'Localidad original', 'locality_inec' => 'Localidad INEC', 'locality_inec_reference' => 'Referencia INEC', 'decimal_latitude' => 'Latitud', 'decimal_longitude' => 'Longitud', 'coordinate_reference' => 'Referencia de coordenadas', 'elevation_min_m' => 'Elevación mín. (m)', 'elevation_max_m' => 'Elevación máx. (m)', 'sampling_protocol' => 'Método de colecta', 'individual_count' => 'Individuos', 'type_status' => 'Condición de tipo', 'disposition' => 'Disposición', 'type_notes' => 'Notas de tipo', 'specimen_notes' => 'Notas del espécimen', 'occurrence_status' => 'Estado original', 'caste' => 'Casta', 'life_stage' => 'Estadio'] as $campo => $etiqueta)
            @if(($registro->{$campo} !== null && $registro->{$campo} !== '') || ($campo === 'type_status' && ($registro->type_status_visible ?? false)))
                @php
                    $valor = match($campo) {
                    'type_status' => \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::tipo($registro->{$campo}),
                    'disposition' => \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::disposicion($registro->{$campo}),
                    'occurrence_status' => \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::estado($registro->{$campo}),
                    'life_stage' => \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::etapa($registro->{$campo}),
                    'sampling_protocol' => \Modules\CatalogoPublico\Infrastructure\ProtocoloColectaPublico::etiqueta($registro->{$campo}),
                    default => $registro->{$campo},
                    };
                @endphp
                <div><dt>{{ $etiqueta }}</dt><dd>{{ $valor }}@if($campo === 'sampling_protocol' && $valor !== $registro->{$campo}) <small>Valor original: {{ $registro->{$campo} }}</small>@endif</dd></div>
            @endif
        @endforeach
    </dl>
    @if($permitirFicha)<button type="button" wire:click="abrirFichaRegistro(@js($registro->especimen_id))" class="portal-record-open">Abrir ficha del registro</button>@endif
</article>
