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
        <div><h2 id="titulo-detalle-celda">Registros de la localidad</h2></div>
        <button type="button" class="atlas-icon-button" autofocus x-on:click="$refs.detalleCelda.close()" aria-label="Cerrar registros">×</button>
    </header>
    <div class="atlas-cell-loading" x-show="cargando" role="status"><span class="atlas-loading-dot" aria-hidden="true"></span>Cargando los registros públicos de esta ubicación…</div>
    <div class="atlas-cell-error" x-show="error" x-cloak role="alert"><p>No se pudo cargar esta ubicación. La selección se conserva.</p><button type="button" x-on:click="error = false; cargando = true; $wire.abrirCelda(punto.lat, punto.lon).then(() => { cargando = false }).catch(() => { cargando = false; error = true })">Reintentar esta ubicación</button></div>
    @if($celdaMapa !== null)
        @php($detalle = $this->detalleCelda)
        <div class="atlas-cell-body" x-show="!cargando && !error">
            <p class="atlas-cell-status" wire:loading wire:target="paginarCelda,ajustarRegistrosPorPagina" role="status">Actualizando registros…</p>
            <div class="atlas-cell-content portal-records-paged">
                <x-grid-frame label="Registros de la localidad" :page-size="$this->registrosPorPaginaCelda" page-size-action="ajustarRegistrosCelda" :allow-download="\App\Support\DescargasCatalogo::permitidas()">
                    <x-catalogopublico::tabla-registros-mapa :registros="$detalle['registros']" :imagenes="$detalle['imagenes']" :total="$detalle['total']" :seleccion="null" :mostrar-titulo="false" />
                    <x-slot:pagination>
                        @if($detalle['ultima'] > 1 && $vistaCelda === 'registros')<x-grid-pages :page="$detalle['pagina']" :last="$detalle['ultima']" :total="$detalle['total']" :per-page="$this->registrosPorPaginaCelda" action="paginarCelda" label="Registros de la localidad" />@endif
                    </x-slot:pagination>
                </x-grid-frame>
            </div>
        </div>
    @endif
</dialog>
