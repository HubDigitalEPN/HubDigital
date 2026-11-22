<div class="hub-transactional-ui hub-depositos-ui hub-workspace p-4 sm:p-6 space-y-5" data-contenido-recepcion>
    <header><h1 class="text-2xl font-semibold">Contenido recibido · {{ $solicitud->numero }}</h1><p>Registra cada lote o grupo de especímenes que comprobaste físicamente.</p></header>
    <a class="text-science-blue underline" href="{{ route('prestamos.curador.deposito.revisar', $solicitud->id) }}" wire:navigate>Consultar solicitud y matriz declarada</a>
    @if($solicitud->contenido_validado_en)
        <flux:callout variant="success" heading="Recibida y validada">Contenido cerrado. Se notificó al Administrador-Curador para continuar con el acta final.</flux:callout>
    @else
        <form wire:submit="guardarRegistro" class="rounded-lg border border-border p-4 space-y-4">
            <h2 class="text-lg font-semibold">{{ $registroId ? 'Editar registro físico' : 'Registrar material comprobado' }}</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <flux:input wire:model="identificador" label="Identificador del lote / recipiente" required maxlength="255" />
                <flux:input wire:model="cantidad" type="number" label="Individuos comprobados" min="1" required />
                <flux:input wire:model="scientificName" label="Nombre científico / identificación" required maxlength="255" />
                <flux:input wire:model="recordNumber" label="recordNumber · código de campo" maxlength="255" />
                <flux:input wire:model="preparations" label="preparations · preparación" maxlength="255" />
            </div>
            <flux:textarea wire:model="observaciones" label="Observaciones / diferencias con la declaración" maxlength="3000" />
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Guardar registro validado</flux:button>
        </form>
    @endif
    <x-grid-frame label="Contenido físico comprobado" :client="true"><table class="w-full text-sm"><caption class="text-left font-semibold py-2">{{ $filas->count() }} registros · {{ $filas->sum('cantidad') }} individuos comprobados</caption><thead><tr><th class="p-2 text-left">Identificador</th><th class="p-2 text-left">Contenido</th><th class="p-2">Cantidad</th><th class="p-2">Acciones</th></tr></thead><tbody>
    @foreach($filas as $fila)
        <tr wire:key="contenido-{{ $fila->id }}" class="border-t border-border"><td class="p-2">{{ $fila->identificador }}</td><td class="p-2">{{ json_decode($fila->datos_dwc, true)['scientificName'] }}<p>{{ $fila->observaciones }}</p></td><td class="p-2 text-center">{{ $fila->cantidad }}</td><td class="p-2">@unless($solicitud->contenido_validado_en)<flux:button size="sm" wire:click="editarRegistro('{{ $fila->id }}')">Editar</flux:button><flux:button size="sm" wire:click="eliminarRegistro('{{ $fila->id }}')" wire:confirm="¿Eliminar este registro antes de cerrar el contenido?">Eliminar</flux:button>@endunless</td></tr>
    @endforeach
    </tbody></table></x-grid-frame>
    @unless($solicitud->contenido_validado_en)
        <form wire:submit="finalizar" class="rounded-lg border border-border p-4 space-y-3">
            <h2 class="text-lg font-semibold">Finalizar validación técnica</h2>
            @foreach($listaCriterios as $indice => $criterio)<flux:checkbox wire:model="criterios.{{ $indice }}" :label="$criterio->value" />@endforeach
            <flux:error name="criterios" />
            <flux:checkbox wire:model="contenidoCompleto" label="He registrado y validado todo el contenido físico recibido, incluidas las diferencias observadas." />
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Marcar recibida y validada</flux:button>
        </form>
    @endunless
</div>
