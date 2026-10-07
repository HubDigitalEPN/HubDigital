<dialog class="atlas-cell-dialog" wire:key="catalogo-detalle-ubicacion" wire:ignore.self x-data="{ anterior: null, cargando: false, error: false, punto: null }" x-ref="detalleCelda"
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
    <div class="atlas-cell-loading" x-show="cargando" role="status"><span class="atlas-loading-dot" aria-hidden="true"></span>Cargando los registros públicos de esta ubicación…</div>
    <div class="atlas-cell-error" x-show="error" x-cloak role="alert"><p>No se pudo cargar esta ubicación. La selección se conserva.</p><button type="button" x-on:click="error = false; cargando = true; $wire.abrirCelda(punto.lat, punto.lon).then(() => { cargando = false }).catch(() => { cargando = false; error = true })">Reintentar esta ubicación</button></div>
    @if($celdaMapa !== null)
        @php($detalle = $this->detalleCelda)
        <div class="atlas-cell-body" x-show="!cargando && !error">
            <p class="atlas-cell-status" wire:loading wire:target="paginarCelda,ajustarRegistrosPorPagina" role="status">Actualizando registros…</p>
            <div class="atlas-cell-content portal-records-viewport" x-data="portalRegistros({{ $this->registrosPorPaginaCelda }}, true)">
                <x-catalogopublico::avisos-curatoriales :total="$detalle['curatoriales_total'] ?? 0" :notas="$detalle['curatoriales'] ?? []" accion="cambiarVistaCelda('registros')" />
                <x-catalogopublico::tabla-registros-mapa :registros="$detalle['registros']" :imagenes="$detalle['imagenes']" :total="$detalle['total']" :seleccion="null" :mostrar-titulo="false" />
                @if($vistaCelda === 'registros' && $detalle['mostrarRegistros'] && $detalle['registros'] !== [])
                    <nav class="atlas-cell-pagination portal-records-pagination" aria-label="Páginas de ejemplares de la ubicación">
                        <span>Página {{ $detalle['pagina'] }} de {{ $detalle['ultima'] }}</span>
                        <button type="button" wire:click="paginarCelda({{ $detalle['pagina'] - 1 }})" wire:loading.attr="disabled" @disabled($detalle['pagina'] <= 1)>Anterior</button>
                        <button type="button" wire:click="paginarCelda({{ $detalle['pagina'] + 1 }})" wire:loading.attr="disabled" @disabled($detalle['pagina'] >= $detalle['ultima'])>Siguiente</button>
                    </nav>
                @endif
            </div>
        </div>
    @endif
</dialog>
