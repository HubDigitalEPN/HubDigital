<dialog class="atlas-cell-dialog" wire:ignore.self x-data="{ anterior: null }" x-ref="detalleCelda"
    aria-labelledby="titulo-detalle-celda"
    x-on:abrir-detalle-celda.window="anterior = document.activeElement; if (!$el.open) $el.showModal()"
    x-on:close="$wire.cerrarCelda(); anterior?.focus({preventScroll: true})"
    x-on:click="if ($event.target === $el) $el.close()">
    @if($celdaMapa !== null)
        @php $detalle = $this->detalleCelda; @endphp
        <header class="atlas-cell-header">
            <div><h2 id="titulo-detalle-celda">Registros de la cuadrícula</h2><p>{{ number_format($detalle['total'], 0, ',', '.') }} registros · {{ $celdaMapa['lat'] }}, {{ $celdaMapa['lon'] }} · centro de cuadrícula de 0,25°</p></div>
            <button type="button" class="atlas-icon-button" autofocus x-on:click="$refs.detalleCelda.close()" aria-label="Cerrar registros">×</button>
        </header>
        <div class="atlas-cell-toolbar">
            <nav aria-label="Agrupación de registros" class="atlas-cell-breadcrumb">
                <button type="button" wire:click="volverCelda(0)">Todos los grupos</button>
                @foreach($rutaCelda as $paso)
                    <span aria-hidden="true">›</span><button type="button" wire:click="volverCelda({{ $loop->iteration }})" @if($loop->last) aria-current="true" @endif>{{ $paso['nombre'] }}</button>
                @endforeach
            </nav>
            <div class="collection-view-switch" aria-label="Presentación de los registros">
                <button type="button" wire:click="cambiarVistaCelda('grupos')" aria-label="Tarjetas agrupadas" title="Tarjetas agrupadas" aria-pressed="{{ $vistaCelda === 'grupos' ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v4H4zM4 12h7v8H4zM15 12h5v8h-5z"/></svg></button>
                <button type="button" wire:click="cambiarVistaCelda('registros')" aria-label="Todos los registros, 12 por página" title="Todos los registros, 12 por página" aria-pressed="{{ $vistaCelda === 'registros' ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h18v18H3zM3 9h18M3 15h18M9 3v18M15 3v18"/></svg></button>
            </div>
        </div>
        <p class="atlas-cell-status" wire:loading role="status">Cargando registros…</p>
        <div class="atlas-cell-content">
            @if($vistaCelda === 'grupos' && $detalle['grupos'] !== [])
                <div class="atlas-cell-grid">
                    @foreach($detalle['grupos'] as $grupo)
                        <button class="atlas-group-card" type="button" wire:click="navegarCelda('{{ $grupo['id'] }}')"><small>{{ ucfirst($grupo['rango']) }}</small><strong>{{ $grupo['nombre'] }}</strong><span>{{ number_format($grupo['total'], 0, ',', '.') }} registros · Explorar →</span></button>
                    @endforeach
                </div>
                @if($detalle['directos'] > 0)<button type="button" class="atlas-panel-link" wire:click="cambiarVistaCelda('registros')">Ver todos, incluidos {{ $detalle['directos'] }} registros sin agrupación adicional pública</button>@endif
            @else
                <div class="atlas-cell-grid">
                    @forelse($detalle['registros'] as $registro)
                        <article class="atlas-record-card">
                            <h3>{{ $registro->occurrence_id ?: 'Código reservado' }}</h3><p><em>{{ $registro->scientific_name ?: 'Identificación reservada o pendiente' }}</em></p>
                            @if($registro->event_date && ((int) substr($registro->event_date, 0, 4) < 1800 || $registro->event_date > date('Y-m-d')))<p class="atlas-data-warning">Fecha original pendiente de revisión; excluida de indicadores temporales.</p>@endif
                            <div class="atlas-record-photos">
                                @forelse($detalle['imagenes'][$registro->occurrence_id] ?? [] as $foto)
                                    <a href="{{ $foto['url'] }}" target="_blank" rel="noopener" title="{{ $foto['nombre'] }}" aria-label="Ampliar foto de {{ $registro->occurrence_id }}"><img src="{{ $foto['url'] }}" alt="Fotografía del espécimen {{ $registro->occurrence_id }}" loading="lazy" width="320" height="200"></a>
                                @empty<span class="atlas-photo-empty">Sin fotografías publicadas</span>@endforelse
                            </div>
                            <dl>
                                @foreach(['event_date' => 'Fecha original', 'recorded_by' => 'Colector', 'country' => 'País', 'state_province' => 'Provincia', 'locality_excel' => 'Localidad original', 'locality_inec' => 'Localidad INEC', 'locality_inec_reference' => 'Referencia INEC', 'decimal_latitude' => 'Latitud', 'decimal_longitude' => 'Longitud', 'coordinate_reference' => 'Referencia de coordenadas', 'elevation_min_m' => 'Elevación mín. (m)', 'elevation_max_m' => 'Elevación máx. (m)', 'sampling_protocol' => 'Método de colecta', 'individual_count' => 'Individuos', 'type_status' => 'Condición de tipo', 'type_notes' => 'Notas de tipo', 'specimen_notes' => 'Notas del espécimen', 'occurrence_status' => 'Estado', 'caste' => 'Casta', 'life_stage' => 'Estadio'] as $campo => $etiqueta)
                                    @if($registro->{$campo} !== null && $registro->{$campo} !== '')<div><dt>{{ $etiqueta }}</dt><dd>{{ $registro->{$campo} }}</dd></div>@endif
                                @endforeach
                            </dl>
                        </article>
                    @empty<p>No hay registros públicos para esta selección.</p>@endforelse
                </div>
                <nav class="atlas-cell-pagination" aria-label="Páginas del detalle">
                    <button type="button" wire:click="paginarCelda({{ $detalle['pagina'] - 1 }})" @disabled($detalle['pagina'] <= 1)>Anterior</button>
                    <span>Página {{ $detalle['pagina'] }} de {{ $detalle['ultima'] }} · 12 por página</span>
                    <button type="button" wire:click="paginarCelda({{ $detalle['pagina'] + 1 }})" @disabled($detalle['pagina'] >= $detalle['ultima'])>Siguiente</button>
                </nav>
            @endif
        </div>
    @endif
</dialog>
