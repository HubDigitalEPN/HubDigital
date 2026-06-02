<div class="space-y-5">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.panel') }}">
            Panel
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.prestamo.detalle', $prestamo->prestamoId) }}">
            Préstamo {{ $prestamo->numeroPrestamo }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Verificación de entrega</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div>
        <flux:heading size="xl" level="1" class="font-display">Verificación de entrega</flux:heading>
        <flux:text class="text-text-secondary text-sm mt-1">
            Revisa el informe del investigador y aprueba la verificación para activar el préstamo.
        </flux:text>
    </div>

    @if($verificacion)

        {{-- Informe del investigador --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                    <flux:icon name="clipboard-document-check" class="size-3.5" />
                </div>
                <flux:heading size="base" level="2" class="font-display">Informe del investigador</flux:heading>
            </div>
            <div class="p-5 space-y-4">
                <div class="flex items-center gap-2">
                    @if($verificacion->estadoEnvio()->value === 'sin_novedades')
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold bg-bio-green/10 text-bio-green border border-bio-green/20">
                            <span class="size-1.5 rounded-full bg-bio-green"></span>
                            Sin novedades
                        </span>
                        <span class="text-sm text-text-secondary">Todos los especímenes llegaron en buen estado.</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold bg-error/10 text-error border border-error/20">
                            <span class="size-1.5 rounded-full bg-error"></span>
                            Con novedades
                        </span>
                    @endif
                </div>

                @if(count($verificacion->observaciones()) > 0)
                    <div class="space-y-2">
                        <p class="text-xs font-semibold text-text-secondary uppercase tracking-wide">Observaciones reportadas</p>
                        @foreach($verificacion->observaciones() as $obs)
                            @php
                                $codigoEspecimen = \Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ItemPrestamoModel::query()
                                    ->where('id', $obs->itemPrestamoId)
                                    ->value('especimen_codigo_externo') ?? $obs->itemPrestamoId;
                            @endphp
                            <div class="rounded-lg border border-border bg-bg-main px-4 py-3">
                                <div class="flex items-center gap-2 mb-1">
                                    <flux:icon name="beaker" class="size-4 text-science-blue shrink-0" />
                                    <p class="text-xs text-text-secondary font-mono">{{ $codigoEspecimen }}</p>
                                </div>
                                <p class="text-sm text-text-primary">{{ $obs->descripcion }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Decisión --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                    <flux:icon name="check-badge" class="size-3.5" />
                </div>
                <flux:heading size="base" level="2" class="font-display">Decisión</flux:heading>
            </div>
            <div class="p-5 space-y-4">
                <p class="text-sm text-text-secondary">
                    Al aprobar, el préstamo quedará en estado <strong class="text-text-primary">Activo</strong> y el investigador podrá comenzar a trabajar con los especímenes.
                </p>
                <div class="flex items-center gap-3">
                    <flux:button variant="primary" icon="check-circle" wire:click="aprobar" wire:loading.attr="disabled">
                        <flux:icon wire:loading wire:target="aprobar" name="arrow-path" class="animate-spin" />
                        Aprobar verificación
                    </flux:button>
                    <flux:button variant="ghost" wire:navigate
                        href="{{ route('prestamos.curador.prestamo.detalle', $prestamo->prestamoId) }}">
                        Cancelar
                    </flux:button>
                </div>
            </div>
        </div>

    @else
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:heading size="sm">Sin verificación registrada</flux:heading>
            <flux:text class="mt-1 text-sm">El investigador aún no ha reportado la recepción de los especímenes.</flux:text>
        </flux:callout>
    @endif

</div>
