<dialog id="registros-taxon-explorador" class="taxonomy-explorer taxonomy-records" wire:ignore.self
    aria-labelledby="titulo-registros-taxon" closedby="closerequest"
    x-ref="dialogo" x-data="{ cerrar() { this.$refs.dialogo.close(); } }"
    x-on:registros-taxon-abiertos.window="if (!$refs.dialogo.open) $refs.dialogo.showModal(); $nextTick(() => $refs.cerrar.focus())"
    x-on:pagina-registros-taxon.window="$nextTick(() => { $refs.contenido.scrollTop = 0; $refs.encabezado?.focus(); })"
    x-on:cancel.stop.prevent="cerrar()" x-on:keydown.escape.stop.prevent="cerrar()"
    x-on:close="$dispatch('registros-taxon-cerrados')">
    <header class="taxonomy-explorer-header">
        <div>
            <h2 id="titulo-registros-taxon" x-ref="encabezado" tabindex="-1">Registros de <em>{{ $nombreTaxon }}</em></h2>
            <p>{{ number_format($paginacion['total'], 0, ',', '.') }} registros públicos asociados</p>
        </div>
        <button type="button" class="taxonomy-explorer-close" x-ref="cerrar" x-on:click="cerrar()" aria-label="Cerrar registros del taxón">×</button>
    </header>
    <div class="taxonomy-explorer-body" x-ref="contenido" wire:loading.attr="aria-busy">
        @if($registroFichaId !== null)
            <button type="button" wire:click="cerrarFichaRegistro" class="taxonomy-records-back">← Volver a los registros</button>
            @if($this->fichaRegistro)
                @include('catalogopublico::components.registro-mapa', ['registro' => $this->fichaRegistro['registro'], 'fotos' => $this->fichaRegistro['fotos'], 'familiaPublica' => $this->fichaRegistro['familia_publica'], 'permitirFicha' => false])
            @else
                <p role="status">Este registro ya no está disponible para consulta pública.</p>
            @endif
        @elseif($taxonId !== '')
            <x-catalogopublico::tabla-registros-mapa :registros="$registros" :imagenes="$imagenes" :total="$paginacion['total']" contexto="catalogo" :mostrar-titulo="false" />
            @if($paginacion['ultima'] > 1)
                <nav class="collection-card-pagination" aria-label="Páginas de registros del taxón">
                    <button type="button" wire:click="cambiarPagina({{ $paginacion['pagina'] - 1 }})" wire:loading.attr="disabled" @disabled($paginacion['pagina'] <= 1)>Anterior</button>
                    <span>Página {{ $paginacion['pagina'] }} de {{ $paginacion['ultima'] }} · 24 por página</span>
                    <button type="button" wire:click="cambiarPagina({{ $paginacion['pagina'] + 1 }})" wire:loading.attr="disabled" @disabled($paginacion['pagina'] >= $paginacion['ultima'])>Siguiente</button>
                </nav>
            @endif
        @endif
        <p wire:loading.delay role="status">Consultando registros…</p>
    </div>
</dialog>
