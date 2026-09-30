@component('layouts.portal', ['title' => 'Estadísticas de la colección'])
<div class="min-h-screen bg-[#f3f7fb] pb-14">
    <header class="border-b border-blue-navy/10 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-9 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-start justify-between gap-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-science-blue">Colección biológica · análisis público</p>
                    <h1 class="mt-2 font-display text-3xl font-bold tracking-tight text-blue-navy sm:text-4xl">Mapa y estadísticas</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-text-secondary">Distribución taxonómica, tiempo y geografía de los registros publicados. Los puntos del mapa representan cuadrículas agrupadas, no sitios de colecta exactos.</p>
                </div>
            </div>
            <form method="get" action="{{ route('portal.estadisticas') }}" class="mt-7 grid gap-3 rounded-xl border border-blue-navy/10 bg-[#f8fbfe] p-4 sm:grid-cols-[minmax(0,1fr)_9rem_9rem_auto] sm:items-end" aria-label="Filtros de estadísticas">
                <label class="text-xs font-semibold text-blue-navy">Provincia
                    <select name="provincia" class="mt-1 block h-10 w-full rounded-md border border-blue-navy/20 bg-white px-3 text-sm font-normal">
                        <option value="">Todas las provincias</option>
                        @foreach($provincias as $provincia)
                            <option value="{{ $provincia }}" @selected(($filtros['provincia'] ?? '') === $provincia)>{{ $provincia }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-semibold text-blue-navy">Desde el año
                    <input name="desde" type="number" min="1800" max="2100" value="{{ $filtros['desde'] ?? '' }}" placeholder="Todos" class="mt-1 block h-10 w-full rounded-md border border-blue-navy/20 bg-white px-3 text-sm font-normal">
                </label>
                <label class="text-xs font-semibold text-blue-navy">Hasta el año
                    <input name="hasta" type="number" min="1800" max="2100" value="{{ $filtros['hasta'] ?? '' }}" placeholder="Todos" class="mt-1 block h-10 w-full rounded-md border border-blue-navy/20 bg-white px-3 text-sm font-normal">
                </label>
                <button class="h-10 rounded-md bg-science-blue px-5 text-sm font-semibold text-white hover:bg-blue-navy">Aplicar filtros</button>
            </form>
        </div>
    </header>

    <div class="mx-auto max-w-7xl px-4 pt-8 sm:px-6 lg:px-8">
        @php $r = $datos['resumen']; @endphp
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach([
                ['Registros públicos', $r['registros'], 'bg-blue-50 text-science-blue'],
                ['Con identificación de especie', $r['identificados'], 'bg-emerald-50 text-bio-green'],
                ['Con fecha visible', $r['fechados'], 'bg-amber-50 text-amber-700'],
                ['Con ubicación visible', $r['georreferenciados'], 'bg-sky-50 text-sky-700'],
            ] as [$etiqueta, $valor, $color])
                <div class="rounded-xl border border-blue-navy/10 bg-white p-5 shadow-sm">
                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide {{ $color }}">{{ $etiqueta }}</span>
                    <strong class="mt-4 block font-display text-3xl tabular-nums text-blue-navy">{{ number_format($valor, 0, ',', '.') }}</strong>
                </div>
            @endforeach
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1.6fr)_minmax(19rem,1fr)]">
            <section class="overflow-hidden rounded-xl border border-blue-navy/10 bg-white shadow-sm" aria-labelledby="titulo-mapa-coleccion">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-blue-navy/10 px-5 py-4">
                    <div><h2 id="titulo-mapa-coleccion" class="font-display text-lg font-bold text-blue-navy">Mapa de registros</h2><p class="text-xs text-text-secondary">Cuadrículas de 0,25° · Mantén Mayús y arrastra para buscar registros de un área</p></div>
                    <div class="flex gap-2"><button id="alternar-mapa" type="button" class="rounded-md border border-blue-navy/15 px-3 py-2 text-xs font-semibold text-blue-navy">Mostrar presencia</button><button id="ampliar-mapa" type="button" class="rounded-md border border-blue-navy/15 px-3 py-2 text-xs font-semibold text-blue-navy">Pantalla completa</button></div>
                </div>
                @if($datos['mapa'] === [])
                    <div class="flex h-80 items-center justify-center text-sm text-text-secondary">No hay coordenadas públicas para estos filtros.</div>
                @else
                    <div id="mapa-coleccion" class="h-[420px] w-full bg-sky-50"></div>
                @endif
                <p class="px-5 py-3 text-xs leading-5 text-text-secondary">El tamaño del círculo indica la cantidad de registros. Algunas coordenadas son aproximadas y están identificadas como tales en el catálogo.</p>
            </section>
            <section class="rounded-xl border border-blue-navy/10 bg-white p-5 shadow-sm" aria-labelledby="titulo-filos">
                <div class="flex items-center justify-between gap-3"><h2 id="titulo-filos" class="font-display text-lg font-bold text-blue-navy">Composición taxonómica</h2><span class="text-xs text-text-secondary">Por filo</span></div>
                <div class="mt-5 space-y-3">
                    @foreach($datos['filos'] as $filo => $cantidad)
                        @php $proporcion = $r['registros'] > 0 ? $cantidad / $r['registros'] * 100 : 0; @endphp
                        <div>
                            <div class="mb-1 flex justify-between gap-3 text-xs"><span class="font-semibold text-blue-navy">{{ $filo }}</span><span class="tabular-nums text-text-secondary">{{ number_format($cantidad, 0, ',', '.') }} · {{ number_format($proporcion, 1, ',', '.') }} %</span></div>
                            <div class="h-2 overflow-hidden rounded-full bg-[#e5edf4]"><div class="h-full rounded-full {{ $filo === 'Sin filo' ? 'bg-amber-500' : 'bg-science-blue' }}" style="width: {{ min(100, $proporcion) }}%"></div></div>
                        </div>
                    @endforeach
                </div>
                <p class="mt-5 border-t border-blue-navy/10 pt-4 text-xs leading-5 text-text-secondary">Solo se muestran ejemplares cuya clasificación por filo está confirmada.</p>
            </section>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <section class="rounded-xl border border-blue-navy/10 bg-white p-5 shadow-sm" aria-labelledby="titulo-temporal">
                <div class="mb-4"><h2 id="titulo-temporal" class="font-display text-lg font-bold text-blue-navy">Registros por año de colecta</h2><p class="text-xs text-text-secondary">Solo fechas normalizadas y visibles</p></div>
                <div class="h-64"><canvas id="grafico-anios" role="img" aria-label="Gráfico de registros por año"></canvas></div>
            </section>
            <section class="rounded-xl border border-blue-navy/10 bg-white p-5 shadow-sm" aria-labelledby="titulo-provincias">
                <div class="mb-4"><h2 id="titulo-provincias" class="font-display text-lg font-bold text-blue-navy">Provincias más representadas</h2><p class="text-xs text-text-secondary">Hasta doce provincias con dato público</p></div>
                <div class="h-64"><canvas id="grafico-provincias" role="img" aria-label="Gráfico de registros por provincia"></canvas></div>
            </section>
        </div>
        <section class="mt-5 overflow-hidden rounded-xl border border-blue-navy/10 bg-white shadow-sm" aria-labelledby="titulo-lista-regional">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-blue-navy/10 px-5 py-4">
                <div><h2 id="titulo-lista-regional" class="font-display text-lg font-bold text-blue-navy">Lista de especies {{ !empty($filtros['provincia']) ? 'de '.$filtros['provincia'] : 'de la colección' }}</h2><p class="text-xs text-text-secondary">Primeras veinte por número de registros; descarga la lista completa en CSV.</p></div>
                <a href="{{ route('portal.lista-especies', $filtros) }}" class="inline-flex min-h-10 items-center rounded-md border border-science-blue px-4 text-xs font-semibold text-science-blue hover:bg-sky-50">Descargar lista completa</a>
            </div>
            <div class="grid gap-x-8 px-5 py-4 sm:grid-cols-2">
                @forelse($datos['especies'] as $especie)
                    <a href="{{ route('portal.catalogo', ['nivel' => 'species', 'taxon' => $especie['nombre']]) }}" class="flex justify-between gap-3 border-b border-blue-navy/5 py-2 text-sm hover:text-science-blue"><span class="italic">{{ $especie['nombre'] }}</span><span class="tabular-nums text-text-secondary">{{ number_format($especie['total'], 0, ',', '.') }}</span></a>
                @empty
                    <p class="py-4 text-sm text-text-secondary">No hay especies identificadas para esta selección.</p>
                @endforelse
            </div>
        </section>
        <p class="mt-5 text-xs leading-5 text-text-secondary">Los gráficos cuentan registros de la colección, no estiman abundancia silvestre. La selección temporal excluye fechas vacías y la cartografía omite coordenadas restringidas.</p>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(() => {
    const anios = @json($datos['anios']);
    const provincias = @json($datos['provincias']);
    const celdas = @json($datos['mapa']);
    const iniciar = () => {
        if (window.HubDigitalChart) {
            const base = {responsive: true, maintainAspectRatio: false, animation: false, plugins: {legend: {display: false}}, scales: {x: {grid: {display: false}, ticks: {maxTicksLimit: 10}}, y: {beginAtZero: true, grid: {color: '#e8eef4'}}}};
            const temporal = document.getElementById('grafico-anios');
            if (temporal) new window.HubDigitalChart(temporal, {type: 'bar', data: {labels: anios.map(p => p.anio), datasets: [{data: anios.map(p => Number(p.total)), backgroundColor: '#176ca7', borderRadius: 2}]}, options: base});
            const geografia = document.getElementById('grafico-provincias');
            if (geografia) new window.HubDigitalChart(geografia, {type: 'bar', data: {labels: provincias.map(p => p.provincia), datasets: [{data: provincias.map(p => Number(p.total)), backgroundColor: '#198268', borderRadius: 3}]}, options: {...base, indexAxis: 'y', scales: {x: {beginAtZero: true, grid: {color: '#e8eef4'}}, y: {grid: {display: false}}}}});
        }
        const contenedor = document.getElementById('mapa-coleccion');
        if (!contenedor || celdas.length === 0) return;
        if (!window.L) {
            contenedor.classList.add('flex', 'items-center', 'justify-center', 'p-5', 'text-sm', 'text-text-secondary');
            contenedor.textContent = 'No se pudo cargar el mapa. Recarga la página para intentarlo nuevamente.';
            return;
        }
        const mapa = L.map(contenedor, {scrollWheelZoom: false, preferCanvas: true});
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors', maxZoom: 18}).addTo(mapa);
        L.control.scale({imperial: false}).addTo(mapa);
        let volumen = true;
        const puntos = celdas.map(p => {
            const n = Number(p.total);
            const circulo = L.circleMarker([Number(p.lat), Number(p.lon)], {radius: Math.min(19, 4 + Math.sqrt(n) * 0.8), color: '#0f547e', weight: 1, fillColor: '#1284b6', fillOpacity: 0.55}).addTo(mapa);
            circulo.bindPopup(`${n.toLocaleString('es-EC')} registros en esta cuadrícula`);
            return {circulo, n};
        });
        mapa.fitBounds(L.featureGroup(puntos.map(p => p.circulo)).getBounds().pad(0.08), {maxZoom: 10});
        mapa.on('boxzoomend', event => {
            const limites = event.boxZoomBounds;
            if (!limites) return;
            const url = new URL(@json(route('portal.catalogo')), window.location.origin);
            url.searchParams.set('vista', 'registros');
            url.searchParams.set('flat', limites.getSouth().toFixed(6));
            url.searchParams.set('flax', limites.getNorth().toFixed(6));
            url.searchParams.set('flon', limites.getWest().toFixed(6));
            url.searchParams.set('flox', limites.getEast().toFixed(6));
            window.location.assign(url.toString());
        });
        document.getElementById('alternar-mapa')?.addEventListener('click', event => {
            volumen = !volumen;
            puntos.forEach(p => p.circulo.setRadius(volumen ? Math.min(19, 4 + Math.sqrt(p.n) * 0.8) : 5));
            event.currentTarget.textContent = volumen ? 'Mostrar presencia' : 'Mostrar volumen';
        });
        document.getElementById('ampliar-mapa')?.addEventListener('click', async () => {
            if (contenedor.requestFullscreen) await contenedor.requestFullscreen();
            setTimeout(() => mapa.invalidateSize(), 100);
        });
        document.addEventListener('fullscreenchange', () => mapa.invalidateSize());
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar, {once: true}); else iniciar();
})();
</script>
@endcomponent
