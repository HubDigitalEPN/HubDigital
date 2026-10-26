@php
    $resumen = $datosMapa['resumen'];
    $dashboardTexto = static fn (string $clave): string => \App\Support\PortalCopy::text('catalogo.'.$clave);
    $total = max(1, (int) $resumen['registros']);
    $territorio = $this->territorioPanel;
    $provinciasSeleccionadas = $this->filtrosAplicados['filtroProvincias'];
    $porLocalidad = $provinciasSeleccionadas !== [];
    $tituloTerritorio = $dashboardTexto($porLocalidad ? 'localidades' : 'provincias');
    $maxProvincia = max([1, ...array_map(static fn ($fila) => (int) $fila['registros'], $territorio)]);
    $maxDecada = max([1, ...array_map(static fn ($fila) => (int) $fila['registros'], $datosMapa['decadas'])]);
    $coloresFilo = ['Arthropoda' => '#17699b', 'Mollusca' => '#d17d28', 'Annelida' => '#568c59', 'Nematoda' => '#8c62a5', 'Nematomorpha' => '#b94e6b'];
@endphp
<div class="atlas atlas-dashboard" wire:key="dashboard-mapa" x-on:atlas-datos="actualizar($event.detail)" x-on:encuadrar-taxonomia.window="encuadrarTaxonomia($event.detail)" x-on:restaurar-foco-mapa.window="restaurarFocoMapa($event.detail.invocador)" x-on:click.capture="recordarAccion($event)" x-data="portalDashboard()" x-on:alternar-tipo-grafico="alternarRepresentacionMapa($event.detail)" x-id="['atlas-map-tooltip']" x-on:scroll.window.capture="ocultarAyudaMapa()" x-on:resize.window="ocultarAyudaMapa()" x-on:keydown.escape.window="ocultarAyudaMapa()">
    <span hidden wire:key="mapa-datos-{{ $claveFiltrosMapa }}" x-init="$dispatch('atlas-datos', {celdas: @js($datosMapa['mapa']), filos: @js($datosMapa['filos'])})"></span>
    <span hidden data-acceso-registros="true"></span>
    <div class="atlas-stage">
        <section x-ref="panelMapa" class="atlas-panel atlas-map-panel" :class="{'atlas-map-maximized': maximizado}" x-on:keydown.escape.window="if (maximizado && !$event.defaultPrevented && !document.querySelector('dialog[open]') && document.querySelector('#chat-bot-trigger')?.getAttribute('aria-expanded') !== 'true' && !$event.target.closest('#chat-bot-panel')) minimizar()" aria-label="Mapa de registros públicos">
            <header class="atlas-panel-header atlas-map-tools">
                <h1 class="atlas-map-title">Distribución de Registros <small class="atlas-collection-count">({{ number_format((int) $resumen['registros'], 0, ',', '.') }} registros)</small></h1>
                <div class="atlas-map-actions">
                    <button type="button" class="atlas-icon-button" x-ref="maximizar" x-on:click="alternarTamano()" :aria-label="maximizado ? @js($dashboardTexto('minimizar_mapa')) : @js($dashboardTexto('maximizar_mapa'))" :title="maximizado ? @js($dashboardTexto('minimizar_mapa')) : @js($dashboardTexto('maximizar_mapa'))" :aria-pressed="maximizado.toString()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path x-show="!maximizado" d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5"/><path x-show="maximizado" d="M3 8h5V3m8 0v5h5M8 21v-5H3m18 0h-5v5"/></svg></button>
                    <x-catalogopublico::menu-analisis tipo="mapa" :datos="$datosMapa['mapa']" />
                </div>
            </header>
            <div class="atlas-map-shell">
                <div class="atlas-cell-error" x-show="errorTeselas" x-cloak role="alert">{{ $dashboardTexto('mapa_base_error') }} <button type="button" x-on:click="reintentarTeselas()">{{ $dashboardTexto('reintentar_mapa_base') }}</button></div>
                <div class="atlas-map" x-ref="mapa" tabindex="-1" wire:ignore role="region" aria-label="Mapa cartográfico de registros públicos: las agrupaciones se separan al acercar hasta mostrar las coordenadas originales"></div>
                @if($datosMapa['mapa'] === [])<p class="atlas-map-message">{{ $dashboardTexto('mapa_vacio') }}</p>@endif
            </div>
            <x-catalogopublico::figura-panel tipo="mapa" />
        </section>

        <section class="atlas-panel atlas-taxa-panel" wire:key="grafico-filos-{{ sha1(json_encode($datosMapa['filos'])) }}" aria-labelledby="titulo-filos" x-data="portalGrafico('filos', @js($datosMapa['filos']), @js($dashboardTexto('composicion')))" x-on:alternar-tipo-grafico="alternar($event.detail)">
            <div class="atlas-panel-header"><div><h2 id="titulo-filos">{{ $dashboardTexto('composicion') }}</h2></div><x-catalogopublico::menu-analisis tipo="filos" :datos="$datosMapa['filos']" /></div>
            <x-catalogopublico::grafico-panel :titulo="$dashboardTexto('composicion')" />
            <div class="atlas-taxon-body" x-show="modo === 'barras' || tablaAbierta">
                @forelse($datosMapa['filos'] as $filo => $cantidad)
                    @php $porcentaje = (int) $cantidad / $total * 100; $color = $coloresFilo[$filo] ?? '#71828d'; $idFiloCategoria = $this->identificadorFilo($filo); @endphp
                    <button class="atlas-taxon-row" type="button" wire:key="composicion-filo-{{ $idFiloCategoria ?? sha1($filo) }}" wire:click="$wire.seleccionarFilo(@js($idFiloCategoria ?? ''))" wire:loading.attr="disabled" wire:target="seleccionarFilo" @disabled($idFiloCategoria === null) aria-pressed="{{ $idFiloCategoria !== null && in_array($idFiloCategoria, $this->filtrosAplicados['filtroFilos'], true) ? 'true' : 'false' }}" aria-label="{{ $idFiloCategoria === null ? $filo.' sin identificador confirmado para filtrar' : (in_array($idFiloCategoria, $this->filtrosAplicados['filtroFilos'], true) ? 'Quitar filtro de filo '.$filo : 'Filtrar por filo '.$filo) }}">
                        <span class="atlas-taxon-name"><i class="atlas-legend-dot" style="background:{{ $color }}"></i>{{ $filo }}</span>
                        <span class="atlas-taxon-count">{{ number_format((int) $cantidad, 0, ',', '.') }} <small>({{ number_format($porcentaje, 1, ',', '.') }} %)</small></span>
                        <span class="atlas-bar" aria-hidden="true"><span style="width:{{ min(100, $porcentaje) }}%;background:{{ $color }}"></span></span>
                    </button>
                @empty
                    <p class="atlas-chart-empty">{{ $dashboardTexto('filos_vacios') }}</p>
                @endforelse
            </div>
            @if($mostrarFotoComposicion)
            @if(($datosMapa['mosaico'] ?? []) !== [])
                <div class="atlas-taxon-photograph" aria-label="Fotografía pública de la selección">
                    @foreach(array_slice($datosMapa['mosaico'], 0, 1) as $foto)
                        <figure><a href="{{ $foto['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="Ampliar fotografía de {{ $foto['taxon'] ?? $foto['nombre'] }}"><img src="{{ $foto['url'] }}" alt="{{ $foto['taxon'] ?? $foto['nombre'] }} — fotografía pública de la colección" width="240" height="180" loading="lazy" decoding="async"></a><figcaption>@if(($foto['family'] ?? '') !== ''){{ $foto['family'] }} — @endif @if(($foto['genus'] ?? '') !== '')<em>{{ $foto['genus'] }}</em> — @endif <em>{{ $foto['species'] ?? $foto['taxon'] ?? $foto['nombre'] }}</em>@if(($foto['credito_ecuador'] ?? false) && ($foto['autor'] ?? '') !== '')<details class="collection-representation-lineage"><summary>Autoría de la fotografía</summary><p>{{ $foto['autor'] }}</p></details>@endif</figcaption></figure>
                    @endforeach
                </div>
            @else
                @php $ilustracionesMosaico = array_values(array_filter(array_slice($datosMapa['ilustraciones_mosaico'] ?? [], 0, 4), static fn (array $imagen): bool => ($imagen['foto_real'] ?? false) && ($imagen['morfologia'] ?? false) && is_string($imagen['url'] ?? null) && $imagen['url'] !== '')); @endphp
                <x-catalogopublico::fotografia-mosaico :taxon="$datosMapa['taxon_mosaico'] ?? []" :fotos="$ilustracionesMosaico" :descripcion-inicial="$datosMapa['descripcion_mosaico'] ?? ''" :limite="1" :retrato="true" contexto="composicion" />
            @endif
            @if(($datosMapa['mosaico'] ?? []) !== [])@endif
            @endif
            <x-catalogopublico::figura-panel tipo="filos" />
        </section>
    </div>

    <div class="atlas-analysis-row atlas-analysis-row--three" role="group" aria-label="Distribución, tiempo y calidad">
        <section class="atlas-panel" wire:key="grafico-riqueza-{{ sha1(json_encode([$territorio, $provinciasSeleccionadas])) }}" aria-labelledby="titulo-riqueza" x-data="portalGrafico('riqueza', @js(array_slice($territorio, 0, 10)), @js($tituloTerritorio))" x-on:alternar-tipo-grafico="alternar($event.detail)">
            <div class="atlas-panel-header"><div><div class="atlas-territory-heading"><h2 id="titulo-riqueza" tabindex="-1">{{ $tituloTerritorio }}</h2>
                @if($porLocalidad)<button type="button" class="atlas-territory-back" wire:click="volverProvincias" wire:loading.attr="disabled" aria-label="{{ $dashboardTexto('volver_provincias') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m10 6-6 6 6 6M4 12h16"/></svg></button>@endif
            </div></div><x-catalogopublico::menu-analisis tipo="riqueza" :datos="$territorio" /></div>
            <x-catalogopublico::grafico-panel :titulo="$tituloTerritorio" />

            <div x-data="{busquedaLocalidad: '', normalizar(valor) { return valor.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim().replace(/\s+/g, ' '); }}" x-show="modo === 'barras' || tablaAbierta">
                @if($porLocalidad && $territorio !== [])<label class="atlas-territory-search" for="buscar-localidad-panel">{{ $dashboardTexto('buscar_localidad') }}<input id="buscar-localidad-panel" type="search" x-model="busquedaLocalidad" placeholder="{{ $dashboardTexto('buscar_nombre') }}" autocomplete="off"></label>@endif
            <div class="atlas-ranked-chart">
                @forelse($territorio as $fila)
                    @php $nombreTerritorio = $fila[$porLocalidad ? 'localidad' : 'provincia']; $seleccionada = $porLocalidad && in_array($nombreTerritorio, $this->filtrosAplicados['filtroGeografias'], true); @endphp
                    <button class="atlas-ranked-row" type="button" wire:key="territorio-{{ sha1($nombreTerritorio) }}" x-show="normalizar(@js($nombreTerritorio)).includes(normalizar(busquedaLocalidad))" wire:click="$wire.{{ $porLocalidad ? 'seleccionarLocalidad' : 'seleccionarProvincia' }}(@js($nombreTerritorio))" wire:loading.attr="disabled" aria-pressed="{{ $seleccionada ? 'true' : 'false' }}" title="{{ $seleccionada ? 'Quitar filtro de ' : 'Filtrar por ' }}{{ $nombreTerritorio }}">
                        <span>{{ $nombreTerritorio }}</span><strong>{{ number_format((int) $fila['registros'], 0, ',', '.') }}</strong>
                        <i aria-hidden="true"><b style="width:{{ max(2, (int) $fila['registros'] / $maxProvincia * 100) }}%"></b></i>
                    </button>
                @empty<p class="atlas-chart-empty">{{ $dashboardTexto($porLocalidad ? 'localidades_vacias' : 'provincias_vacias') }}</p>@endforelse
            </div>
            @if($porLocalidad && $territorio !== [])<p role="status" class="atlas-panel-subtitle px-4" x-show="@js(array_column($territorio, 'localidad')).every(nombre => !normalizar(nombre).includes(normalizar(busquedaLocalidad)))">{{ $dashboardTexto('territorio_vacio') }}</p>@endif
            </div>
            <x-catalogopublico::figura-panel tipo="riqueza" />
        </section>
        <section class="atlas-panel" wire:key="grafico-decadas-{{ sha1(json_encode($datosMapa['decadas'])) }}" aria-labelledby="titulo-decadas" x-data="portalGrafico('decadas', @js($datosMapa['decadas']), @js($dashboardTexto('cobertura_temporal')))" x-on:alternar-tipo-grafico="alternar($event.detail)">
            <div class="atlas-panel-header"><div><h2 id="titulo-decadas">{{ $dashboardTexto('cobertura_temporal') }}</h2></div><x-catalogopublico::menu-analisis tipo="decadas" :datos="$datosMapa['decadas']" /></div>
            <x-catalogopublico::grafico-panel :titulo="$dashboardTexto('cobertura_temporal')" />
            <div class="atlas-ranked-chart atlas-ranked-chart--time" x-show="modo === 'barras' || tablaAbierta">
                @forelse($datosMapa['decadas'] as $fila)
                    <button class="atlas-ranked-row" type="button" wire:click="seleccionarDecada({{ (int) $fila['decada'] }})" title="Filtrar la década de {{ $fila['decada'] }}">
                        <span>{{ $fila['decada'] }}–{{ (int) $fila['decada'] + 9 }}</span><strong>{{ number_format((int) $fila['registros'], 0, ',', '.') }}</strong>
                        <i aria-hidden="true"><b style="width:{{ max(2, (int) $fila['registros'] / $maxDecada * 100) }}%"></b></i>
                    </button>
                @empty<p class="atlas-chart-empty">{{ $dashboardTexto('fechas_vacias') }}</p>@endforelse
            </div>
            <x-catalogopublico::figura-panel tipo="decadas" />
        </section>
        @include('catalogopublico::components.panel-investigacion', ['tipoPanel' => 'estacionalidad'])
    </div>
    <div class="atlas-analysis-row atlas-analysis-row--two" role="group" aria-label="Altitud y métodos de muestreo">
        @include('catalogopublico::components.panel-investigacion', ['tipoPanel' => 'altitud'])
        @include('catalogopublico::components.panel-investigacion', ['tipoPanel' => 'metodos'])
    </div>
</div>
