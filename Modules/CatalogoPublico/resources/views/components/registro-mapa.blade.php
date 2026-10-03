<article class="atlas-record-card" wire:key="registro-mapa-{{ $registro->especimen_id }}">
    <h3>{{ $registro->occurrence_id ?: 'Código reservado' }}</h3><p><em>{{ $registro->scientific_name ?: 'Identificación reservada o pendiente' }}</em></p>
    @if($registro->taxon_en_revision ?? false)<p class="atlas-data-warning">Dato original por revisar; excluido de riqueza e identificación a especie.</p>@endif
    @if($registro->event_date && ((int) substr($registro->event_date, 0, 4) < 1800 || $registro->event_date > date('Y-m-d')))<p class="atlas-data-warning">Fecha original pendiente de revisión; excluida de indicadores temporales.</p>@endif
    @if($fotos !== [])
        <div class="atlas-record-photos">
            @foreach($fotos as $foto)
                <a href="{{ $foto['url'] }}" target="_blank" rel="noopener noreferrer" title="{{ $foto['nombre'] }}" aria-label="Ampliar foto de {{ $registro->occurrence_id }}"><img src="{{ $foto['url'] }}" alt="Fotografía publicada del espécimen {{ $registro->occurrence_id }}" loading="lazy" decoding="async" width="320" height="200"></a>
            @endforeach
        </div>
    @endif
    <dl>
        @foreach(['event_date' => 'Fecha original', 'recorded_by' => 'Colector', 'country' => 'País', 'state_province' => 'Provincia', 'locality_excel' => 'Localidad original', 'locality_inec' => 'Localidad INEC', 'locality_inec_reference' => 'Referencia INEC', 'decimal_latitude' => 'Latitud', 'decimal_longitude' => 'Longitud', 'coordinate_reference' => 'Referencia de coordenadas', 'elevation_min_m' => 'Elevación mín. (m)', 'elevation_max_m' => 'Elevación máx. (m)', 'sampling_protocol' => 'Método de colecta', 'individual_count' => 'Individuos', 'type_status' => 'Condición de tipo', 'type_notes' => 'Notas de tipo', 'specimen_notes' => 'Notas del espécimen', 'occurrence_status' => 'Estado', 'caste' => 'Casta', 'life_stage' => 'Estadio'] as $campo => $etiqueta)
            @if($registro->{$campo} !== null && $registro->{$campo} !== '')
                @php($valor = match($campo) {
                    'type_status' => \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::tipo($registro->{$campo}),
                    'occurrence_status' => \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::estado($registro->{$campo}),
                    'life_stage' => \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::etapa($registro->{$campo}),
                    default => $registro->{$campo},
                })
                <div><dt>{{ $etiqueta }}</dt><dd>{{ $valor }}</dd></div>
            @endif
        @endforeach
    </dl>
</article>
