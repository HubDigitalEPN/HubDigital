<div class="space-y-3" wire:loading.class="opacity-40 pointer-events-none" wire:target="archivoMatriz,guardarMatrizNativa">

    {{-- Banner: rechazo por campo DwC faltante / obligatorio vacío --}}
    @if($matrizCargada && !empty($errorMatriz))
        <flux:callout variant="danger" icon="x-circle">
            <flux:heading>Carga rechazada</flux:heading>
            @if(!empty($camposObligatoriosVacios))
                <flux:text class="text-sm">
                    Algunos campos obligatorios están vacíos. Complétalos en los registros de HubDigital y vuelve a validar:
                </flux:text>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach($camposObligatoriosVacios as $vacio)
                        <span class="inline-flex items-center gap-1.5 rounded border border-error/40 bg-error/5 px-2 py-1 text-xs text-error">
                            <flux:icon name="exclamation-triangle" variant="outline" class="size-3 shrink-0" />
                            <span class="font-serif italic">{{ $vacio['fila'] }}</span>
                            <span class="text-error/70">·</span>
                            <span class="font-medium">{{ $vacio['campo'] }}</span>
                        </span>
                    @endforeach
                </div>
            @else
                <flux:text class="text-sm">
                    {{ $errorMatriz }}. Corrige los registros indicados y vuelve a validarlos.
                </flux:text>
            @endif
        </flux:callout>
    @endif

    {{-- Formulario nativo: vía principal para consultores y depositantes --}}
    <section class="space-y-3 rounded border border-border bg-white p-3 sm:p-4" aria-labelledby="registro-biologico-nativo">
        <div class="flex items-center gap-2">
            <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-science-blue/10">
                <flux:icon name="bug-ant" class="size-4 text-science-blue" />
            </div>
            <flux:heading id="registro-biologico-nativo" size="sm" level="3">Especímenes y lotes</flux:heading>
        </div>

        @if(!empty($muestrasDetectadas))
            <div class="flex flex-wrap items-center gap-2 rounded-lg border border-science-blue/25 bg-white px-2.5 py-2">
                <p class="mr-1 text-xs font-semibold text-blue-navy">Códigos de la guía</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($muestrasDetectadas as $muestra)
                        <button type="button" wire:click="usarMuestraDetectada(@js($muestra['recordNumber']))" class="rounded-full border border-science-blue/30 bg-science-blue/5 px-3 py-1 font-mono text-xs font-semibold text-science-blue hover:bg-science-blue/10">
                            {{ $muestra['recordNumber'] }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <fieldset class="hub-detail-section">
        <legend>Taxonomía</legend>
        <div class="relative">
            <flux:field>
                <flux:label>Taxón científico</flux:label>
                <flux:input wire:model.live.debounce.400ms="busquedaTaxon" placeholder="Escribe al menos 3 caracteres, por ejemplo Atta…" autocomplete="off" required />
                <flux:error name="registroNativo.scientificName" />
            </flux:field>
            @if(!empty($opcionesTaxones))
                <div class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-border bg-white p-1 shadow-xl">
                    @foreach($opcionesTaxones as $opcion)
                        <button type="button" wire:click="seleccionarTaxon(@js($opcion['nombre']))" class="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left hover:bg-bg-main">
                            <span><em class="font-serif text-sm text-text-primary">{{ $opcion['nombre'] }}</em><span class="ml-2 text-xs text-text-secondary">{{ $opcion['rango'] }}</span></span>
                            <span class="rounded-full bg-science-blue/10 px-2 py-0.5 text-[10px] font-semibold text-science-blue">{{ $opcion['fuente'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        </fieldset>
        @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.detalle-formulario')

    </section>
</div>
