<div class="hub-workspace p-4 sm:p-6 space-y-4" data-captura-dwc>
    <h1 class="text-2xl font-semibold">Captura Darwin Core</h1>
    <p class="text-sm text-text-secondary">Completa los datos de los especímenes. occurrenceID y los campos de uso interno están reservados al equipo responsable.</p>
    <div class="flex flex-wrap gap-3 items-end"><flux:input wire:model.live.debounce.400ms="busqueda" label="Buscar espécimen" /><flux:button wire:click="nuevo" variant="primary">Nuevo registro</flux:button></div>
    <div class="grid gap-5 lg:grid-cols-[18rem_1fr]">
        <nav aria-label="Registros para captura" class="space-y-1 max-h-96 overflow-y-auto">@foreach($filas as $fila)<button class="block w-full text-left p-2 rounded border border-border" wire:click="seleccionar('{{ $fila->id }}')" wire:key="captura-{{ $fila->id }}">{{ $fila->taxon_verbatim ?: $fila->codigo_catalogo }}</button>@endforeach</nav>
        <div>
            @if($mensaje)<p role="status" class="mb-3 text-bio-green">{{ $mensaje }}</p>@endif
            @if($datos !== [])
                <form wire:submit="guardar" class="space-y-4"><h2 class="font-semibold">{{ $id ? 'Editar datos del registro' : 'Nuevo registro' }}</h2><div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($campos as $clave => $campo)<flux:input wire:model="datos.{{ $clave }}" :label="$campo['etiqueta']" :type="$clave === 'fechaColecta' ? 'date' : 'text'" />@endforeach
                </div><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Guardar datos</flux:button></form>
            @else<p>Selecciona un registro o crea uno nuevo.</p>@endif
        </div>
    </div>
</div>
