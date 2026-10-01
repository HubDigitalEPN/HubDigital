@component('layouts.portal', ['title' => 'Colección Biológica'])
@php
    $r = $datos['resumen'];
    $total = max(1, (int) $r['registros']);
    $guias = [
        'mapa' => ['Leer los puntos del mapa', 'Cada punto resume una cuadrícula de 0,25°. Su color indica el filo con más registros en esa celda y su tamaño, el volumen documentado. La leyenda permite aislar un filo. Una celda vacía no demuestra ausencia biológica. Las coordenadas no públicas nunca se muestran.'],
        'filos' => ['Composición taxonómica', 'Los registros se agrupan según el filo del taxón asignado. Pulsa un filo para mostrar solo sus celdas en el mapa. La proporción se calcula sobre todos los registros filtrados; algunos pueden carecer de filo.'],
        'riqueza' => ['Riqueza documentada por provincia', 'Se cuentan nombres de especie distintos con provincia pública. Se muestran las diez provincias con más especies documentadas. Es riqueza observada en esta colección, no una estimación de la riqueza natural. Selecciona una barra para acotar el territorio.'],
        'decadas' => ['Cobertura temporal de especies', 'Cada barra cuenta especies distintas documentadas en una década de colecta. Las diferencias pueden deberse al esfuerzo de muestreo y digitalización. Pulsa una barra para consultar esa década.'],
        'aptitud' => ['Registros aptos para cruces espaciales', 'La fracción apta reúne simultáneamente identificación a especie, fecha y coordenadas visibles. Es un criterio de completitud, no una certificación de precisión geográfica: comprueba incertidumbre y localidad antes de modelar.'],
        'raras' => ['Especies con pocos registros', 'Lista hasta doce especies con uno a tres registros publicados dentro de la selección. Ayuda a localizar vacíos de documentación y ejemplares para revisión; la rareza en la colección no equivale a amenaza ni rareza en la naturaleza.'],
    ];
    $iconoMenu = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>';
    $filtrosCatalogo = array_filter([
        'ft' => $filtros['taxon'] ?? null,
        'fprov' => $filtros['provincia'] ?? null,
        'fph' => $filtros['filo'] ?? null,
        'ffd' => isset($filtros['desde']) ? $filtros['desde'].'-01-01' : null,
        'ffh' => isset($filtros['hasta']) ? $filtros['hasta'].'-12-31' : null,
        'fmes' => $filtros['mes'] ?? null,
        'fid' => $filtros['identificacion'] ?? null,
        'fgeo' => $filtros['ubicacion'] ?? null,
        'fap' => ($filtros['aptitud'] ?? null) === 'completos' ? '1' : null,
        'fco' => $filtros['colector'] ?? null,
        'fm' => isset($filtros['metodo']) ? [$filtros['metodo']] : null,
    ], static fn ($valor) => $valor !== null && $valor !== '');
