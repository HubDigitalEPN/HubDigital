@component('layouts.portal', ['title' => 'Estadísticas de la colección'])
@php
    $r = $datos['resumen'];
    $total = max(1, (int) $r['registros']);
    $guias = [
        'mapa' => [
            'titulo' => 'Leer el mapa de registros', 'imagen' => 'images/estadisticas-campo-ecuador.png', 'alt' => 'Imagen ilustrativa de investigación de campo en bosque nublado',
            'intro' => 'El mapa ubica registros publicados de la colección dentro del territorio. Está pensado para explorar patrones de documentación y abrir una búsqueda geográfica en el catálogo.',
            'pasos' => [
                ['Qué representa cada círculo', 'Una cuadrícula de 0,25° agrupa varios registros. Su tamaño indica cuántos registros públicos contiene; no señala un sitio de colecta exacto.'],
                ['Cómo explorar un área', 'Acerca el mapa con los controles. Mantén Mayús y arrastra para seleccionar un rectángulo: se abrirán los registros públicos del catálogo comprendidos en él.'],
                ['Cómo cambiar la lectura', 'En el menú de tres puntos puedes alternar entre volumen y presencia, volver a Ecuador, ampliar el mapa o descargar los datos agrupados.'],
                ['Límite de interpretación', 'Las coordenadas restringidas no se dibujan. Una zona sin círculos significa ausencia de registros cartografiables en esta selección, no ausencia de especies.'],
            ],
        ],
        'filos' => [
            'titulo' => 'Entender la composición taxonómica', 'imagen' => 'images/dashboard-nudibranquio.jpg', 'alt' => 'Nudibranquio observado en su ambiente natural',
            'intro' => 'Un filo reúne grandes linajes de animales. Este panel muestra cómo se distribuyen los registros de la colección entre filos confirmados.',
            'pasos' => [
                ['Lee el conteo', 'Cada número corresponde a ejemplares publicados asignados a ese filo, después de aplicar los filtros superiores.'],
                ['Lee la barra', 'La longitud expresa la proporción respecto de todos los registros filtrados; permite comparar grupos de un vistazo.'],
                ['Ten en cuenta la clasificación', 'Los ejemplares sin filo confirmado quedan fuera de esta lista. Por eso las barras no siempre suman el 100 % del total.'],
                ['Continúa en el catálogo', 'Usa el filtro Filo para acotar toda la página a un grupo y, luego, consulta sus especies en la lista inferior.'],
            ],
        ],
        'anios' => [
            'titulo' => 'Interpretar los años de colecta', 'imagen' => 'images/portal-laboratorio-hero.jpg', 'alt' => 'Trabajo de curaduría con ejemplares de la colección',
            'intro' => 'La serie temporal cuenta registros según el año en que se colectó el ejemplar, siempre que la fecha pueda mostrarse públicamente.',
            'pasos' => [
                ['Qué cuenta una barra', 'La altura indica el número de registros con fecha visible de ese año. Puedes pasar el cursor sobre una barra para ver el valor exacto.'],
                ['Usa el intervalo', 'Los campos Desde y Hasta limitan todos los paneles y la lista CSV. También puedes pulsar una barra para seleccionar ese año.'],
                ['Evita una conclusión errónea', 'Un aumento de registros puede reflejar campañas de muestreo, digitalización o publicación. No demuestra que haya más organismos en la naturaleza.'],
            ],
        ],
        'provincias' => [
            'titulo' => 'Comparar provincias', 'imagen' => 'images/estadisticas-campo-ecuador.png', 'alt' => 'Imagen ilustrativa de un paisaje de bosque nublado',
            'intro' => 'Este ranking muestra las provincias que aparecen con más frecuencia en los registros publicados con provincia visible.',
            'pasos' => [
                ['Lee el orden', 'Las barras se ordenan por cantidad de registros. Se muestran hasta doce provincias para facilitar la comparación.'],
                ['Acota el territorio', 'Selecciona una provincia en los filtros o pulsa su barra para actualizar el mapa, los gráficos y la lista de especies.'],
                ['Interpreta el vacío', 'Una provincia fuera del ranking puede tener menos registros, datos territoriales incompletos o información restringida.'],
            ],
        ],
        'calidad' => [
            'titulo' => 'Qué muestra la documentación', 'imagen' => 'images/portal-depositos-hero.webp', 'alt' => 'Ejemplares identificados y conservados en cajones de una colección científica',
            'intro' => 'Un registro puede estar publicado aunque algunos de sus campos no estén disponibles. Estos indicadores describen qué parte del conjunto filtrado puede consultarse con más detalle.',
            'pasos' => [
                ['Identificación a especie', 'Cuenta registros cuyo taxón llega al rango especie y cuyo nombre científico se puede mostrar.'],
                ['Fecha visible', 'Cuenta registros con una fecha de colecta normalizada que está autorizada para divulgación.'],
                ['Ubicación visible', 'Cuenta registros con ambas coordenadas disponibles y autorizadas. El mapa agrupa esas coordenadas para proteger la precisión de los puntos.'],
                ['Cómo usarlo', 'Compara cada porcentaje con el total de registros filtrados. Los tres grupos pueden solaparse: un mismo ejemplar puede tener nombre, fecha y ubicación.'],
            ],
        ],
        'metodos' => [
            'titulo' => 'Entender los métodos de colecta', 'imagen' => 'images/portal-laboratorio-hero.jpg', 'alt' => 'Trabajo de curaduría con ejemplares de la colección',
            'intro' => 'Los métodos describen cómo se obtuvo una muestra. Este panel resume los protocolos de colecta que pueden divulgarse en los registros filtrados.',
            'pasos' => [
                ['Qué cuenta cada fila', 'El número indica cuántos registros publicados usan ese protocolo y permiten mostrarlo. Se presentan los cinco protocolos con más registros.'],
                ['Filtra por un método', 'Selecciona un protocolo en la franja de filtros o pulsa una fila para actualizar todos los análisis y el CSV.'],
                ['Interpreta los vacíos', 'Un registro puede no tener método documentado o puede tener el campo restringido. Esos casos no aparecen en esta clasificación.'],
            ],
        ],
        'lista' => [
            'titulo' => 'Usar la lista de especies', 'imagen' => 'images/portal-depositos-hero.webp', 'alt' => 'Cajón de la colección con ejemplares etiquetados',
            'intro' => 'La lista conecta la vista estadística con la exploración de especies y con una descarga reutilizable de los conteos.',
            'pasos' => [
                ['Consulta desde la página', 'Se muestran las primeras veinte especies de la selección, ordenadas por número de registros. Cada nombre enlaza al catálogo.'],
                ['Descarga la lista completa', 'El CSV incluye todas las especies que cumplen los filtros activos, con su conteo; usa punto y coma como separador.'],
                ['Lee con cautela', 'El archivo resume registros publicados con nombre visible. No incluye ejemplares sin identificación a especie.'],
            ],
        ],
    ];
