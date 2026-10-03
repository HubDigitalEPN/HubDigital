@php
    $resumen = $datosMapa['resumen'];
    $total = max(1, (int) $resumen['registros']);
    $maxProvincia = max([1, ...array_map(static fn ($fila) => (int) $fila['especies'], $datosMapa['riqueza'])]);
    $maxDecada = max([1, ...array_map(static fn ($fila) => (int) $fila['especies'], $datosMapa['decadas'])]);
    $paleta = ['#17699b', '#d17d28', '#568c59', '#8c62a5', '#b94e6b', '#71828d', '#a18a29', '#3f8d90'];
@endphp
<div class="atlas atlas-dashboard" wire:key="dashboard-mapa" x-on:atlas-datos="actualizar($event.detail)" x-on:click.capture="recordarAccion($event)" x-data="portalDashboard(@js($datosMapa['mapa']), @js($datosMapa['filos']))">
    <span hidden wire:key="mapa-datos-{{ $claveFiltrosMapa }}" x-init="$dispatch('atlas-datos', {celdas: @js($datosMapa['mapa']), filos: @js($datosMapa['filos'])})"></span>
    <header class="atlas-dashboard-header">
        <div><h1>Colección Biológica</h1><p>Distribución, cobertura y calidad de {{ number_format((int) $resumen['registros'], 0, ',', '.') }} registros públicos de invertebrados.</p></div>
        <span class="atlas-dashboard-count">{{ number_format((int) $resumen['georreferenciados'], 0, ',', '.') }} con coordenadas públicas</span>
    </header>

    <div class="atlas-stage">
        <section x-ref="panelMapa" class="atlas-panel atlas-map-panel" :class="{'atlas-map-maximized': maximizado}" x-on:keydown.escape.window="if (maximizado && !$event.defaultPrevented && !document.querySelector('dialog[open]') && document.querySelector('#chat-bot-trigger')?.getAttribute('aria-expanded') !== 'true' && !$event.target.closest('#chat-bot-panel')) minimizar()" aria-labelledby="titulo-mapa">
            <div class="atlas-panel-header"><div><h2 id="titulo-mapa">Distribución de los registros</h2><p class="atlas-panel-subtitle">Agrupaciones azules al alejar · Ubicaciones originales al acercar · Mayús + arrastrar para seleccionar un área</p></div><button type="button" class="atlas-icon-button" x-ref="maximizar" x-on:click="alternarTamano()" :aria-label="maximizado ? 'Minimizar mapa' : 'Maximizar mapa'" :title="maximizado ? 'Minimizar mapa' : 'Maximizar mapa'" :aria-pressed="maximizado.toString()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path x-show="!maximizado" d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5"/><path x-show="maximizado" d="M3 8h5V3m8 0v5h5M8 21v-5H3m18 0h-5v5"/></svg></button><x-catalogopublico::menu-analisis tipo="mapa" :datos="$datosMapa['mapa']" /></div>
            <div class="atlas-map-shell">
                <div class="atlas-map" x-ref="mapa" tabindex="-1" wire:ignore role="region" aria-label="Mapa cartográfico de registros públicos: las agrupaciones se separan al acercar hasta mostrar las coordenadas originales"></div>
                @if($datosMapa['mapa'] === [])<p class="atlas-map-message">Esta selección no tiene coordenadas públicas. Ajusta los filtros o explora los registros.</p>@endif
            </div>
            <div class="atlas-map-legend" aria-label="Leyenda del mapa">
                <span><i class="atlas-legend-cluster" aria-hidden="true">n</i>Agrupación de ubicaciones cercanas; el número indica ubicaciones originales, y su ayuda muestra los registros. Pulsa o presiona Enter para acercar.</span>
                <span><i class="atlas-legend-dot" style="background:#17699b"></i>Ubicación original WGS84: registros en la misma latitud y longitud. Pulsa para consultar sus datos.</span>
                <small>Al acercar se separan los grupos; su símbolo no es una nueva coordenada de colecta. Los círculos de ubicaciones originales crecen con los registros hasta 17 px; no representan abundancia natural ni esfuerzo de muestreo. Las descargas conservan todas las coordenadas originales. La precisión y el origen constan en cada ejemplar; una referencia aproximada no indica una colecta exacta.</small>
            </div>
        </section>

        <section class="atlas-panel atlas-taxa-panel" aria-labelledby="titulo-filos">
            <div class="atlas-panel-header"><div><h2 id="titulo-filos">Composición taxonómica</h2><p class="atlas-panel-subtitle">Selecciona un filo para filtrar todos los paneles</p></div><x-catalogopublico::menu-analisis tipo="filos" :datos="$datosMapa['filos']" /></div>
            <div class="atlas-taxon-body">
                @forelse($datosMapa['filos'] as $filo => $cantidad)
                    @php $porcentaje = (int) $cantidad / $total * 100; $color = $paleta[$loop->index % count($paleta)]; $idFiloCategoria = $this->identificadorFilo($filo); @endphp
                    <button class="atlas-taxon-row" type="button" wire:click="seleccionarFilo(@js($idFiloCategoria ?? ''))" wire:loading.attr="disabled" wire:target="seleccionarFilo" @disabled($idFiloCategoria === null) aria-pressed="{{ $idFiloCategoria !== null && $filtroFiloId === $idFiloCategoria ? 'true' : 'false' }}" aria-label="{{ $idFiloCategoria === null ? $filo.' sin identificador confirmado para filtrar' : ($filtroFiloId === $idFiloCategoria ? 'Quitar filtro de filo '.$filo : 'Filtrar por filo '.$filo) }}">
                        <span class="atlas-taxon-name"><i class="atlas-legend-dot" style="background:{{ $color }}"></i>{{ $filo }}</span>
                        <span class="atlas-taxon-count">{{ number_format((int) $cantidad, 0, ',', '.') }} <small>({{ number_format($porcentaje, 1, ',', '.') }} %)</small></span>
                        <span class="atlas-bar" aria-hidden="true"><span style="width:{{ min(100, $porcentaje) }}%;background:{{ $color }}"></span></span>
                    </button>
                @empty
                    <p class="atlas-chart-empty">La selección no contiene filos identificados.</p>
                @endforelse
            </div>
            @if(($datosMapa['mosaico'] ?? []) !== [])
                <div class="atlas-taxon-mosaic" aria-label="Fotografías públicas de la selección">
                    @foreach(array_slice($datosMapa['mosaico'], 0, 4) as $foto)
                        <figure><a href="{{ $foto['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="Ampliar fotografía de {{ $foto['taxon'] ?? $foto['nombre'] }}"><img src="{{ $foto['url'] }}" alt="{{ $foto['taxon'] ?? $foto['nombre'] }} — fotografía pública de la colección" width="240" height="180" loading="lazy" decoding="async"></a><figcaption>@if(($foto['family'] ?? '') !== ''){{ $foto['family'] }} — @endif @if(($foto['genus'] ?? '') !== '')<em>{{ $foto['genus'] }}</em> — @endif <em>{{ $foto['species'] ?? $foto['taxon'] ?? $foto['nombre'] }}</em>@if(($foto['credito_ecuador'] ?? false) && ($foto['autor'] ?? '') !== '')<details class="collection-representation-lineage"><summary>Autoría de la fotografía</summary><p>{{ $foto['autor'] }}</p></details>@endif</figcaption></figure>
                    @endforeach
                </div>
            @else
                @php $ilustracionesMosaico = array_values(array_filter(array_slice($datosMapa['ilustraciones_mosaico'] ?? [], 0, 4), static fn (array $imagen): bool => ($imagen['foto_real'] ?? false) && ($imagen['morfologia'] ?? false) && is_string($imagen['url'] ?? null) && $imagen['url'] !== '')); @endphp
                <x-catalogopublico::fotografia-mosaico :taxon="$datosMapa['taxon_mosaico'] ?? []" :fotos="$ilustracionesMosaico" :descripcion-inicial="$datosMapa['descripcion_mosaico'] ?? ''" contexto="composicion" />
            @endif
            @if(($datosMapa['mosaico'] ?? []) !== [] && ($datosMapa['descripcion_mosaico'] ?? '') !== '')<p class="atlas-taxa-note">{{ $datosMapa['descripcion_mosaico'] }}</p>@endif
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
        @include('catalogopublico::components.panel-investigacion', ['tipoPanel' => 'estacionalidad'])
    </div>
    <div class="atlas-analysis-row atlas-analysis-row--two" role="group" aria-label="Altitud y métodos de muestreo">
        @include('catalogopublico::components.panel-investigacion', ['tipoPanel' => 'altitud'])
        @include('catalogopublico::components.panel-investigacion', ['tipoPanel' => 'metodos'])
    </div>
    <p class="atlas-source-note">Los conteos describen especímenes publicados; no corrigen sesgos de muestreo. Las coordenadas reservadas no se muestran. Criterios: <a href="https://techdocs.gbif.org/en/data-publishing/data-quality-recommendations" target="_blank" rel="noopener noreferrer">calidad de datos GBIF</a> y <a href="https://dwc.tdwg.org/list/" target="_blank" rel="noopener noreferrer">Darwin Core</a>.</p>
</div>
