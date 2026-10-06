@props(['registros' => [], 'imagenes' => [], 'total' => 0, 'seleccion' => null, 'contexto' => 'mapa', 'mostrarTitulo' => true])

@php
    // El componente recibe exclusivamente los DTO que ya pasaron aplicarVisibilidad.
    $columnas = array_column($this->columnasPublicas, 'etiqueta', 'campo');
    $camposNumericos = ['decimal_latitude', 'decimal_longitude', 'elevation_min_m', 'elevation_max_m', 'individual_count'];
    $camposExtensos = ['locality_inec_reference', 'coordinate_reference', 'type_notes', 'specimen_notes'];
    $etiquetaRegion = $contexto === 'catalogo' ? 'Registros de la colección con desplazamiento horizontal' : 'Tabla de registros de la ubicación con desplazamiento horizontal y vertical';
@endphp

<section class="atlas-record-table-section" aria-label="Registros públicos">
    @if($mostrarTitulo)<h3>Registros{{ $seleccion ? ' de '.$seleccion : ($contexto === 'mapa' ? ' de esta ubicación' : ' del catálogo') }}</h3>@endif
    <div class="atlas-record-table-scroll" role="region" tabindex="0" aria-label="{{ $etiquetaRegion }}">
        <table class="atlas-record-table">
            <caption class="sr-only">Registros públicos de esta selección, con las columnas configuradas por la administración.</caption>
            <thead>
                <tr>
                    @foreach($columnas as $campo => $etiqueta)<th scope="col">{{ $etiqueta }}</th>@endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($registros as $registro)
                    <tr wire:key="registro-tabla-{{ $contexto }}-{{ $registro->especimen_id }}" tabindex="0" class="portal-record-row"
                        x-on:click="$wire.abrirFichaRegistro(@js($registro->especimen_id))"
                        x-on:keydown.enter.self.prevent="$wire.abrirFichaRegistro(@js($registro->especimen_id))"
                        x-on:keydown.space.self.prevent="$wire.abrirFichaRegistro(@js($registro->especimen_id))"
                        aria-label="Abrir ficha de {{ $registro->occurrence_id ?: 'registro con código reservado' }}">
                        @foreach($columnas as $campo => $etiqueta)
                            @if(in_array($campo, ['fotografias', 'ficha'], true)) @continue @endif
                            @php
                                $valorOriginal = $registro->{$campo} ?? null;
                                $valor = match ($campo) {
                                    'type_status' => \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::tipo($valorOriginal),
                                    'disposition' => \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::disposicion($valorOriginal),
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
                                        @if($campo === 'type_status' && ($registro->type_status_visible ?? false))<span>No informado</span>@else<span aria-label="Sin dato público disponible">—</span>@endif
                                    @elseif($campo === 'scientific_name')
                                        <em class="atlas-record-table-value" title="{{ $valor }}">{{ $valor }}</em>
                                    @else
                                        <span class="atlas-record-table-value" title="{{ $valor }}">{{ $valor }}</span>
                                    @endif
                                    @if($campo === 'scientific_name' && ($registro->taxon_en_revision ?? false))<p class="atlas-data-warning">Dato original con clasificación pendiente de revisión; no se considera una identificación confirmada.</p>@endif
                                    @if($campo === 'event_date' && $valorOriginal && ((int) substr($valorOriginal, 0, 4) < 1800 || $valorOriginal > date('Y-m-d')))<p class="atlas-data-warning">Fecha original pendiente de revisión; no se interpreta como una fecha de colecta confirmada.</p>@endif
                                </td>
                            @endif
                        @endforeach
                        @if(isset($columnas['fotografias']))<td>
                            @php
                                $fotos = $imagenes[$registro->occurrence_id ?? ''] ?? [];
                            @endphp
                            @if($fotos !== [])
                                <div class="atlas-record-table-photos">
                                    @foreach($fotos as $foto)
                                        <a href="{{ $foto['url'] }}" x-on:click.stop target="_blank" rel="noopener noreferrer" title="{{ $foto['nombre'] }}" aria-label="Abrir fotografía publicada del espécimen {{ $registro->occurrence_id }} en otra pestaña"><img src="{{ $foto['url'] }}" alt="Fotografía publicada del espécimen {{ $registro->occurrence_id }}" loading="lazy" decoding="async" width="96" height="72"></a>
                                    @endforeach
                                </div>
                            @else
                                <span class="atlas-photo-empty">Sin fotografías publicadas</span>
                            @endif
                        </td>@endif
                        @if(isset($columnas['ficha']))<td><button type="button" class="portal-record-open" x-on:click.stop="$wire.abrirFichaRegistro(@js($registro->especimen_id))">Abrir ficha<span class="sr-only"> de {{ $registro->occurrence_id ?: 'registro público' }}</span></button></td>@endif
                    </tr>
                @empty
                    <tr><td colspan="{{ max(1, count($columnas)) }}">No hay registros públicos para esta selección.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