@endphp
<link rel="stylesheet" href="{{ asset('css/portal-estadisticas.css') }}">
<div class="atlas">
    <header class="atlas-intro">
        <div class="atlas-wrap atlas-intro-inner">
            <div>
                <h1>Mapa y estadísticas</h1>
                <p class="atlas-lead">Descubre qué invertebrados reúne la colección, dónde y cuándo se registraron y cómo fueron colectados. Filtra los datos públicos y abre sus especies en el catálogo.</p>
            </div>
            <span class="atlas-index" aria-hidden="true">Colección biológica · EPN</span>
        </div>
    </header>

    <div class="atlas-filter-area">
        <form id="filtros-estadisticas" class="atlas-wrap atlas-filters" method="get" action="{{ route('portal.estadisticas') }}" aria-label="Filtros de estadísticas">
            <div class="atlas-filter-grid">
                <label class="atlas-field"><span>Provincia</span><select name="provincia"><option value="">Todas las provincias</option>@foreach($provincias as $provincia)<option value="{{ $provincia }}" @selected(($filtros['provincia'] ?? '') === $provincia)>{{ $provincia }}</option>@endforeach</select></label>
                <label class="atlas-field"><span>Desde el año</span><input name="desde" type="number" min="1800" max="2100" value="{{ $filtros['desde'] ?? '' }}" placeholder="Todos"></label>
                <label class="atlas-field"><span>Hasta el año</span><input name="hasta" type="number" min="1800" max="2100" value="{{ $filtros['hasta'] ?? '' }}" placeholder="Todos"></label>
                <label class="atlas-field"><span>Filo</span><select name="filo"><option value="">Todos los filos</option>@foreach($filosDisponibles as $filoOpcion)<option value="{{ $filoOpcion['id'] }}" @selected(($filtros['filo'] ?? '') === $filoOpcion['id'])>{{ $filoOpcion['nombre_cientifico'] }}</option>@endforeach</select></label>
                <label class="atlas-field"><span>Colector/a</span><input name="colector" type="search" value="{{ $filtros['colector'] ?? '' }}" maxlength="120" placeholder="Buscar nombre"></label>
                <label class="atlas-field"><span>Identificación</span><select name="identificacion"><option value="">Todas</option><option value="especie" @selected(($filtros['identificacion'] ?? '') === 'especie')>A especie</option><option value="superior" @selected(($filtros['identificacion'] ?? '') === 'superior')>Rango superior</option></select></label>
                <label class="atlas-field"><span>Método de colecta</span><select name="metodo"><option value="">Todos los métodos</option>@foreach($metodosDisponibles as $metodoOpcion)<option value="{{ $metodoOpcion }}" @selected(($filtros['metodo'] ?? '') === $metodoOpcion)>{{ $metodoOpcion }}</option>@endforeach</select></label>
            </div>
            <div class="atlas-filter-actions">
                <label class="atlas-check"><input type="checkbox" name="ubicacion" value="1" @checked(($filtros['ubicacion'] ?? '') === '1')><span>Solo con ubicación pública</span></label>
                <div class="atlas-filter-buttons"><a class="atlas-link" href="{{ route('portal.estadisticas') }}">Limpiar filtros</a><button class="atlas-primary" type="submit">Aplicar filtros</button></div>
            </div>
            @error('hasta')<p class="atlas-validation" role="alert">{{ $message }}</p>@enderror
        </form>
    </div>

    <div class="atlas-wrap atlas-main">
        <div class="atlas-stage">
            <aside class="atlas-metrics" aria-label="Resumen de la selección"><dl class="contents">
                @foreach([['Registros públicos', $r['registros']], ['Con identificación de especie', $r['identificados']], ['Con fecha visible', $r['fechados']], ['Con ubicación visible', $r['georreferenciados']]] as [$etiqueta, $valor])
                    <div class="atlas-metric"><dt>{{ $etiqueta }}</dt><dd>{{ number_format((int) $valor, 0, ',', '.') }}</dd></div>
                @endforeach
            </dl></aside>

            <section class="atlas-panel" id="panel-mapa" data-panel="mapa" aria-labelledby="titulo-mapa">
                <div class="atlas-panel-header"><div><h2 id="titulo-mapa">Registros por territorio</h2><p class="atlas-panel-subtitle">Cuadrículas de 0,25° · Mayús + arrastrar para consultar un área</p></div><div class="atlas-panel-tools"><button class="atlas-icon-button" type="button" data-ayuda="mapa" aria-label="Ayuda sobre el mapa">?</button><button class="atlas-icon-button" type="button" data-menu-trigger aria-label="Acciones del mapa" aria-expanded="false" aria-haspopup="menu"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg></button></div></div>
                <div class="atlas-menu" role="menu" hidden><button type="button" role="menuitem" data-action="reset-map">Volver a Ecuador</button><button type="button" role="menuitem" data-action="toggle-map">Mostrar presencia</button><button type="button" role="menuitem" data-action="download">Descargar cuadrículas CSV</button><button type="button" role="menuitem" data-action="copy">Copiar enlace al panel</button><button type="button" role="menuitem" data-action="fullscreen">Ampliar panel</button></div>
                <div id="mapa-coleccion" class="atlas-map" role="region" aria-label="Mapa interactivo de registros públicos centrado en Ecuador"></div>
                @if($datos['mapa'] === [])<div class="atlas-map-empty">No hay coordenadas públicas para estos filtros.</div>@endif
                <div class="atlas-map-footer"><span>El tamaño del círculo representa el número de registros agrupados.</span><button type="button" data-ayuda="mapa">Cómo leer el mapa</button></div>
            </section>

            <section class="atlas-panel" id="panel-filos" data-panel="filos" aria-labelledby="titulo-filos">
                <div class="atlas-panel-header"><div><h2 id="titulo-filos">Composición taxonómica</h2><p class="atlas-panel-subtitle">Registros agrupados por filo</p></div><div class="atlas-panel-tools"><button class="atlas-icon-button" type="button" data-ayuda="filos" aria-label="Ayuda sobre composición taxonómica">?</button><button class="atlas-icon-button" type="button" data-menu-trigger aria-label="Acciones de composición taxonómica" aria-expanded="false" aria-haspopup="menu"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg></button></div></div>
                <div class="atlas-menu" role="menu" hidden><button type="button" role="menuitem" data-action="download">Descargar datos CSV</button><button type="button" role="menuitem" data-action="copy">Copiar enlace al panel</button><button type="button" role="menuitem" data-action="fullscreen">Ampliar panel</button></div>
                <div class="atlas-taxon-body">
                    @forelse($datos['filos'] as $filo => $cantidad)
                        @php $proporcion = (int) $cantidad / $total * 100; @endphp
                        <div class="atlas-taxon-row"><span class="atlas-taxon-name" title="{{ $filo }}">{{ $filo }}</span><span class="atlas-taxon-count">{{ number_format((int) $cantidad, 0, ',', '.') }} · {{ number_format($proporcion, 1, ',', '.') }} %</span><div class="atlas-bar" aria-hidden="true"><span style="width: {{ min(100, $proporcion) }}%"></span></div></div>
                    @empty<p class="atlas-quality-caption">No hay filos confirmados para esta selección.</p>@endforelse
                </div>
            </section>
        </div>

        <p class="atlas-mobile-hint">Desliza para ver los cuatro análisis →</p>
        <div class="atlas-panels-grid" role="group" aria-label="Análisis de la colección">
            <section class="atlas-panel" id="panel-anios" data-panel="anios" aria-labelledby="titulo-anios"><div class="atlas-panel-header"><div><h2 id="titulo-anios">Registros por año de colecta</h2><p class="atlas-panel-subtitle">Fechas normalizadas y visibles</p></div><div class="atlas-panel-tools"><button class="atlas-icon-button" type="button" data-ayuda="anios" aria-label="Ayuda sobre años de colecta">?</button><button class="atlas-icon-button" type="button" data-menu-trigger aria-label="Acciones de registros por año" aria-expanded="false" aria-haspopup="menu"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg></button></div></div><div class="atlas-menu" role="menu" hidden><button type="button" role="menuitem" data-action="download">Descargar datos CSV</button><button type="button" role="menuitem" data-action="copy">Copiar enlace al panel</button><button type="button" role="menuitem" data-action="fullscreen">Ampliar panel</button></div><div class="atlas-chart-body"><canvas id="grafico-anios" role="img" aria-label="Registros por año de colecta"></canvas></div></section>
            <section class="atlas-panel" id="panel-provincias" data-panel="provincias" aria-labelledby="titulo-provincias"><div class="atlas-panel-header"><div><h2 id="titulo-provincias">Provincias más representadas</h2><p class="atlas-panel-subtitle">Hasta doce con dato público</p></div><div class="atlas-panel-tools"><button class="atlas-icon-button" type="button" data-ayuda="provincias" aria-label="Ayuda sobre provincias">?</button><button class="atlas-icon-button" type="button" data-menu-trigger aria-label="Acciones de provincias" aria-expanded="false" aria-haspopup="menu"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg></button></div></div><div class="atlas-menu" role="menu" hidden><button type="button" role="menuitem" data-action="download">Descargar datos CSV</button><button type="button" role="menuitem" data-action="copy">Copiar enlace al panel</button><button type="button" role="menuitem" data-action="fullscreen">Ampliar panel</button></div><div class="atlas-chart-body"><canvas id="grafico-provincias" role="img" aria-label="Registros públicos por provincia"></canvas></div></section>
            <section class="atlas-panel" id="panel-calidad" data-panel="calidad" aria-labelledby="titulo-calidad"><div class="atlas-panel-header"><div><h2 id="titulo-calidad">Documentación visible</h2><p class="atlas-panel-subtitle">Cobertura de los campos públicos</p></div><div class="atlas-panel-tools"><button class="atlas-icon-button" type="button" data-ayuda="calidad" aria-label="Ayuda sobre documentación visible">?</button><button class="atlas-icon-button" type="button" data-menu-trigger aria-label="Acciones de documentación visible" aria-expanded="false" aria-haspopup="menu"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg></button></div></div><div class="atlas-menu" role="menu" hidden><button type="button" role="menuitem" data-action="download">Descargar datos CSV</button><button type="button" role="menuitem" data-action="copy">Copiar enlace al panel</button><button type="button" role="menuitem" data-action="fullscreen">Ampliar panel</button></div><div class="atlas-quality">@foreach([['A especie', $r['identificados']], ['Fecha visible', $r['fechados']], ['Ubicación visible', $r['georreferenciados']]] as [$etiqueta, $valor])@php $porcentaje = (int) $valor / $total * 100; @endphp<div class="atlas-quality-item"><div class="atlas-quality-top"><span>{{ $etiqueta }}</span><strong>{{ number_format($porcentaje, 1, ',', '.') }} %</strong></div><div class="atlas-quality-track" aria-hidden="true"><span style="width: {{ min(100, $porcentaje) }}%"></span></div></div>@endforeach<p class="atlas-quality-caption">Porcentaje de los {{ number_format((int) $r['registros'], 0, ',', '.') }} registros filtrados. Las categorías pueden coincidir.</p></div></section>
            <section class="atlas-panel" id="panel-metodos" data-panel="metodos" aria-labelledby="titulo-metodos">
                <div class="atlas-panel-header"><div><h2 id="titulo-metodos">Métodos de colecta</h2><p class="atlas-panel-subtitle">Cinco protocolos públicos más frecuentes</p></div><div class="atlas-panel-tools"><button class="atlas-icon-button" type="button" data-ayuda="metodos" aria-label="Ayuda sobre métodos de colecta">?</button><button class="atlas-icon-button" type="button" data-menu-trigger aria-label="Acciones de métodos de colecta" aria-expanded="false" aria-haspopup="menu"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg></button></div></div>
                <div class="atlas-menu" role="menu" hidden><button type="button" role="menuitem" data-action="download">Descargar datos CSV</button><button type="button" role="menuitem" data-action="copy">Copiar enlace al panel</button><button type="button" role="menuitem" data-action="fullscreen">Ampliar panel</button></div>
                <div class="atlas-top-species">@forelse($datos['metodos'] as $metodo)<div class="atlas-species-row"><button class="atlas-method-button" type="button" data-metodo="{{ $metodo['metodo'] }}" title="Filtrar por {{ $metodo['metodo'] }}">{{ $metodo['metodo'] }}</button><strong>{{ number_format((int) $metodo['total'], 0, ',', '.') }}</strong></div>@empty<p class="atlas-quality-caption">No hay métodos públicos para esta selección.</p>@endforelse</div>
            </section>
        </div>

        <section class="atlas-panel atlas-list-panel" id="panel-lista" data-panel="lista" aria-labelledby="titulo-lista"><div class="atlas-panel-header atlas-list-header"><div><h2 id="titulo-lista">Lista de especies {{ !empty($filtros['provincia']) ? 'de '.$filtros['provincia'] : 'de la colección' }}</h2><p class="atlas-panel-subtitle">Primeras veinte por número de registros · CSV con la lista completa</p></div><div class="atlas-list-actions"><a class="atlas-small-link" href="{{ route('portal.lista-especies', $filtros) }}">Descargar lista completa</a><div class="atlas-panel-tools"><button class="atlas-icon-button" type="button" data-ayuda="lista" aria-label="Ayuda sobre la lista de especies">?</button><button class="atlas-icon-button" type="button" data-menu-trigger aria-label="Acciones de lista de especies" aria-expanded="false" aria-haspopup="menu"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg></button></div></div></div><div class="atlas-menu" role="menu" hidden><button type="button" role="menuitem" data-action="download">Descargar lista completa CSV</button><button type="button" role="menuitem" data-action="copy">Copiar enlace al panel</button><button type="button" role="menuitem" data-action="fullscreen">Ampliar panel</button></div><div class="atlas-list">@forelse($datos['especies'] as $especie)<div class="atlas-species-row"><a href="{{ route('portal.catalogo', ['nivel' => 'species', 'taxon' => $especie['nombre']]) }}">{{ $especie['nombre'] }}</a><strong>{{ number_format((int) $especie['total'], 0, ',', '.') }}</strong></div>@empty<p class="atlas-quality-caption">No hay especies identificadas para esta selección.</p>@endforelse</div></section>
    </div>

    <dialog class="atlas-help" id="ayuda-estadisticas" aria-labelledby="titulo-ayuda-estadisticas"><div class="atlas-help-head"><h2 id="titulo-ayuda-estadisticas"></h2><button class="atlas-help-close" type="button" aria-label="Cerrar ayuda">×</button></div><div class="atlas-help-content" id="contenido-ayuda-estadisticas"></div></dialog>
    @foreach($guias as $clave => $guia)
        <template id="guia-{{ $clave }}" data-titulo="{{ $guia['titulo'] }}"><img class="atlas-help-photo" src="{{ asset($guia['imagen']) }}" alt="{{ $guia['alt'] }}" loading="lazy"><span class="atlas-photo-caption">Imagen ilustrativa</span><div class="atlas-help-copy"><p>{{ $guia['intro'] }}</p><div class="atlas-help-steps">@foreach($guia['pasos'] as [$subtitulo, $explicacion])<div class="atlas-help-step"><h3>{{ $subtitulo }}</h3><p>{{ $explicacion }}</p></div>@endforeach</div></div></template>
    @endforeach
    <div class="atlas-toast" id="aviso-estadisticas" role="status" aria-live="polite" hidden></div>
</div>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    window.portalEstadisticasDatos = {
        anios: @json($datos['anios']),
        provincias: @json($datos['provincias']),
        metodos: @json($datos['metodos']),
        mapa: @json($datos['mapa']),
        filos: @json($datos['filos']),
        especies: @json($datos['especies']),
        resumen: @json($datos['resumen']),
        listaUrl: @json(route('portal.lista-especies', $filtros)),
        catalogoUrl: @json(route('portal.catalogo'))
    };
</script>
<script src="{{ asset('js/portal-estadisticas.js') }}" defer></script>
@endcomponent
