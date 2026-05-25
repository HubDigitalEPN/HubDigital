<div class="p-6 space-y-6 max-w-3xl mx-auto">

    <div>
        <flux:heading size="xl" level="1" class="font-display">Configuración de recordatorios de devolución</flux:heading>
        <flux:text class="text-text-secondary mt-1">Define cuántos días antes del vencimiento se envían recordatorios automáticos a los investigadores.</flux:text>
    </div>

    {{-- Mensaje de éxito --}}
    @if($mensajeExito)
        <flux:callout variant="success" icon="check-circle">
            {{ $mensajeExito }}
        </flux:callout>
    @endif

    {{-- Card de configuración global --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4 max-w-2xl">

        <div class="flex items-center justify-between">
            <flux:heading size="lg" level="2" class="font-display">Cadencia global de recordatorios</flux:heading>
            @if($configuracionId !== '' && !$modoEdicion)
                <flux:button wire:click="habilitarEdicion" variant="ghost" icon="pencil-square" size="sm">
                    Editar
                </flux:button>
            @endif
        </div>

        <flux:separator />

        {{-- Estado: sin configuración definida --}}
        @if($configuracionId === '' && !$modoEdicion)
            <flux:callout variant="info" icon="information-circle">
                No hay una configuración global de recordatorios definida. Los préstamos usarán la cadencia por defecto del sistema (30, 15, 7 y 1 día antes del vencimiento).
            </flux:callout>
            <div class="pt-2">
                <flux:button wire:click="habilitarEdicion" variant="primary" icon="plus">
                    Definir configuración
                </flux:button>
            </div>
        @endif

        {{-- Estado: configuración existente en modo visualización --}}
        @if($configuracionId !== '' && !$modoEdicion)
            <div>
                <flux:text class="text-xs text-text-secondary mb-2">Días antes del vencimiento</flux:text>
                <div class="flex flex-wrap gap-2">
                    @foreach(collect($diasAntes)->sortDesc()->values() as $dia)
                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-navy/10 text-blue-navy px-3 py-1 text-sm font-medium">
                            <flux:icon name="bell" class="size-3.5" />
                            {{ $dia }} {{ $dia === 1 ? 'día' : 'días' }} antes
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Estado: formulario de edición / definición --}}
        @if($modoEdicion)
            <div class="space-y-5">

                {{-- Presets rápidos --}}
                <div>
                    <flux:text class="text-xs text-text-secondary mb-2">Cadencia sugerida</flux:text>
                    <div class="flex flex-wrap gap-2">
                        @foreach([30, 15, 7, 3, 1] as $preset)
                            @php $activo = in_array($preset, array_map('intval', $diasAntes), true); @endphp
                            <button
                                type="button"
                                wire:click="toggleDia({{ $preset }})"
                                @class([
                                    'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium border transition-colors',
                                    'bg-blue-navy text-white border-blue-navy' => $activo,
                                    'bg-surface text-text-secondary border-border hover:border-blue-navy hover:text-blue-navy' => !$activo,
                                ])
                            >
                                @if($activo)
                                    <flux:icon name="check" class="size-3.5" />
                                @else
                                    <flux:icon name="bell" class="size-3.5" />
                                @endif
                                {{ $preset }} {{ $preset === 1 ? 'día' : 'días' }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Días seleccionados --}}
                @if(count($diasAntes) > 0)
                    <div>
                        <flux:text class="text-xs text-text-secondary mb-2">Recordatorios configurados</flux:text>
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

                {{-- Agregar día personalizado --}}
                <div>
                    <flux:text class="text-xs text-text-secondary mb-2">Agregar día personalizado</flux:text>
                    <div class="flex items-center gap-2 max-w-xs">
                        <flux:input
                            wire:model="nuevoDia"
                            wire:keydown.enter.prevent="agregarDia"
                            type="number"
                            min="1"
                            placeholder="Ej: 45"
                            class="flex-1" />
                        <flux:button wire:click="agregarDia" variant="ghost" icon="plus" size="sm">
                            Agregar
                        </flux:button>
                    </div>
                </div>

                <flux:error name="diasAntes" />

                {{-- Botones --}}
                <div class="flex flex-col gap-2 sm:flex-row pt-2">
                    <flux:button
                        wire:click="guardar"
                        wire:loading.attr="disabled"
                        variant="primary"
                    >
                        <span wire:loading.remove wire:target="guardar">Guardar configuración</span>
                        <span wire:loading wire:target="guardar">Guardando...</span>
                    </flux:button>
                    <flux:button wire:click="cancelarEdicion" variant="ghost">
                        Cancelar
                    </flux:button>
                </div>

            </div>
        @endif

    </div>

    {{-- Nota informativa --}}
    <div class="max-w-2xl">
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:heading>Alcance de la configuración global</flux:heading>
            <flux:text>Esta configuración aplica a todos los préstamos activos. Los recordatorios se generan automáticamente al aprobar una acta o al aprobar una prórroga. Puedes personalizar la cadencia de recordatorios en cada préstamo individual desde la vista de auditoría.</flux:text>
        </flux:callout>
    </div>

</div>
