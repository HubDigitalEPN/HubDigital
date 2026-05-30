<div class="p-6 space-y-6">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.panel') }}">
            Panel
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.prestamo.detalle', $prestamo->prestamoId) }}">
            Préstamo {{ $prestamo->numeroPrestamo }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Verificación de entrega</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="max-w-2xl space-y-6">

        <flux:heading size="xl" level="1" class="font-display">Verificación de entrega</flux:heading>

        @if($verificacion)

            {{-- Estado del envío --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-3">
                <flux:heading size="lg" level="2" class="font-display">Informe del investigador</flux:heading>
                <flux:separator />

                <div class="flex items-center gap-2">
                    @if($verificacion->estadoEnvio()->value === 'sin_novedades')
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-[#E8F5E9] text-[#2E7D32] border border-[#A5D6A7]">
                            <span class="size-1.5 rounded-full bg-[#2E7D32]"></span>
                            Sin novedades
                        </span>
                        <span class="text-sm text-text-secondary">Todos los especímenes llegaron en buen estado.</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-[#FFEBEE] text-[#C62828] border border-[#EF9A9A]">
                            <span class="size-1.5 rounded-full bg-[#C62828]"></span>
                            Con novedades
                        </span>
                    @endif
                </div>

                @if(count($verificacion->observaciones()) > 0)
                    <div class="space-y-2 mt-2">
                        <p class="text-xs font-medium text-text-secondary uppercase tracking-wide">Observaciones</p>
                        @foreach($verificacion->observaciones() as $obs)
                            <div class="rounded-lg border border-border bg-bg-main px-3 py-2">
                                <p class="text-xs text-text-secondary font-mono">{{ $obs->itemPrestamoId }}</p>
                                <p class="text-sm text-text-primary mt-0.5">{{ $obs->descripcion }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Acción --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-3">
                <flux:heading size="lg" level="2" class="font-display">Decisión</flux:heading>
                <flux:separator />
                <p class="text-sm text-text-secondary">
                    Al aprobar, el préstamo quedará en estado <strong>Activo</strong> y el investigador podrá comenzar a trabajar con los especímenes.
                </p>
                <div class="flex items-center gap-3 pt-1">
                    <flux:button variant="primary" wire:click="aprobar" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="aprobar">Aprobar verificación</span>
                        <span wire:loading wire:target="aprobar">Aprobando…</span>
                    </flux:button>
                    <flux:button variant="ghost" wire:navigate
                        href="{{ route('prestamos.curador.prestamo.detalle', $prestamo->prestamoId) }}">
                        Cancelar
                    </flux:button>
                </div>
            </div>

        @else
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Sin verificación registrada</flux:callout.heading>
                <flux:callout.text>El investigador aún no ha reportado la recepción de los especímenes.</flux:callout.text>
            </flux:callout>
        @endif

    </div>

</div>
