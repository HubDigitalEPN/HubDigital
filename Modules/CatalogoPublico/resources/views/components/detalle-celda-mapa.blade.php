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
            $ramas = [];
            $idsArbol = array_fill_keys(array_map('strval', array_column($detalle['arbol'] ?? [], 'id')), true);
            foreach ($detalle['arbol'] ?? [] as $nodoArbol) {
                $padreArbol = isset($idsArbol[(string) ($nodoArbol['padre_id'] ?? '')]) ? (string) $nodoArbol['padre_id'] : 'raiz';
                $ramas[$padreArbol][] = $nodoArbol;
            }
            $ilustracion = $seleccionado ? ($seleccionado['ilustracion'] ?? \Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::paraTaxon($seleccionado['jerarquia'] ?? [])) : null;
        @endphp
        <div x-show="!cargando && !error">
            <div class="atlas-cell-toolbar">
                <button type="button" class="atlas-panel-link" wire:click="volverCelda(0)" wire:loading.attr="disabled">Todos los grupos</button>
                <div class="collection-view-switch" aria-label="Presentación de los registros">
                    <button type="button" wire:click="cambiarVistaCelda('grupos')" wire:loading.attr="disabled" aria-label="Árbol taxonómico e información" title="Árbol taxonómico e información" aria-pressed="{{ $vistaCelda === 'grupos' ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6v5H9zM3 16h6v5H3zM15 16h6v5h-6zM12 8v4M6 16v-4h12v4"/></svg></button>
                    <button type="button" wire:click="cambiarVistaCelda('registros')" wire:loading.attr="disabled" aria-label="Todos los registros, 12 por página" title="Todos los registros, 12 por página" aria-pressed="{{ $vistaCelda === 'registros' ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h18v18H3zM3 9h18M3 15h18M9 3v18M15 3v18"/></svg></button>
                </div>
            </div>
            <p class="atlas-cell-status" wire:loading wire:target="navegarCelda,volverCelda,paginarCelda,cambiarVistaCelda" role="status">Actualizando selección…</p>
            <div class="atlas-cell-content">
                @if($vistaCelda === 'grupos' && ($detalle['arbol'] ?? []) !== [])
                    <div class="atlas-taxonomic-layout">
                        <section class="atlas-tree-section" aria-labelledby="titulo-arbol-mapa">
                            <h3 id="titulo-arbol-mapa">Árbol taxonómico</h3><p class="atlas-tree-hint">Selecciona una rama o especie para ver su información.</p>
                            <div class="atlas-tree-scroll" tabindex="0" aria-label="Jerarquía taxonómica con desplazamiento horizontal">
                                <ul class="atlas-taxonomic-tree" aria-label="Taxones de esta ubicación">
                                    @foreach($ramas['raiz'] ?? [] as $raizArbol)
                                        @include('catalogopublico::components.nodo-arbol-mapa', ['nodo' => $raizArbol])
                                    @endforeach
                                </ul>
                            </div>
                            @if(($detalle['grupos'] ?? []) !== [])
                                <nav class="atlas-cell-pagination" aria-label="Páginas de ramas del árbol">
                                    <button type="button" wire:click="paginarCelda({{ $detalle['pagina'] - 1 }})" wire:loading.attr="disabled" @disabled($detalle['pagina'] <= 1)>Anterior</button>
                                    <span>Ramas · {{ $detalle['pagina'] }} / {{ $detalle['ultima'] }} · hasta 12 por página</span>
                                    <button type="button" wire:click="paginarCelda({{ $detalle['pagina'] + 1 }})" wire:loading.attr="disabled" @disabled($detalle['pagina'] >= $detalle['ultima'])>Siguiente</button>
                                </nav>
                            @endif
                            @if($detalle['directos'] > 0 && ($detalle['grupos'] ?? []) !== [])<button type="button" class="atlas-panel-link" wire:click="cambiarVistaCelda('registros')">Incluye {{ $detalle['directos'] }} registros sin agrupación adicional pública. Ver registros.</button>@endif
                        </section>
                        <aside class="atlas-taxon-information" aria-labelledby="titulo-info-taxon">
                            <h3 id="titulo-info-taxon">{{ $seleccionado['nombre'] ?? 'Información del taxón' }}</h3>
                            @if($seleccionado)
                                <p class="atlas-tree-hint">{{ ucfirst($seleccionado['rango']) }} · {{ number_format((int) $seleccionado['total'], 0, ',', '.') }} registros públicos en esta ubicación</p>
                                @if(($detalle['informacion']['autor'] ?? '') !== '' || ($detalle['informacion']['anio_descripcion'] ?? null) !== null)
                                    <dl class="atlas-taxon-authorship">
                                        @if(($detalle['informacion']['autor'] ?? '') !== '')<div><dt>Autoría taxonómica</dt><dd>{{ $detalle['informacion']['autor'] }}</dd></div>@endif
                                        @if(($detalle['informacion']['anio_descripcion'] ?? null) !== null)<div><dt>Año de descripción</dt><dd>{{ $detalle['informacion']['anio_descripcion'] }}</dd></div>@endif
                                    </dl>
                                @endif
                                <figure class="atlas-taxon-illustration"><img src="{{ $ilustracion['url'] }}" alt="{{ $ilustracion['alt'] }}" width="480" height="320" loading="lazy" decoding="async"><figcaption>Ilustración representativa de {{ $ilustracion['grupo'] }}. Los datos siguientes corresponden a ejemplares publicados de la base de datos.</figcaption></figure>
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
                                <p class="atlas-tree-hint">El árbol reúne {{ number_format((int) $detalle['total'], 0, ',', '.') }} registros con esta latitud y longitud. Selecciona un taxón para consultar sus datos y su ilustración.</p>
                            @endif
                        </aside>
                    </div>
                @else
                    <div class="atlas-cell-grid">
                        @forelse($detalle['registros'] as $registro)
                            @include('catalogopublico::components.registro-mapa', ['fotos' => $detalle['imagenes'][$registro->occurrence_id] ?? []])
                        @empty<p>No hay registros públicos para esta selección.</p>@endforelse
                    </div>
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
