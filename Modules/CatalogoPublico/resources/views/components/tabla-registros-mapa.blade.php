@props(['registros' => [], 'imagenes' => [], 'total' => 0, 'seleccion' => null])

@php
    // El componente recibe exclusivamente los DTO que ya pasaron aplicarVisibilidad.
    $columnas = [
        'occurrence_id' => 'Código de catálogo',
        'scientific_name' => 'Identificación científica',
        'event_date' => 'Fecha original',
        'recorded_by' => 'Colector',
        'country' => 'País',
        'state_province' => 'Provincia',
        'locality_name' => 'Localidad registrada',
        'locality_excel' => 'Localidad original',
        'locality_inec' => 'Localidad INEC',
        'locality_inec_reference' => 'Referencia INEC',
        'decimal_latitude' => 'Latitud',
        'decimal_longitude' => 'Longitud',
        'coordinate_reference' => 'Referencia de coordenadas',
        'elevation_min_m' => 'Elevación mín. (m)',
        'elevation_max_m' => 'Elevación máx. (m)',
        'sampling_protocol' => 'Método de colecta',
        'individual_count' => 'Individuos',
        'type_status' => 'Condición de tipo',
        'type_notes' => 'Notas de tipo',
        'specimen_notes' => 'Notas del espécimen',
        'occurrence_status' => 'Estado',
        'caste' => 'Casta',
        'life_stage' => 'Estadio',
    ];
    $camposNumericos = ['decimal_latitude', 'decimal_longitude', 'elevation_min_m', 'elevation_max_m', 'individual_count'];
    $camposExtensos = ['locality_inec_reference', 'coordinate_reference', 'type_notes', 'specimen_notes'];
@endphp

<section class="atlas-record-table-section" aria-labelledby="titulo-tabla-registros-mapa">
    <h3 id="titulo-tabla-registros-mapa">Registros{{ $seleccion ? ' de '.$seleccion : ' de esta ubicación' }}</h3>
    <p class="atlas-record-table-summary">{{ number_format((int) $total, 0, ',', '.') }} registros públicos en esta selección · hasta 12 por página.</p>
    <p id="indicacion-tabla-registros-mapa" class="atlas-record-table-hint">Desplaza la tabla horizontalmente para consultar todas las columnas. Con teclado, enfoca la tabla y usa las flechas. «—» indica que no hay un dato público disponible.</p>
    <div class="atlas-record-table-scroll" role="region" tabindex="0" aria-label="Tabla de registros de la ubicación con desplazamiento horizontal y vertical" aria-describedby="indicacion-tabla-registros-mapa">
        <table class="atlas-record-table">
            <caption class="sr-only">Información pública completa de los registros de esta ubicación y selección</caption>
            <thead>
                <tr>
                    @foreach($columnas as $campo => $etiqueta)<th scope="col">{{ $etiqueta }}</th>@endforeach
                    <th scope="col">Fotografías publicadas</th>
                </tr>
            </thead>
            <tbody>
                @forelse($registros as $registro)
                    <tr wire:key="registro-tabla-mapa-{{ $registro->especimen_id }}">
                        @foreach($columnas as $campo => $etiqueta)
                            @php
                                $valorOriginal = $registro->{$campo} ?? null;
                                $valor = match ($campo) {
                                    'type_status' => \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::tipo($valorOriginal),
                                    'occurrence_status' => \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::estado($valorOriginal),
                                    'life_stage' => \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::etapa($valorOriginal),
                                    default => $valorOriginal,
                                };
                                $claseCelda = in_array($campo, $camposNumericos, true) ? 'atlas-record-table-number' : (in_array($campo, $camposExtensos, true) ? 'atlas-record-table-notes' : '');
                            @endphp
                            @if($campo === 'occurrence_id')
                                <th scope="row">{{ $valor ?: 'Código reservado' }}</th>
                            @else
                                <td class="{{ $claseCelda }}">
                                    @if($valorOriginal === null || $valorOriginal === '')
                                        <span aria-label="Sin dato público disponible">—</span>
                                    @elseif($campo === 'scientific_name')
                                        <em>{{ $valor }}</em>
                                    @else
                                        <span class="atlas-record-table-value">{{ $valor }}</span>
                                    @endif
                                    @if($campo === 'scientific_name' && ($registro->taxon_en_revision ?? false))<p class="atlas-data-warning">Dato original con clasificación pendiente de revisión; no se considera una identificación confirmada.</p>@endif
                                    @if($campo === 'event_date' && $valorOriginal && ((int) substr($valorOriginal, 0, 4) < 1800 || $valorOriginal > date('Y-m-d')))<p class="atlas-data-warning">Fecha original pendiente de revisión; no se interpreta como una fecha de colecta confirmada.</p>@endif
                                </td>
                            @endif
                        @endforeach
                        <td>
                            @php($fotos = $imagenes[$registro->occurrence_id ?? ''] ?? [])
                            @if($fotos !== [])
                                <div class="atlas-record-table-photos">
                                    @foreach($fotos as $foto)
                                        <a href="{{ $foto['url'] }}" target="_blank" rel="noopener noreferrer" title="{{ $foto['nombre'] }}" aria-label="Abrir fotografía publicada del espécimen {{ $registro->occurrence_id }} en otra pestaña"><img src="{{ $foto['url'] }}" alt="Fotografía publicada del espécimen {{ $registro->occurrence_id }}" loading="lazy" decoding="async" width="96" height="72"></a>
                                    @endforeach
                                </div>
                            @else
                                <span class="atlas-photo-empty">Sin fotografías publicadas</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columnas) + 1 }}">No hay registros públicos para esta selección.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
