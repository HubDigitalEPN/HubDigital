<div class="space-y-6">

    <div>
        <flux:heading size="xl" level="1" class="font-display">Configuración de recordatorios</flux:heading>
        <flux:text class="text-text-secondary text-sm mt-1">
            Define cuántos días antes del vencimiento se envían recordatorios automáticos a los investigadores.
        </flux:text>
    </div>

    @if($mensajeExito)
        <flux:callout variant="success" icon="check-circle">{{ $mensajeExito }}</flux:callout>
    @endif

    {{-- Card de configuración global --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-border flex items-center gap-3">
            <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                <flux:icon name="bell" class="size-3.5" />
            </div>
            <flux:heading size="base" level="2" class="font-display flex-1">Cadencia global de recordatorios</flux:heading>
            @if($configuracionId !== '' && !$modoEdicion)
                <flux:button wire:click="habilitarEdicion" variant="ghost" icon="pencil-square" size="sm">
                    Editar
                </flux:button>
            @endif
        </div>
        <div class="p-5">

            {{-- Sin configuración --}}
            @if($configuracionId === '' && !$modoEdicion)
                <flux:callout variant="info" icon="information-circle">
                    No hay una configuración global definida. Los préstamos usarán la cadencia por defecto del sistema (30, 15, 7 y 1 día antes del vencimiento).
                </flux:callout>
                <div class="mt-4">
                    <flux:button wire:click="habilitarEdicion" variant="primary" icon="plus">
                        Definir configuración
                    </flux:button>
                </div>
            @endif

            {{-- Vista de configuración existente --}}
            @if($configuracionId !== '' && !$modoEdicion)
                <div>
                    <p class="text-xs text-text-secondary mb-3">Días antes del vencimiento configurados</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach(collect($diasAntes)->sortDesc()->values() as $dia)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-navy/10 text-blue-navy px-3 py-1.5 text-sm font-medium">
                                <flux:icon name="bell" class="size-3.5" />
                                {{ $dia }} {{ $dia === 1 ? 'día' : 'días' }} antes
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Formulario de edición --}}
            @if($modoEdicion)
                <div class="space-y-5">

                    <div>
                        <p class="text-xs text-text-secondary mb-2">Cadencia sugerida — selecciona para activar o desactivar</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach([30, 15, 7, 3, 1] as $preset)
                                @php $activo = in_array($preset, array_map('intval', $diasAntes), true); @endphp
                                <button type="button" wire:click="toggleDia({{ $preset }})"
                                    @class([
                                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium border transition-colors',
                                        'bg-blue-navy text-white border-blue-navy' => $activo,
                                        'bg-surface text-text-secondary border-border hover:border-blue-navy hover:text-blue-navy' => !$activo,
                                    ])>
                                    @if($activo)<flux:icon name="check" class="size-3.5" />@else<flux:icon name="bell" class="size-3.5" />@endif
                                    {{ $preset }} {{ $preset === 1 ? 'día' : 'días' }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    @if(count($diasAntes) > 0)
                        <div>
                            <p class="text-xs text-text-secondary mb-2">Recordatorios configurados</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach(collect($diasAntes)->map(fn($d) => (int)$d)->sortDesc()->values() as $dia)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-navy/10 text-blue-navy px-3 py-1 text-sm font-medium">
                                        <flux:icon name="bell" class="size-3.5" />
                                        {{ $dia }} {{ $dia === 1 ? 'día' : 'días' }} antes
                                        <button type="button" wire:click="quitarDia({{ $dia }})" class="hover:text-error transition-colors ml-0.5">
                                            <flux:icon name="x-mark" class="size-3" />
                                        </button>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div>
                        <p class="text-xs text-text-secondary mb-2">Agregar día personalizado</p>
                        <div class="flex items-center gap-2 max-w-xs">
                            <flux:input
                                wire:model="nuevoDia"
                                wire:keydown.enter.prevent="agregarDia"
                                type="number" min="1" placeholder="Ej: 45"
                                class="flex-1" />
                            <flux:button wire:click="agregarDia" variant="ghost" icon="plus" size="sm">
                                Agregar
                            </flux:button>
                        </div>
                    </div>

                    <flux:error name="diasAntes" />

                    <div class="flex flex-col gap-2 sm:flex-row pt-2">
                        <flux:button wire:click="guardar" wire:loading.attr="disabled" variant="primary">
                            <span wire:loading.remove wire:target="guardar">Guardar configuración</span>
                            <span wire:loading wire:target="guardar">Guardando...</span>
                        </flux:button>
                        <flux:button wire:click="cancelarEdicion" variant="ghost">Cancelar</flux:button>
                    </div>

                </div>
            @endif

        </div>
    </div>

    {{-- Card de patente del laboratorio --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-border flex items-center gap-3">
            <div class="flex h-7 w-7 items-center justify-center rounded-full bg-bio-green text-white shrink-0">
                <flux:icon name="document-check" class="size-3.5" />
            </div>
            <flux:heading size="base" level="2" class="font-display flex-1">Patente del laboratorio (año {{ $anioPatente }})</flux:heading>
        </div>
        <div class="p-5 space-y-4">
            <flux:text class="text-text-secondary text-sm">
                Código de patente vigente ante el MAATE para el año en curso. Consta en cada acta de préstamo que se genere. Sin patente registrada para el año, no se podrá firmar ni descargar el acta.
            </flux:text>

            @if($mensajePatente)
                <flux:callout variant="success" icon="check-circle">{{ $mensajePatente }}</flux:callout>
            @endif

            <div class="max-w-md">
                <flux:input
                    wire:model="patente"
                    label="Patente {{ $anioPatente }}"
                    placeholder="Ej: MAATE-MCMEVS-2023-276" />
                <flux:error name="patente" />
            </div>

            <div class="flex flex-col gap-2 sm:flex-row pt-1">
                <flux:button wire:click="guardarPatente" wire:loading.attr="disabled" variant="primary">
                    <span wire:loading.remove wire:target="guardarPatente">Guardar patente</span>
                    <span wire:loading wire:target="guardarPatente">Guardando...</span>
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Nota informativa --}}
    <flux:callout variant="warning" icon="exclamation-triangle">
        <flux:heading size="sm">Alcance de la configuración global</flux:heading>
        <flux:text class="mt-1 text-sm">
            Esta configuración aplica a todos los préstamos activos. Los recordatorios se generan automáticamente al aprobar una acta o al aprobar una prórroga. Puedes personalizar la cadencia de recordatorios en cada préstamo individual desde la vista de auditoría.
        </flux:text>
    </flux:callout>

</div>
