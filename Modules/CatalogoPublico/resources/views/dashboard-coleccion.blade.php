@php
    $resumen = $datosMapa['resumen'];
    $total = max(1, (int) $resumen['registros']);
    $maxProvincia = max([1, ...array_map(static fn ($fila) => (int) $fila['especies'], $datosMapa['riqueza'])]);
    $maxDecada = max([1, ...array_map(static fn ($fila) => (int) $fila['especies'], $datosMapa['decadas'])]);
    $paleta = ['#17699b', '#d17d28', '#568c59', '#8c62a5', '#b94e6b', '#71828d', '#a18a29', '#3f8d90'];
@endphp
<div class="atlas atlas-dashboard" wire:key="dashboard-{{ $claveFiltrosMapa }}" x-data="portalDashboard(@js($datosMapa['mapa']), @js($datosMapa['filos']))">
    <header class="atlas-dashboard-header">
        <div><h1>Colección Biológica</h1><p>Distribución, cobertura y calidad de {{ number_format((int) $resumen['registros'], 0, ',', '.') }} registros públicos de invertebrados.</p></div>
        <span class="atlas-dashboard-count">{{ number_format((int) $resumen['georreferenciados'], 0, ',', '.') }} con coordenadas públicas</span>
    </header>

    <div class="atlas-stage">
        <section class="atlas-panel atlas-map-panel" aria-labelledby="titulo-mapa">
            <div class="atlas-panel-header"><div><h2 id="titulo-mapa">Distribución de los registros</h2><p class="atlas-panel-subtitle">Cuadrículas de 0,25° · Tamaño según número de registros · Mayús + arrastrar para seleccionar un área</p></div><x-catalogopublico::menu-analisis tipo="mapa" :datos="$datosMapa['mapa']" /></div>
            <div class="atlas-map-shell">
                <div class="atlas-map" x-ref="mapa" wire:ignore role="region" aria-label="Mapa de registros publicados por cuadrícula"></div>
                @if($datosMapa['mapa'] === [])<p class="atlas-map-message">Esta selección no tiene coordenadas públicas. Ajusta los filtros o explora los registros.</p>@endif
            </div>
            <div class="atlas-map-legend" aria-label="Leyenda del mapa">
                <strong>Filo predominante</strong>
                @foreach($datosMapa['filos'] as $filo => $cantidad)
                    <span><i class="atlas-legend-dot" style="background:{{ $paleta[$loop->index % count($paleta)] }}"></i>{{ $filo }}</span>
                @endforeach
                <span><i class="atlas-legend-dot" style="background:#71828d"></i>Sin filo público</span>
                <small>Círculo mayor = más registros en la cuadrícula. Los colores coinciden con la composición taxonómica.</small>
            </div>
            <div class="atlas-map-footer"><span>Los puntos representan registros documentados, no ausencia o abundancia natural.</span><button type="button" wire:click="verGeorreferenciados">Ver registros ubicados →</button></div>
        </section>

        <section class="atlas-panel atlas-taxa-panel" aria-labelledby="titulo-filos">
            <div class="atlas-panel-header"><div><h2 id="titulo-filos">Composición taxonómica</h2><p class="atlas-panel-subtitle">Selecciona un filo para filtrar todos los paneles</p></div><x-catalogopublico::menu-analisis tipo="filos" :datos="$datosMapa['filos']" /></div>
            <div class="atlas-taxon-body">
                @forelse($datosMapa['filos'] as $filo => $cantidad)
                    @php $porcentaje = (int) $cantidad / $total * 100; $color = $paleta[$loop->index % count($paleta)]; @endphp
                    <button class="atlas-taxon-row" type="button" wire:click="seleccionarFilo(@js($filo))" wire:loading.attr="disabled" aria-pressed="{{ $filtroFiloId !== '' ? 'true' : 'false' }}">
                        <span class="atlas-taxon-name"><i class="atlas-legend-dot" style="background:{{ $color }}"></i>{{ $filo }}</span>
                        <span class="atlas-taxon-count">{{ number_format((int) $cantidad, 0, ',', '.') }} <small>({{ number_format($porcentaje, 1, ',', '.') }} %)</small></span>
                        <span class="atlas-bar" aria-hidden="true"><span style="width:{{ min(100, $porcentaje) }}%;background:{{ $color }}"></span></span>
                    </button>
                @empty
                    <p class="atlas-chart-empty">La selección no contiene filos identificados.</p>
                @endforelse
            </div>
            <p class="atlas-taxa-note">La proporción usa todos los registros seleccionados; algunos aún no tienen filo confirmado.</p>
        </section>
    </div>

    <div class="atlas-analysis-row atlas-analysis-row--three" role="group" aria-label="Distribución, tiempo y calidad">
        <section class="atlas-panel" aria-labelledby="titulo-riqueza">
            <div class="atlas-panel-header"><div><h2 id="titulo-riqueza">Riqueza por provincia</h2><p class="atlas-panel-subtitle">Especies distintas documentadas · diez principales</p></div><x-catalogopublico::menu-analisis tipo="riqueza" :datos="$datosMapa['riqueza']" /></div>
            <div class="atlas-ranked-chart">
                @forelse($datosMapa['riqueza'] as $fila)
                    <button class="atlas-ranked-row" type="button" wire:click='seleccionarProvincia(@json($fila["provincia"]))' title="Filtrar por {{ $fila['provincia'] }}">
                        <span>{{ $fila['provincia'] }}</span><strong>{{ number_format((int) $fila['especies'], 0, ',', '.') }}</strong>
                        <i aria-hidden="true"><b style="width:{{ max(2, (int) $fila['especies'] / $maxProvincia * 100) }}%"></b></i>
                    </button>
                @empty<p class="atlas-chart-empty">No hay especies con provincia pública en esta selección.</p>@endforelse
            </div>
            <p class="atlas-panel-note">Riqueza observada en la colección; depende del esfuerzo de muestreo.</p>
        </section>
        <section class="atlas-panel" aria-labelledby="titulo-decadas">
            <div class="atlas-panel-header"><div><h2 id="titulo-decadas">Cobertura temporal</h2><p class="atlas-panel-subtitle">Especies distintas por década de colecta</p></div><x-catalogopublico::menu-analisis tipo="decadas" :datos="$datosMapa['decadas']" /></div>
            <div class="atlas-ranked-chart atlas-ranked-chart--time">
                @forelse($datosMapa['decadas'] as $fila)
                    <button class="atlas-ranked-row" type="button" wire:click="seleccionarDecada({{ (int) $fila['decada'] }})" title="Filtrar la década de {{ $fila['decada'] }}">
                        <span>{{ $fila['decada'] }}–{{ (int) $fila['decada'] + 9 }}</span><strong>{{ number_format((int) $fila['especies'], 0, ',', '.') }}</strong>
                        <i aria-hidden="true"><b style="width:{{ max(2, (int) $fila['especies'] / $maxDecada * 100) }}%"></b></i>
                    </button>
                @empty<p class="atlas-chart-empty">No hay fechas e identificaciones a especie visibles.</p>@endforelse
            </div>
            <p class="atlas-panel-note">Selecciona una década para actualizar toda la vista.</p>
        </section>
        <section class="atlas-panel" aria-labelledby="titulo-calidad">
            <div class="atlas-panel-header"><div><h2 id="titulo-calidad">Calidad para análisis</h2><p class="atlas-panel-subtitle">Completitud de los registros seleccionados</p></div><x-catalogopublico::menu-analisis tipo="calidad" :datos="$datosMapa['resumen']" /></div>
            <div class="atlas-quality">
                <div class="atlas-quality-number">{{ number_format((int) $resumen['aptos'], 0, ',', '.') }} <small>de {{ number_format((int) $resumen['registros'], 0, ',', '.') }}</small></div>
                <p class="atlas-quality-caption">tienen identificación a especie, fecha y coordenadas públicas a la vez.</p>
                @foreach([['Identificados a especie', 'identificados'], ['Con fecha', 'fechados'], ['Con coordenadas', 'georreferenciados']] as [$etiqueta, $clave])
                    <div class="atlas-quality-item"><div class="atlas-quality-top"><span>{{ $etiqueta }}</span><strong>{{ number_format((int) $resumen[$clave], 0, ',', '.') }}</strong></div><div class="atlas-quality-track"><span style="width:{{ min(100, (int) $resumen[$clave] / $total * 100) }}%"></span></div></div>
                @endforeach
                <button type="button" class="atlas-panel-link" wire:click="filtrarCompletos">Revisar registros completos →</button>
            </div>
            <p class="atlas-panel-note">Completitud no certifica precisión; revisa la incertidumbre antes de modelar.</p>
        </section>
    </div>

    <div class="atlas-analysis-row atlas-analysis-row--two" role="group" aria-label="Especies prioritarias y más documentadas">
        <section class="atlas-panel" aria-labelledby="titulo-raras">
            <div class="atlas-panel-header"><div><h2 id="titulo-raras">Especies con pocos registros</h2><p class="atlas-panel-subtitle">Una a tres ocurrencias: posibles vacíos para revisar</p></div><x-catalogopublico::menu-analisis tipo="raras" :datos="$datosMapa['raras']" /></div>
            <div class="atlas-species-list">
                @forelse($datosMapa['raras'] as $especie)<button type="button" class="atlas-species-row" wire:click='explorarEspecie(@json($especie["nombre"]))'><em>{{ $especie['nombre'] }}</em><strong>{{ $especie['total'] }}</strong></button>
                @empty<p class="atlas-chart-empty">No hay especies con una a tres ocurrencias en esta selección.</p>@endforelse
            </div>
            <p class="atlas-panel-note">Pocos ejemplares publicados no implican rareza ni amenaza en la naturaleza.</p>
        </section>
        <section class="atlas-panel" aria-labelledby="titulo-especies">
            <div class="atlas-panel-header"><div><h2 id="titulo-especies">Especies más documentadas</h2><p class="atlas-panel-subtitle">Veinte especies con más registros en la selección</p></div><x-catalogopublico::menu-analisis tipo="especies" :datos="$datosMapa['especies']" /></div>
            <div class="atlas-species-list atlas-species-list--columns">
                @forelse($datosMapa['especies'] as $especie)<button type="button" class="atlas-species-row" wire:click='explorarEspecie(@json($especie["nombre"]))'><em>{{ $especie['nombre'] }}</em><strong>{{ number_format((int) $especie['total'], 0, ',', '.') }}</strong></button>
                @empty<p class="atlas-chart-empty">No hay especies identificadas en esta selección.</p>@endforelse
            </div>
            <p class="atlas-panel-note">Selecciona una especie para ver sus registros; la lista responde a los filtros.</p>
        </section>
    </div>
    <p class="atlas-source-note">Los conteos describen especímenes publicados; no corrigen sesgos de muestreo. Las coordenadas reservadas no se muestran. Criterios: <a href="https://techdocs.gbif.org/en/data-publishing/data-quality-recommendations" target="_blank" rel="noopener noreferrer">calidad de datos GBIF</a> y <a href="https://dwc.tdwg.org/list/" target="_blank" rel="noopener noreferrer">Darwin Core</a>.</p>
</div>
