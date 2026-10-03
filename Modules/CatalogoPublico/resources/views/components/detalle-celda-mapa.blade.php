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
        <div><h2 id="titulo-detalle-celda">Registros de la ubicación</h2><p x-show="punto" x-text="punto ? Number(punto.total).toLocaleString('es-EC') + ' registros · latitud ' + punto.lat + ' · longitud ' + punto.lon : ''"></p></div>
        <button type="button" class="atlas-icon-button" autofocus x-on:click="$refs.detalleCelda.close()" aria-label="Cerrar registros">×</button>
    </header>
    <div class="atlas-cell-loading" x-show="cargando" role="status"><span class="atlas-loading-dot" aria-hidden="true"></span>Cargando árbol taxonómico y registros públicos…</div>
    <p class="atlas-cell-error" x-show="error" x-cloak role="alert">No se pudo cargar esta ubicación. Cierra el diálogo y vuelve a seleccionar el punto para reintentar.</p>
    @if($celdaMapa !== null)
        @php
            $detalle = $this->detalleCelda;
            $seleccionado = $detalle['seleccionado'] ?? null;
            $idsArbol = array_fill_keys(array_map('strval', array_column($detalle['arbol'] ?? [], 'id')), true);
            $seleccionadoFueraArbol = $seleccionado && ! isset($idsArbol[(string) $seleccionado['id']]);
            $ilustracion = $seleccionado ? ($seleccionado['ilustracion'] ?? \Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::paraTaxon($seleccionado['jerarquia'] ?? [])) : null;
        @endphp
        <div x-show="!cargando && !error">
            <div class="atlas-cell-toolbar">
                <button type="button" class="atlas-panel-link" wire:click="volverCelda(0)" wire:loading.attr="disabled">Todos los grupos</button>
                <div class="collection-view-switch" aria-label="Presentación de los registros">
                    <button type="button" wire:click="cambiarVistaCelda('grupos')" wire:loading.attr="disabled" aria-label="Árbol taxonómico e información" title="Árbol taxonómico e información" aria-pressed="{{ $vistaCelda === 'grupos' ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6v5H9zM3 16h6v5H3zM15 16h6v5h-6zM12 8v4M6 16v-4h12v4"/></svg></button>
                    <button type="button" wire:click="cambiarVistaCelda('registros')" wire:loading.attr="disabled" aria-label="Tabla de registros de esta selección, 12 por página" title="Tabla de registros de esta selección, 12 por página" aria-pressed="{{ $vistaCelda === 'registros' ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h18v18H3zM3 9h18M3 15h18M9 3v18M15 3v18"/></svg></button>
                </div>
            </div>
            <p class="atlas-cell-status" wire:loading wire:target="navegarCelda,volverCelda,paginarCelda,paginarArbolCelda,cambiarVistaCelda" role="status">Actualizando selección…</p>
            <div class="atlas-cell-content">
                <x-catalogopublico::avisos-curatoriales :total="$detalle['curatoriales_total'] ?? 0" :notas="$detalle['curatoriales'] ?? []" accion="cambiarVistaCelda('registros')" />
                @if($vistaCelda === 'grupos' && ($detalle['arbol'] ?? []) !== [])
                    <div class="atlas-taxonomic-layout">
                        <section class="atlas-tree-section" aria-labelledby="titulo-arbol-mapa">
                            <h3 id="titulo-arbol-mapa">Árbol taxonómico</h3>
                            <p class="atlas-tree-hint">Vista de conjunto de {{ number_format((int) ($detalle['arbol_registros_total'] ?? $detalle['total']), 0, ',', '.') }} registros de esta ubicación. Selecciona un taxón para ver su información.</p>
                            <p id="nota-dendrograma-mapa" class="atlas-dendrogram-note">Jerarquía taxonómica publicada, sin escala temporal. Miniaturas: fotografías de referencia identificadas.</p>
                            <div class="atlas-tree-scroll" tabindex="0" role="region" aria-label="Gráfico de la jerarquía taxonómica con desplazamiento vertical" aria-describedby="nota-dendrograma-mapa">
                                <x-catalogopublico::dendrograma-mapa :arbol="$detalle['arbol']" :seleccionado="$seleccionado['id'] ?? null" />
                            </div>
                            @if(($detalle['arbol_hojas_total'] ?? 0) > 0)
                                <nav class="atlas-cell-pagination" aria-label="Páginas de taxones terminales del dendrograma">
                                    <button type="button" wire:click="paginarArbolCelda({{ $detalle['arbol_pagina'] - 1 }})" wire:loading.attr="disabled" @disabled($detalle['arbol_pagina'] <= 1)>Anterior</button>
                                    <span>{{ number_format((int) $detalle['arbol_hojas_total'], 0, ',', '.') }} taxones terminales · {{ $detalle['arbol_pagina'] }} / {{ $detalle['arbol_ultima'] }} · hasta 12 por página</span>
                                    <button type="button" wire:click="paginarArbolCelda({{ $detalle['arbol_pagina'] + 1 }})" wire:loading.attr="disabled" @disabled($detalle['arbol_pagina'] >= $detalle['arbol_ultima'])>Siguiente</button>
                                </nav>
                            @endif
                            @if($seleccionadoFueraArbol)<p class="atlas-dendrogram-note" role="status">{{ $seleccionado['nombre'] }} está en otra página del árbol. Su ficha permanece abierta.</p>@endif
                            @if($detalle['directos'] > 0 && ($detalle['grupos'] ?? []) !== [])<button type="button" class="atlas-panel-link" wire:click="cambiarVistaCelda('registros')">Incluye {{ $detalle['directos'] }} registros sin agrupación adicional pública. Ver registros.</button>@endif
                        </section>
                        <aside class="atlas-taxon-information" aria-labelledby="titulo-info-taxon">
                            <h3 id="titulo-info-taxon">{{ $seleccionado['nombre'] ?? 'Información del taxón' }}</h3>
                            @if($seleccionado)
                                <p class="atlas-tree-hint">{{ \Modules\CatalogoPublico\Application\Services\DendrogramaTaxonomico::etiquetaRango($seleccionado['rango']) }} · {{ number_format((int) $seleccionado['total'], 0, ',', '.') }} registros públicos en esta ubicación</p>
                                @if(($detalle['informacion']['autor'] ?? '') !== '' || ($detalle['informacion']['anio_descripcion'] ?? null) !== null)
                                    <dl class="atlas-taxon-authorship">
                                        @if(($detalle['informacion']['autor'] ?? '') !== '')<div><dt>Autoría taxonómica</dt><dd>{{ $detalle['informacion']['autor'] }}</dd></div>@endif
                                        @if(($detalle['informacion']['anio_descripcion'] ?? null) !== null)<div><dt>Año de descripción</dt><dd>{{ $detalle['informacion']['anio_descripcion'] }}</dd></div>@endif
                                    </dl>
                                @endif
                                @if(in_array($seleccionado['rango'], ['especie', 'species'], true))
                                    <x-catalogopublico::representacion-especie :nombre="$seleccionado['nombre']" :jerarquia="$seleccionado['jerarquia'] ?? []" :ilustracion="$ilustracion" contexto="ficha-mapa" />
                                @else
                                    <x-catalogopublico::fotografia-mosaico :taxon="$ilustracion['taxon_consulta'] ?? ($seleccionado['jerarquia'] ?? [])" :fotos="\Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::mosaicoParaTaxon($seleccionado['jerarquia'] ?? [])" contexto="ficha-mapa" />
                                @endif
                                @if($detalle['registros'] !== [])
                                    <div x-data="{ registroActivo: 0 }" wire:key="informacion-taxon-{{ $seleccionado['id'] }}-{{ $detalle['pagina'] }}">
                                        <label class="atlas-record-selector">Ejemplar de la selección<select x-model.number="registroActivo">
                                            @foreach($detalle['registros'] as $registro)<option value="{{ $loop->index }}">{{ $registro->occurrence_id ?: 'Código reservado' }} · {{ $loop->iteration }}</option>@endforeach
                                        </select></label>
                                        @foreach($detalle['registros'] as $registro)
                                            <div x-show="registroActivo === {{ $loop->index }}" @if(!$loop->first) x-cloak @endif>
                                                @include('catalogopublico::components.registro-mapa', ['fotos' => $detalle['imagenes'][$registro->occurrence_id] ?? []])
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="atlas-tree-hint">Abre una especie para consultar las fechas, localidades, métodos de colecta y demás campos públicos de sus ejemplares.</p>
                                @endif
                            @else
                                <p class="atlas-tree-hint">El árbol reúne {{ number_format((int) $detalle['total'], 0, ',', '.') }} registros con esta latitud y longitud. Selecciona un taxón para consultar sus datos y su representación.</p>
                            @endif
                        </aside>
                    </div>
                @else
                    <x-catalogopublico::tabla-registros-mapa :registros="$detalle['registros']" :imagenes="$detalle['imagenes']" :total="$detalle['total']" :seleccion="$seleccionado['nombre'] ?? null" />
                @endif
                @if($vistaCelda === 'registros' || ($detalle['grupos'] ?? []) === [])
                    <nav class="atlas-cell-pagination" aria-label="Páginas de ejemplares de la ubicación">
                        <button type="button" wire:click="paginarCelda({{ $detalle['pagina'] - 1 }})" wire:loading.attr="disabled" @disabled($detalle['pagina'] <= 1)>Anterior</button>
                        <span>Página {{ $detalle['pagina'] }} de {{ $detalle['ultima'] }} · 12 registros por página</span>
                        <button type="button" wire:click="paginarCelda({{ $detalle['pagina'] + 1 }})" wire:loading.attr="disabled" @disabled($detalle['pagina'] >= $detalle['ultima'])>Siguiente</button>
                    </nav>
                @endif
            </div>
        </div>
    @endif
</dialog>
