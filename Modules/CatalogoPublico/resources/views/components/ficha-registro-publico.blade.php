<dialog class="portal-record-dialog" wire:ignore.self x-data x-ref="fichaPublica"
    x-on:abrir-ficha-registro.window="$refs.fichaPublica.showModal()"
    x-on:click="if ($event.target === $el) $el.close()"
    x-on:close="$wire.cerrarFichaRegistro()" aria-labelledby="titulo-ficha-publica">
    <header class="portal-record-dialog-header">
        <h2 id="titulo-ficha-publica">Ficha del registro</h2>
        <button type="button" autofocus x-on:click="$refs.fichaPublica.close()" aria-label="Cerrar ficha del registro">×</button>
    </header>
    @php($ficha = $this->fichaRegistro)
    @if($ficha)
        @include('catalogopublico::components.registro-mapa', ['registro' => $ficha['registro'], 'fotos' => array_slice($ficha['fotos'], 0, 1), 'permitirFicha' => false])
    @else
        <p role="status">Este registro ya no está disponible en la selección pública.</p>
    @endif
</dialog>
