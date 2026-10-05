<dialog class="atlas-cell-dialog" wire:ignore.self x-data="{ anterior: null, cargando: false, error: false, punto: null }" x-ref="detalleCelda"
    aria-labelledby="titulo-detalle-celda" :aria-busy="cargando.toString()"
    x-on:iniciar-detalle-celda.window="anterior = $event.detail.invocador || document.activeElement; punto = $event.detail; error = false; cargando = true; if (!$el.open) $el.showModal()"
    x-on:finalizar-detalle-celda.window="cargando = false"
    x-on:abrir-detalle-celda.window="cargando = false"
    x-on:error-detalle-celda.window="cargando = false; error = true"
    x-on:keydown.escape.stop.prevent="$el.close()" x-on:cancel.stop.prevent="$el.close()"
    x-on:close="cargando = false; $wire.cerrarCelda(); anterior?.focus({preventScroll: true})"
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
        <div x-show="!cargando && !error">
            <div class="atlas-cell-toolbar">
                <h3 id="titulo-arbol-mapa">Árbol taxonómico</h3>
                <div class="collection-view-switch" aria-label="Presentación de los registros">
                    <button type="button" wire:click="cambiarVistaCelda('grupos')" wire:loading.attr="disabled" aria-label="Árbol taxonómico e información" title="Árbol taxonómico e información" aria-pressed="{{ $vistaCelda === 'grupos' ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6v5H9zM3 16h6v5H3zM15 16h6v5h-6zM12 8v4M6 16v-4h12v4"/></svg></button>
                    <button type="button" wire:click="cambiarVistaCelda('registros')" wire:loading.attr="disabled" aria-label="Todos los registros de la ubicación, 6 por página" title="Todos los registros de la ubicación, 6 por página" aria-pressed="{{ $vistaCelda === 'registros' ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h18v18H3zM3 9h18M3 15h18M9 3v18M15 3v18"/></svg></button>
                </div>
            </div>
            <p class="atlas-cell-status" wire:loading wire:target="navegarCelda,volverCelda,paginarCelda,cambiarVistaCelda" role="status">Actualizando selección…</p>
            <div class="atlas-cell-content">
                <x-catalogopublico::avisos-curatoriales :total="$detalle['curatoriales_total'] ?? 0" :notas="$detalle['curatoriales'] ?? []" accion="cambiarVistaCelda('registros')" />
                @if($vistaCelda === 'grupos' && ($detalle['arbol'] ?? []) !== [])
                    <div class="atlas-taxonomic-layout">
                        <section class="atlas-tree-section" aria-labelledby="titulo-arbol-mapa">
                            <div class="atlas-tree-scroll" tabindex="0" role="region" aria-label="Árbol taxonómico adaptable con registros como hojas">
                                <x-catalogopublico::dendrograma-mapa :arbol="$detalle['arbol']" :seleccionado="$detalle['registro_seleccionado']['id'] ?? $seleccionado['id'] ?? null" />
                            </div>
                        </section>
                        <aside class="atlas-taxon-information" aria-labelledby="titulo-info-taxon">
                            <h3 id="titulo-info-taxon">{{ $seleccionado['nombre'] ?? 'Información del taxón' }}</h3>
                            @if($seleccionado)
                                <x-catalogopublico::referencia-taxonomica :nombre="$seleccionado['nombre']" :familia-visible="isset($seleccionado['jerarquia']['family'])" :familia-original="$seleccionado['jerarquia']['family'] ?? null" />
                                @if(!$esEspecie)
                                    <x-catalogopublico::fotografia-mosaico :taxon="$ilustracion['taxon_consulta'] ?? ($seleccionado['jerarquia'] ?? [])" :fotos="\Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::mosaicoParaTaxon($seleccionado['jerarquia'] ?? [])" :limite="1" :retrato="true" :compacto="true" contexto="ficha-mapa" />
                                @endif
                            @endif
                            @if(!$seleccionado && !$detalle['mostrarRegistros'])<p>Selecciona un taxón o un registro del árbol para ver su información.</p>@endif
                                @if($detalle['mostrarRegistros'] && $detalle['registros'] !== [])
                                    <div x-data="{ registroActivo: 0 }" wire:key="informacion-taxon-{{ $detalle['registro_seleccionado']['id'] ?? $seleccionado['id'] ?? 'publico' }}-{{ $detalle['pagina'] }}">
                                        <label class="atlas-record-selector">Ejemplar de la selección<select x-model.number="registroActivo">
                                            @foreach($detalle['registros'] as $registro)<option value="{{ $loop->index }}">{{ $registro->occurrence_id ?: 'Código reservado' }} · {{ $loop->iteration }}</option>@endforeach
                                        </select></label>
                                        @foreach($detalle['registros'] as $registro)
                                            <div x-show="registroActivo === {{ $loop->index }}" @if(!$loop->first) x-cloak @endif>
                                                <x-catalogopublico::fotografia-ejemplar :url="$detalle['imagenes'][$registro->occurrence_id][0]['url'] ?? null" :nombre="$seleccionado['nombre'] ?? 'Registro público'" />
                                                @include('catalogopublico::components.registro-mapa', ['fotos' => [], 'mostrarIdentificacion' => false])
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                @if($detalle['mostrarRegistros'] && $detalle['registros'] === [])<p role="status">No hay registros públicos disponibles para esta hoja en la selección actual. Revisa los filtros o vuelve al árbol.</p>@endif
                        </aside>
                    </div>
                @else
                    <x-catalogopublico::tabla-registros-mapa :registros="$detalle['registros']" :imagenes="$detalle['imagenes']" :total="$detalle['total']" :seleccion="null" />
                @endif
                @if($vistaCelda === 'registros' && $detalle['mostrarRegistros'] && $detalle['registros'] !== [])
                    <nav class="atlas-cell-pagination" aria-label="Páginas de ejemplares de la ubicación">
                        <button type="button" wire:click="paginarCelda({{ $detalle['pagina'] - 1 }})" wire:loading.attr="disabled" @disabled($detalle['pagina'] <= 1)>Anterior</button>
                        <span>Página {{ $detalle['pagina'] }} de {{ $detalle['ultima'] }} · 6 registros por página</span>
                        <button type="button" wire:click="paginarCelda({{ $detalle['pagina'] + 1 }})" wire:loading.attr="disabled" @disabled($detalle['pagina'] >= $detalle['ultima'])>Siguiente</button>
                    </nav>
                @endif
            </div>
        </div>
    @endif
</dialog>
