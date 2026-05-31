<div class="p-6 space-y-6">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.mis-prestamos') }}">
            Mis préstamos
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.prestamo.detalle', $prestamo->id) }}">
            {{ $prestamo->acta?->numero_prestamo ?? 'Préstamo' }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Reportar recepción</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="max-w-2xl mx-auto space-y-6">

        <flux:heading size="xl" level="1" class="font-display">Reportar recepción de especímenes</flux:heading>

        {{-- Estado general del envío --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4">
            <flux:heading size="lg" level="2" class="font-display">Estado general del envío</flux:heading>
            <flux:separator />

            <div class="flex flex-col gap-3">
                <label class="flex items-start gap-3 cursor-pointer rounded-lg border border-border p-3 hover:bg-bg-main transition-colors
                    {{ $estadoEnvio === 'sin_novedades' ? 'border-bio-green bg-[#E8F5E9]' : '' }}">
                    <input type="radio" wire:model.live="estadoEnvio" value="sin_novedades" class="mt-0.5 accent-bio-green" />
                    <div>
                        <p class="text-sm font-medium text-text-primary">Sin novedades</p>
                        <p class="text-xs text-text-secondary">Todos los especímenes llegaron en buen estado.</p>
                    </div>
                </label>

                <label class="flex items-start gap-3 cursor-pointer rounded-lg border border-border p-3 hover:bg-bg-main transition-colors
                    {{ $estadoEnvio === 'con_novedades' ? 'border-error bg-[#FFEBEE]' : '' }}">
                    <input type="radio" wire:model.live="estadoEnvio" value="con_novedades" class="mt-0.5 accent-error" />
                    <div>
                        <p class="text-sm font-medium text-text-primary">Con novedades</p>
                        <p class="text-xs text-text-secondary">Uno o más especímenes presentan observaciones.</p>
                    </div>
                </label>
            </div>
        </div>

        {{-- Observaciones por especímen (solo si ConNovedades) --}}
        @if($estadoEnvio === 'con_novedades')
            <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4">
                <flux:heading size="lg" level="2" class="font-display">Observaciones por especímen</flux:heading>
                <flux:separator />

                @php $items = collect($prestamo->acta?->solicitud?->items ?? []); @endphp

                @if($items->isEmpty())
                    <flux:text class="text-xs text-text-secondary">No hay especímenes registrados en esta solicitud.</flux:text>
                @else
                    @error('observaciones')
                        <p class="text-xs text-error">{{ $message }}</p>
                    @enderror
                    <div class="space-y-3">
                        @foreach($observaciones as $i => $obs)
                            <div class="rounded-lg border border-border p-3 space-y-2">
                                @php $item = $items->get($i); @endphp
                                <p class="text-sm font-medium text-text-primary">
                                    {{ $item?->especimen_codigo_externo ?? 'Especímen ' . ($i + 1) }}
                                    @if($item?->especimen_snapshot)
                                        <span class="text-text-secondary font-normal">— {{ $item->especimen_snapshot['nombre'] ?? '' }}</span>
                                    @endif
                                </p>
                                <flux:field>
                                    <flux:label class="sr-only">Observación</flux:label>
                                    <flux:textarea
                                        wire:model="observaciones.{{ $i }}.descripcion"
                                        placeholder="Opcional — describe el estado del especímen si presenta alguna irregularidad."
                                        rows="2" />
                                    <flux:error name="observaciones.{{ $i }}.descripcion" />
                                </flux:field>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Acciones --}}
        <div class="flex items-center gap-3">
            <flux:button variant="primary" wire:click="registrar" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="registrar">Enviar verificación</span>
                <span wire:loading wire:target="registrar">Enviando…</span>
            </flux:button>
            <flux:button variant="ghost" wire:navigate
                href="{{ route('prestamos.investigador.prestamo.detalle', $prestamo->id) }}">
                Cancelar
            </flux:button>
        </div>

    </div>

</div>