@endphp
<link rel="stylesheet" href="{{ asset('css/portal-estadisticas.css') }}">
<div class="atlas">
    <header class="atlas-intro"><div class="atlas-wrap atlas-intro-inner"><div><h1>Colección Biológica</h1><p class="atlas-lead">Explora la distribución documentada de invertebrados, su cobertura temporal y la completitud de los datos publicados.</p></div><span class="atlas-index">EPN · Datos públicos</span></div></header>
    <div class="atlas-wrap atlas-workspace">
        <aside class="atlas-sidebar">
            <details class="atlas-filter-drawer" @if($filtros !== [] || $errors->any()) open @endif>
                <summary>Filtros de investigación <span aria-hidden="true">☰</span></summary>
                <form id="filtros-estadisticas" class="atlas-filters" method="get" action="{{ route('portal.estadisticas') }}" aria-label="Filtros de investigación">
                    <p>Todos los paneles y el CSV usan la misma selección.</p>
                    <label class="atlas-field"><span>Nombre científico</span><input name="taxon" type="search" value="{{ $filtros['taxon'] ?? '' }}" maxlength="120" placeholder="Género o especie"></label>
                    <label class="atlas-field"><span>Provincia</span><select name="provincia"><option value="">Todas</option>@foreach($provincias as $provincia)<option value="{{ $provincia }}" @selected(($filtros['provincia'] ?? '') === $provincia)>{{ $provincia }}</option>@endforeach</select></label>
                    <label class="atlas-field"><span>Filo</span><select name="filo"><option value="">Todos</option>@foreach($filosDisponibles as $opcion)<option value="{{ $opcion['id'] }}" @selected(($filtros['filo'] ?? '') === $opcion['id'])>{{ $opcion['nombre_cientifico'] }}</option>@endforeach</select></label>
                    <div class="atlas-filter-pair"><label class="atlas-field"><span>Desde año</span><input name="desde" type="number" min="1800" max="2100" value="{{ $filtros['desde'] ?? '' }}" placeholder="1800"></label><label class="atlas-field"><span>Hasta año</span><input name="hasta" type="number" min="1800" max="2100" value="{{ $filtros['hasta'] ?? '' }}" placeholder="2100"></label></div>
                    <label class="atlas-field"><span>Mes de colecta</span><select name="mes"><option value="">Todos los meses</option>@foreach(['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'] as $indice => $nombreMes)<option value="{{ $indice + 1 }}" @selected((int)($filtros['mes'] ?? 0) === $indice + 1)>{{ $nombreMes }}</option>@endforeach</select></label>
                    <label class="atlas-field"><span>Determinación</span><select name="identificacion"><option value="">Todos los rangos</option><option value="especie" @selected(($filtros['identificacion'] ?? '') === 'especie')>Hasta especie</option><option value="superior" @selected(($filtros['identificacion'] ?? '') === 'superior')>Rango superior</option></select></label>
                    <label class="atlas-field"><span>Datos para análisis</span><select name="aptitud"><option value="">Todos los registros</option><option value="completos" @selected(($filtros['aptitud'] ?? '') === 'completos')>Especie + fecha + coordenadas visibles</option></select></label>
                    <label class="atlas-check"><input type="checkbox" name="ubicacion" value="1" @checked(($filtros['ubicacion'] ?? '') === '1')> Solo con ubicación pública</label>
                    <div class="atlas-filter-buttons"><a class="atlas-link" href="{{ route('portal.estadisticas') }}">Limpiar</a><button class="atlas-primary" type="submit">Aplicar filtros</button></div>
                    @if($errors->any())<ul class="atlas-validation" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
                </form>
            </details>
        </aside>
        <main class="atlas-content">
            <nav class="atlas-view-switch" aria-label="Vista de la colección">
                <a href="{{ route('portal.catalogo', ['vista' => 'tarjetas'] + $filtrosCatalogo) }}" aria-label="Vista de tarjetas"><span aria-hidden="true">▦</span> Tarjetas</a>
                <a href="{{ route('portal.catalogo', ['vista' => 'registros'] + $filtrosCatalogo) }}" aria-label="Vista de registros tabulares"><span aria-hidden="true">☷</span> Registros</a>
                <span aria-current="page"><span aria-hidden="true">◉</span> Mapa y análisis</span>
            </nav>
            <div class="atlas-stage">
                <section class="atlas-panel" id="panel-mapa" data-panel="mapa" aria-labelledby="titulo-mapa">
                    <div class="atlas-panel-header"><div><h2 id="titulo-mapa">Distribución de los registros</h2><p class="atlas-panel-subtitle">Puntos por cuadrícula de 0,25° · Mayús + arrastrar para abrir registros del área</p></div><div class="atlas-panel-tools"><button class="atlas-icon-button" type="button" data-ayuda="mapa" aria-label="Cómo interpretar el mapa">?</button><button class="atlas-icon-button" type="button" data-menu-trigger aria-label="Acciones del mapa" aria-expanded="false" aria-haspopup="menu">{!! $iconoMenu !!}</button></div></div>
                    <div class="atlas-menu" role="menu" hidden><button type="button" role="menuitem" data-action="download">Descargar cuadrículas y filos CSV</button><button type="button" role="menuitem" data-action="open-records">Explorar registros tabulares</button><button type="button" role="menuitem" data-action="open-mapped-records">Revisar registros con coordenadas públicas</button></div>
                    <div id="mapa-coleccion" class="atlas-map" role="region" aria-label="Mapa de cuadrículas coloreadas por filo dominante"></div>
                    @if($datos['mapa'] === [])<div class="atlas-map-empty">No hay coordenadas públicas para estos filtros.</div>@endif
                    <div class="atlas-map-footer"><span>Color: filo dominante · Tamaño: registros por cuadrícula. Selecciona un filo en la leyenda.</span><button type="button" data-ayuda="mapa">Cómo leerlo</button></div>
                    <div class="atlas-map-legend" id="leyenda-mapa" aria-label="Leyenda de filos del mapa"></div>
                </section>
                <section class="atlas-panel" id="panel-filos" data-panel="filos" aria-labelledby="titulo-filos">
                    <div class="atlas-panel-header"><div><h2 id="titulo-filos">Composición taxonómica</h2><p class="atlas-panel-subtitle">Selecciona un filo para destacarlo en el mapa</p></div><div class="atlas-panel-tools"><button class="atlas-icon-button" type="button" data-ayuda="filos" aria-label="Cómo interpretar los filos">?</button><button class="atlas-icon-button" type="button" data-menu-trigger aria-label="Acciones de filos" aria-expanded="false" aria-haspopup="menu">{!! $iconoMenu !!}</button></div></div>
                    <div class="atlas-menu" role="menu" hidden><button type="button" role="menuitem" data-action="download">Descargar filos CSV</button><button type="button" role="menuitem" data-action="open-records">Explorar registros</button></div>
                    <div class="atlas-taxon-body">@forelse($datos['filos'] as $filo => $cantidad)@php $proporcion = (int)$cantidad / $total * 100; @endphp<button class="atlas-taxon-row" type="button" data-filo-mapa="{{ $filo }}" aria-pressed="false"><span class="atlas-taxon-name">{{ $filo }}</span><span class="atlas-taxon-count">{{ number_format((int)$cantidad, 0, ',', '.') }} · {{ number_format($proporcion, 1, ',', '.') }} %</span><span class="atlas-bar" aria-hidden="true"><span style="width: {{ min(100, $proporcion) }}%"></span></span></button>@empty<p class="atlas-quality-caption">No hay filos asignados en esta selección.</p>@endforelse</div>
                </section>
            </div>
            <div class="atlas-panels-grid" role="group" aria-label="Análisis de la colección">
                <section class="atlas-panel" id="panel-riqueza" data-panel="riqueza" aria-labelledby="titulo-riqueza"><div class="atlas-panel-header"><div><h2 id="titulo-riqueza">Riqueza documentada por provincia</h2><p class="atlas-panel-subtitle">Especies distintas · no riqueza natural estimada</p></div><div class="atlas-panel-tools"><button class="atlas-icon-button" type="button" data-ayuda="riqueza" aria-label="Cómo interpretar la riqueza">?</button><button class="atlas-icon-button" type="button" data-menu-trigger aria-label="Acciones de riqueza" aria-expanded="false" aria-haspopup="menu">{!! $iconoMenu !!}</button></div></div><div class="atlas-menu" role="menu" hidden><button type="button" role="menuitem" data-action="download">Descargar riqueza CSV</button><button type="button" role="menuitem" data-action="open-records">Explorar registros</button></div><div class="atlas-chart-body"><canvas id="grafico-riqueza" role="img" aria-label="Especies distintas por provincia"></canvas></div></section>
                <section class="atlas-panel" id="panel-decadas" data-panel="decadas" aria-labelledby="titulo-decadas"><div class="atlas-panel-header"><div><h2 id="titulo-decadas">Cobertura temporal de especies</h2><p class="atlas-panel-subtitle">Especies distintas por década de colecta</p></div><div class="atlas-panel-tools"><button class="atlas-icon-button" type="button" data-ayuda="decadas" aria-label="Cómo interpretar la cobertura temporal">?</button><button class="atlas-icon-button" type="button" data-menu-trigger aria-label="Acciones de cobertura temporal" aria-expanded="false" aria-haspopup="menu">{!! $iconoMenu !!}</button></div></div><div class="atlas-menu" role="menu" hidden><button type="button" role="menuitem" data-action="download">Descargar décadas CSV</button><button type="button" role="menuitem" data-action="open-records">Explorar registros</button></div><div class="atlas-chart-body"><canvas id="grafico-decadas" role="img" aria-label="Especies documentadas por década"></canvas></div></section>
                <section class="atlas-panel" id="panel-aptitud" data-panel="aptitud" aria-labelledby="titulo-aptitud"><div class="atlas-panel-header"><div><h2 id="titulo-aptitud">Completitud para análisis espacial</h2><p class="atlas-panel-subtitle">Especie, fecha y coordenadas visibles a la vez</p></div><div class="atlas-panel-tools"><button class="atlas-icon-button" type="button" data-ayuda="aptitud" aria-label="Cómo interpretar la completitud">?</button><button class="atlas-icon-button" type="button" data-menu-trigger aria-label="Acciones de completitud" aria-expanded="false" aria-haspopup="menu">{!! $iconoMenu !!}</button></div></div><div class="atlas-menu" role="menu" hidden><button type="button" role="menuitem" data-action="download">Descargar completitud CSV</button><button type="button" role="menuitem" data-action="filter-ready">Filtrar registros completos</button></div><div class="atlas-quality"><div class="atlas-quality-number">{{ number_format((int)$r['aptos'], 0, ',', '.') }}</div><p>de {{ number_format((int)$r['registros'], 0, ',', '.') }} registros reúnen los tres campos.</p><div class="atlas-quality-track" aria-label="{{ number_format((int)$r['aptos'] / $total * 100, 1, ',', '.') }} por ciento"><span style="width: {{ min(100, (int)$r['aptos'] / $total * 100) }}%"></span></div><p class="atlas-quality-caption">Completitud ≠ precisión. Revisa incertidumbre de coordenadas antes de modelar.</p><button type="button" class="atlas-text-action" data-filter-ready>Ver selección completa →</button></div></section>
                <section class="atlas-panel" id="panel-raras" data-panel="raras" aria-labelledby="titulo-raras"><div class="atlas-panel-header"><div><h2 id="titulo-raras">Especies con pocos registros</h2><p class="atlas-panel-subtitle">Una a tres ocurrencias en la selección</p></div><div class="atlas-panel-tools"><button class="atlas-icon-button" type="button" data-ayuda="raras" aria-label="Cómo interpretar las especies con pocos registros">?</button><button class="atlas-icon-button" type="button" data-menu-trigger aria-label="Acciones de especies con pocos registros" aria-expanded="false" aria-haspopup="menu">{!! $iconoMenu !!}</button></div></div><div class="atlas-menu" role="menu" hidden><button type="button" role="menuitem" data-action="download">Descargar selección CSV</button><button type="button" role="menuitem" data-action="open-records">Explorar registros</button></div><div class="atlas-top-species">@forelse($datos['raras'] as $especie)<div class="atlas-species-row"><a href="{{ route('portal.catalogo', ['nivel' => 'species', 'taxon' => $especie['nombre']]) }}">{{ $especie['nombre'] }}</a><strong>{{ $especie['total'] }}</strong></div>@empty<p class="atlas-quality-caption">No hay especies con hasta tres registros en esta selección.</p>@endforelse</div></section>
                <section class="atlas-panel atlas-list-panel" id="panel-lista" data-panel="lista" aria-labelledby="titulo-lista"><div class="atlas-panel-header"><div><h2 id="titulo-lista">Lista de especies de la colección</h2><p class="atlas-panel-subtitle">Veinte más documentadas · CSV completo</p></div><div class="atlas-list-actions"><a class="atlas-small-link" href="{{ route('portal.lista-especies', $filtros) }}">Descargar lista completa</a><button class="atlas-icon-button" type="button" data-menu-trigger aria-label="Acciones de lista de especies" aria-expanded="false" aria-haspopup="menu">{!! $iconoMenu !!}</button></div></div><div class="atlas-menu" role="menu" hidden><button type="button" role="menuitem" data-action="download">Descargar lista completa CSV</button><button type="button" role="menuitem" data-action="open-records">Explorar registros</button></div><div class="atlas-list">@forelse($datos['especies'] as $especie)<div class="atlas-species-row"><a href="{{ route('portal.catalogo', ['nivel' => 'species', 'taxon' => $especie['nombre']]) }}">{{ $especie['nombre'] }}</a><strong>{{ number_format((int)$especie['total'], 0, ',', '.') }}</strong></div>@empty<p class="atlas-quality-caption">No hay especies identificadas para esta selección.</p>@endforelse</div></section>
            </div>
            <p class="atlas-source-note">Los conteos describen especímenes publicados de esta colección. No corrigen sesgos de muestreo ni sustituyen revisión taxonómica o geográfica. Criterios de referencia: <a href="https://techdocs.gbif.org/en/data-publishing/data-quality-recommendations" target="_blank" rel="noopener noreferrer">calidad de ocurrencias GBIF</a> y <a href="https://dwc.tdwg.org/list/" target="_blank" rel="noopener noreferrer">Darwin Core</a>.</p>
        </main>
    </div>
    <dialog class="atlas-help" id="ayuda-estadisticas" aria-labelledby="titulo-ayuda-estadisticas"><div class="atlas-help-head"><h2 id="titulo-ayuda-estadisticas"></h2><button class="atlas-help-close" type="button" aria-label="Cerrar ayuda">×</button></div><div class="atlas-help-content" id="contenido-ayuda-estadisticas"></div></dialog>
    @foreach($guias as $clave => [$titulo, $explicacion])
        <template id="guia-{{ $clave }}" data-titulo="{{ $titulo }}">
            <div class="atlas-help-visual atlas-help-visual--{{ $clave }}" aria-hidden="true">
                @switch($clave)
                    @case('mapa')
                        <span>◉ {{ number_format(count($datos['mapa']), 0, ',', '.') }} cuadrículas visibles</span>
                        @foreach(array_slice(array_keys($datos['filos']), 0, 2) as $nombreFilo)<span>● {{ $nombreFilo }}</span>@endforeach
                        @break
                    @case('filos')
                        @forelse(array_slice($datos['filos'], 0, 3, true) as $nombreFilo => $cantidadFilo)
                            <span>{{ $nombreFilo }} · {{ number_format((int)$cantidadFilo, 0, ',', '.') }} <i style="width:{{ min(100, (int)$cantidadFilo / $total * 100) }}%"></i></span>
                        @empty<span>Sin filos visibles</span>@endforelse
                        @break
                    @case('riqueza')
                        @forelse(array_slice($datos['riqueza'], 0, 3) as $filaRiqueza)
                            <span>{{ $filaRiqueza['provincia'] }} · {{ $filaRiqueza['especies'] }} especies <i style="width:{{ min(100, (int)$filaRiqueza['especies'] / max(1, (int)$datos['riqueza'][0]['especies']) * 100) }}%"></i></span>
                        @empty<span>Sin provincias con especies visibles</span>@endforelse
                        @break
                    @case('decadas')
                        @forelse(array_slice($datos['decadas'], -4) as $filaDecada)<span>{{ $filaDecada['decada'] }} · {{ $filaDecada['especies'] }} especies</span>@empty<span>Sin décadas visibles</span>@endforelse
                        @break
                    @case('aptitud')<span>{{ number_format((int)$r['aptos'], 0, ',', '.') }} de {{ number_format((int)$r['registros'], 0, ',', '.') }} con los tres campos</span>@break
                    @case('raras')
                        @forelse(array_slice($datos['raras'], 0, 3) as $filaRara)<span><em>{{ $filaRara['nombre'] }}</em> · {{ $filaRara['total'] }}</span>@empty<span>Sin especies de 1 a 3 registros</span>@endforelse
                        @break
                @endswitch
            </div>
            <div class="atlas-help-copy"><p>{{ $explicacion }}</p></div>
        </template>
    @endforeach
    <div class="atlas-toast" id="aviso-estadisticas" role="status" aria-live="polite" hidden></div>
</div>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
window.portalEstadisticasDatos = {
    riqueza: @json($datos['riqueza']),
    decadas: @json($datos['decadas']),
    raras: @json($datos['raras']),
    mapa: @json($datos['mapa']),
    filos: @json($datos['filos']),
    resumen: @json($datos['resumen']),
    seleccion: @json($filtros),
    listaUrl: @json(route('portal.lista-especies', $filtros)),
    catalogoUrl: @json(route('portal.catalogo'))
};
</script>
<script src="{{ asset('js/portal-estadisticas.js') }}" defer></script>
@endcomponent
