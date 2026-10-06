<dialog class="atlas-cell-dialog" wire:key="catalogo-detalle-ubicacion" wire:ignore.self x-data="{ anterior: null, cargando: false, error: false, punto: null, invocadorLov: null }" x-ref="detalleCelda"
    aria-labelledby="titulo-detalle-celda" :aria-busy="cargando.toString()"
    x-on:iniciar-detalle-celda.window="anterior = $event.detail.invocador || document.activeElement; punto = $event.detail; error = false; cargando = true; if (!$el.open) $el.showModal()"
    x-on:finalizar-detalle-celda.window="cargando = false"
    x-on:abrir-detalle-celda.window="cargando = false"
    x-on:error-detalle-celda.window="cargando = false; error = true"
    x-on:keydown.escape.stop.prevent="$el.close()" x-on:cancel.stop.prevent="$el.close()"
    x-on:close.self="cargando = false; $dispatch('restaurar-foco-mapa', { invocador: anterior })"
    x-on:click="if ($event.target === $el) $el.close()">
    <header class="atlas-cell-header">
        <div><h2 id="titulo-detalle-celda">Registros de la ubicación</h2><p x-show="punto" x-text="punto ? Number(punto.total).toLocaleString('es-EC') + (Number(punto.total) === 1 ? ' registro' : ' registros') + ' · latitud ' + punto.lat + ' · longitud ' + punto.lon : ''"></p></div>
        <button type="button" class="atlas-icon-button" autofocus x-on:click="$refs.detalleCelda.close()" aria-label="Cerrar registros">×</button>
    </header>
    <div class="atlas-cell-loading" x-show="cargando" role="status"><span class="atlas-loading-dot" aria-hidden="true"></span>Cargando árbol taxonómico y registros públicos…</div>
    <div class="atlas-cell-error" x-show="error" x-cloak role="alert"><p>No se pudo cargar esta ubicación. La selección se conserva.</p><button type="button" x-on:click="error = false; cargando = true; $wire.abrirCelda(punto.lat, punto.lon).then(() => { cargando = false }).catch(() => { cargando = false; error = true })">Reintentar esta ubicación</button></div>
    @if($celdaMapa !== null)
        @php
            $detalle = $this->detalleCelda;
            $seleccionado = $detalle['seleccionado'] ?? null;
            $esEspecie = $seleccionado && in_array(mb_strtolower($seleccionado['rango']), ['especie', 'species'], true);
            $ilustracion = $seleccionado && !$esEspecie ? ($seleccionado['ilustracion'] ?? \Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::paraTaxon($seleccionado['jerarquia'] ?? [])) : null;
        @endphp
        <div class="atlas-cell-body" x-show="!cargando && !error">
            <div class="atlas-cell-toolbar">
                <h3 id="titulo-arbol-mapa">{{ $vistaCelda === 'registros' ? 'Registros de esta ubicación' : 'Árbol taxonómico' }}</h3>
                <div class="collection-view-switch" aria-label="Presentación de los registros">
                    <button type="button" wire:click="cambiarVistaCelda('grupos')" wire:loading.attr="disabled" aria-label="Árbol taxonómico e información" title="Árbol taxonómico e información" aria-pressed="{{ $vistaCelda === 'grupos' ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6v5H9zM3 16h6v5H3zM15 16h6v5h-6zM12 8v4M6 16v-4h12v4"/></svg></button>
                    <button type="button" wire:click="cambiarVistaCelda('registros')" wire:loading.attr="disabled" aria-label="Todos los registros de la ubicación" title="Todos los registros de la ubicación" aria-pressed="{{ $vistaCelda === 'registros' ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h18v18H3zM3 9h18M3 15h18M9 3v18M15 3v18"/></svg></button>
                </div>
            </div>
            <p class="atlas-cell-status" wire:loading wire:target="navegarCelda,volverCelda,paginarCelda,cambiarVistaCelda" role="status">Actualizando selección…</p>
            <div class="atlas-cell-content {{ $vistaCelda === 'registros' ? 'portal-records-viewport' : '' }}" @if($vistaCelda === 'registros') x-data="portalRegistros({{ $this->registrosPorPaginaCelda }}, true)" @endif>
                <x-catalogopublico::avisos-curatoriales :total="$detalle['curatoriales_total'] ?? 0" :notas="$detalle['curatoriales'] ?? []" accion="cambiarVistaCelda('registros')" />
                @if($vistaCelda === 'grupos' && ($detalle['arbolVisual'] ?? []) !== [])
                    <div class="atlas-taxonomic-layout">
                        <section class="atlas-tree-section" aria-labelledby="titulo-arbol-mapa">
                            @php
                                $idArbolSeleccionado = $detalle['registro_seleccionado']['id'] ?? $seleccionado['id'] ?? null;
                                if ($detalle['arbolResumido']) {
                                    $idsVisibles = array_column($detalle['arbolVisual'], 'id');
                                    $rutaVisible = array_reverse($this->rutaCelda);
                                    $idArbolSeleccionado = array_find($rutaVisible, static fn (array $n): bool => in_array($n['id'], $idsVisibles, true))['id'] ?? null;
                                }
                            @endphp
                            <div class="atlas-tree-scroll" tabindex="0" role="region" aria-label="{{ $detalle['arbolResumido'] ? 'Clasificación taxonómica adaptable hasta familia; los ejemplares se eligen en el buscador' : 'Árbol taxonómico adaptable con registros como hojas' }}">
                                <x-catalogopublico::dendrograma-mapa :arbol="$detalle['arbolVisual']" :seleccionado="$idArbolSeleccionado" />
                            </div>
                        </section>
                        <aside class="atlas-taxon-information" aria-labelledby="titulo-info-taxon">
                            <h3 id="titulo-info-taxon">{{ $seleccionado['nombre'] ?? 'Información del taxón' }}</h3>
                            @if($detalle['arbolResumido'])<button type="button" class="atlas-record-selector atlas-record-lov-trigger" x-on:click="invocadorLov = $event.currentTarget; $refs.registrosLov.showModal(); $nextTick(() => $refs.buscarRegistroLov.focus())">Buscar entre {{ number_format($detalle['totalUbicacion'], 0, ',', '.') }} ejemplares <span aria-hidden="true">⌕</span></button>@endif
                            @if($seleccionado)
                                <x-catalogopublico::referencia-taxonomica :nombre="$seleccionado['nombre']" :familia-visible="isset($seleccionado['jerarquia']['family'])" :familia-original="$seleccionado['jerarquia']['family'] ?? null" />
                                @if(!$esEspecie)
                                    <x-catalogopublico::fotografia-mosaico :taxon="$ilustracion['taxon_consulta'] ?? ($seleccionado['jerarquia'] ?? [])" :fotos="\Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::mosaicoParaTaxon($seleccionado['jerarquia'] ?? [])" :limite="1" :retrato="true" :compacto="true" contexto="ficha-mapa" />
                                @endif
                            @endif
                            @if(!$seleccionado && !$detalle['mostrarRegistros'])<p>Selecciona un taxón o un registro del árbol para ver su información.</p>@endif
                                @if($detalle['mostrarRegistros'] && $detalle['registros'] !== [])
                                    <div wire:key="informacion-taxon-{{ $detalle['registro_seleccionado']['id'] ?? $seleccionado['id'] ?? 'publico' }}-{{ $detalle['pagina'] }}">
                                        @if($detalle['totalUbicacion'] > 1 && !$detalle['arbolResumido'])
                                            <button type="button" class="atlas-record-selector atlas-record-lov-trigger" x-on:click="invocadorLov = $event.currentTarget; $refs.registrosLov.showModal(); $nextTick(() => $refs.buscarRegistroLov.focus())">Buscar ejemplar <span aria-hidden="true">⌕</span></button>
                                        @endif
                                        @foreach($detalle['registros'] as $registro)
                                            @if($detalle['registro_seleccionado'] !== null || (!$detalle['arbolResumido'] && $loop->first))<div>
                                                <x-catalogopublico::fotografia-ejemplar :url="$detalle['imagenes'][$registro->occurrence_id][0]['url'] ?? null" :nombre="$seleccionado['nombre'] ?? 'Registro público'" />
                                                @include('catalogopublico::components.registro-mapa', ['fotos' => [], 'mostrarIdentificacion' => false, 'mostrarReferencia' => !$seleccionado, 'permitirFicha' => false])
                                            </div>@endif
                                        @endforeach
                                        @if($detalle['arbolResumido'] && $detalle['registro_seleccionado'] === null)<p>Busca un ejemplar por su código de catálogo o nombre científico para consultar sus datos.</p>@endif
                                    </div>
                                @endif
                                @if($detalle['mostrarRegistros'] && $detalle['registros'] === [])<p role="status">No hay registros públicos disponibles para esta hoja en la selección actual. Revisa los filtros o vuelve al árbol.</p>@endif
                        </aside>
                    </div>
                @else
                    <x-catalogopublico::tabla-registros-mapa :registros="$detalle['registros']" :imagenes="$detalle['imagenes']" :total="$detalle['total']" :seleccion="null" :mostrar-titulo="false" />
                @endif
                @if($vistaCelda === 'registros' && $detalle['mostrarRegistros'] && $detalle['registros'] !== [])
                    <nav class="atlas-cell-pagination portal-records-pagination" aria-label="Páginas de ejemplares de la ubicación">
                        <span>Página {{ $detalle['pagina'] }} de {{ $detalle['ultima'] }}</span>
                        <button type="button" wire:click="paginarCelda({{ $detalle['pagina'] - 1 }})" wire:loading.attr="disabled" @disabled($detalle['pagina'] <= 1)>Anterior</button>
                        <button type="button" wire:click="paginarCelda({{ $detalle['pagina'] + 1 }})" wire:loading.attr="disabled" @disabled($detalle['pagina'] >= $detalle['ultima'])>Siguiente</button>
                    </nav>
                @endif
            </div>
            <dialog class="atlas-record-lov" wire:ignore.self x-ref="registrosLov" aria-labelledby="titulo-registros-lov"
                x-on:registro-celda-seleccionado.window="if ($el.open) $el.close()" x-on:close="invocadorLov?.focus({preventScroll: true})" x-on:keydown.escape.stop.prevent="$el.close()" x-on:cancel.stop.prevent="$el.close()">
                <header><h3 id="titulo-registros-lov">Buscar ejemplar en esta ubicación</h3><button type="button" x-on:click="$refs.registrosLov.close()" aria-label="Cerrar búsqueda">×</button></header>
                <label><span>Código de catálogo o nombre científico</span><input type="search" x-ref="buscarRegistroLov" wire:model.live.debounce.350ms="busquedaRegistroCelda" maxlength="120" autocomplete="off"></label>
                <div wire:loading.attr="inert" wire:target="busquedaRegistroCelda,seleccionarRegistroCelda">
                    <ul>@forelse($detalle['registrosLov'] as $opcion)<li><button type="button" wire:click="seleccionarRegistroCelda(@js($opcion['id']))"><strong>{{ $opcion['codigo'] ?: 'Código reservado' }}</strong><em>{{ $opcion['nombre'] ?: 'Identificación reservada' }}</em></button></li>@empty<li role="status">No hay coincidencias públicas.</li>@endforelse</ul>
                    @if($detalle['lovHayMas'])<p>Se muestran 30 coincidencias. Escribe parte del código o nombre para acotar la búsqueda.</p>@endif
                </div>
                <p wire:loading wire:target="busquedaRegistroCelda,seleccionarRegistroCelda" role="status">Buscando…</p>
            </dialog>
        </div>
    @endif
</dialog>
