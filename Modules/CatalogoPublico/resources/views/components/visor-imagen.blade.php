<dialog class="collection-image-dialog" x-data="portalVisorImagen" wire:ignore.self aria-labelledby="titulo-visor-imagen"
    x-on:lightbox-open.window="abrir($event.detail)"
    x-on:keydown.escape.stop.prevent="cerrar()" x-on:cancel.stop.prevent="cerrar()"
    x-on:close="restaurarFoco()" x-on:click="if ($event.target === $el) cerrar()">
    <header>
        <h2 id="titulo-visor-imagen">Fotografía de la colección</h2>
        <div>
            @if(\App\Support\DescargasCatalogo::permitidas())<a :href="url" :download="filename" aria-label="Descargar fotografía" title="Descargar fotografía"><flux:icon name="arrow-down-tray" class="size-4" /><span>Descargar</span></a>@endif
            <button type="button" x-ref="cerrar" autofocus x-on:click="cerrar()" aria-label="Cerrar fotografía" title="Cerrar fotografía (Esc)"><flux:icon name="x-mark" class="size-5" /></button>
        </div>
    </header>
    <p class="collection-photo-view" x-show="vista" x-text="vista"></p>
    <img :src="url" :alt="alt" decoding="async" />
    <x-catalogopublico::atribuciones-imagen :dinamico="true" />
</dialog>
