@props(['columnas'])
<div x-data="{ busqueda: '' }" class="min-w-0">
    <flux:button icon="question-mark-circle" x-on:click="$refs.guia.showModal()" aria-haspopup="dialog"
        aria-controls="guia-columnas-catalogo" class="max-w-full whitespace-normal! text-left">
        Ayuda de las columnas de la matriz
    </flux:button>
    <dialog x-ref="guia" id="guia-columnas-catalogo" aria-labelledby="guia-columnas-catalogo-titulo" wire:ignore.self
        x-on:click="if ($event.target === $refs.guia) $refs.guia.close()"
        class="fixed inset-0 m-auto max-h-[80vh] w-[min(56rem,calc(100vw-2rem))] overflow-auto rounded-lg border border-border bg-surface p-5 text-text-primary shadow-xl backdrop:bg-blue-navy/30">
        <div class="flex items-start justify-between gap-3">
            <h2 id="guia-columnas-catalogo-titulo" class="text-lg font-semibold">Guía de la plantilla vigente</h2>
            <button type="button" x-on:click="$refs.guia.close()" class="hub-ig-button" aria-label="Cerrar guía de columnas">Cerrar</button>
        </div>
        <p class="mt-3 text-sm">Una fila representa un registro. Conserva los encabezados de la plantilla; los campos obligatorios para el catálogo se indican abajo. Las obligaciones de un depósito son independientes.</p>
        <label for="buscar-guia-catalogo" class="mt-4 block text-sm font-medium">Buscar un campo o su significado</label>
        <input id="buscar-guia-catalogo" type="search" x-model="busqueda" autofocus class="mt-1 w-full rounded border border-border bg-bg-main p-2" placeholder="Por ejemplo: coordenadas, fecha, occurrenceID" />
        <dl class="mt-4 space-y-3" data-catalogo-guia-columnas>
            @foreach($columnas as $columna)
                <div x-show="$el.dataset.busqueda.toLocaleLowerCase().includes(busqueda.toLocaleLowerCase())" data-busqueda="{{ $columna['cabecera'].' '.$columna['campo'].' '.$columna['descripcion'] }}" class="rounded border border-border p-3">
                    <dt class="font-semibold">{{ $columna['cabecera'] }} <span class="text-sm font-normal text-text-secondary">({{ $columna['campo'] }})</span></dt>
                    <dd class="mt-1 text-sm leading-relaxed">{{ $columna['descripcion'] }}</dd>
                    <dd class="mt-2 text-xs text-text-secondary">Catálogo: {{ ucfirst($columna['requisito']) }}. Formato de plantilla: {{ $columna['formato'] }}.</dd>
                </div>
            @endforeach
        </dl>
    </dialog>
</div>
