<div>
<div class="space-y-5 pb-24">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.mis-prestamos') }}">
            Mis préstamos
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.prestamo.detalle', $prestamo->prestamoId) }}">
            {{ $prestamo->numeroPrestamo }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Reportar recepción</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div>
        <flux:heading size="xl" level="1" class="font-display">Reportar recepción de especímenes</flux:heading>
        <flux:text class="text-text-secondary text-sm mt-1">
            Indica el estado en que recibiste los especímenes para activar el préstamo.
        </flux:text>
    </div>

    {{-- Sección 1: Estado general --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-border flex items-center gap-3">
            <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white text-sm font-bold shrink-0">
                1
            </div>
            <flux:heading size="base" level="2" class="font-display">Estado general del envío</flux:heading>
        </div>
        <div class="p-5">
            <div class="flex flex-col gap-3">
                <label class="flex items-start gap-4 cursor-pointer rounded-lg border-2 p-4 transition-all
                    {{ $estadoEnvio === 'sin_novedades'
                        ? 'border-bio-green bg-bio-green/5 ring-1 ring-bio-green/20'
                        : 'border-border hover:border-bio-green/40' }}">
                    <input type="radio" wire:model.live="estadoEnvio" value="sin_novedades"
                        class="mt-0.5 accent-bio-green shrink-0" />
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-text-primary">Sin novedades</p>
                        <p class="text-xs text-text-secondary mt-0.5">Todos los especímenes llegaron en buen estado y en la cantidad correcta.</p>
                    </div>
                    @if($estadoEnvio === 'sin_novedades')
                        <flux:icon name="check-circle" class="size-5 text-bio-green shrink-0" />
                    @endif
                </label>

                <label class="flex items-start gap-4 cursor-pointer rounded-lg border-2 p-4 transition-all
                    {{ $estadoEnvio === 'con_novedades'
                        ? 'border-error bg-error/5 ring-1 ring-error/20'
                        : 'border-border hover:border-error/40' }}">
                    <input type="radio" wire:model.live="estadoEnvio" value="con_novedades"
                        class="mt-0.5 accent-error shrink-0" />
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-text-primary">Con novedades</p>
                        <p class="text-xs text-text-secondary mt-0.5">Uno o más especímenes presentan daños, faltan unidades o hay irregularidades.</p>
                    </div>
                    @if($estadoEnvio === 'con_novedades')
                        <flux:icon name="exclamation-triangle" class="size-5 text-error shrink-0" />
                    @endif
                </label>
            </div>
        </div>
    </div>

    {{-- Sección 2: Observaciones (solo si hay novedades) --}}
    @if($estadoEnvio === 'con_novedades')
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white text-sm font-bold shrink-0">
                    2
                </div>
                <flux:heading size="base" level="2" class="font-display">Observaciones por espécimen</flux:heading>
            </div>
            <div class="p-5 space-y-3">

                @php $items = collect($items); @endphp

                @if($items->isEmpty())
                    <flux:text class="text-xs text-text-secondary">No hay especímenes registrados en esta solicitud.</flux:text>
                @else
                    @error('observaciones')
                        <p class="text-xs text-error">{{ $message }}</p>
                    @enderror

                    @foreach($observaciones as $i => $obs)
                        @php $item = $items->get($i); @endphp
                        <div class="rounded-lg border border-border bg-bg-main overflow-hidden">
                            <div class="flex items-center gap-3 px-4 py-2.5 border-b border-border">
                                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-science-blue/10">
                                    <flux:icon name="beaker" class="size-4 text-science-blue" />
                                </div>
                                <p class="text-sm font-mono font-medium text-text-primary">
                                    {{ $item?->codigoExterno ?? 'Espécimen ' . ($i + 1) }}
                                </p>
                                @if($item?->nombre)
                                    <span class="text-xs text-text-secondary">— {{ $item->nombre }}</span>
                                @endif
                            </div>
                            <div class="p-3">
                                <flux:field>
                                    <flux:label class="sr-only">Observación</flux:label>
                                    <flux:textarea
                                        wire:model="observaciones.{{ $i }}.descripcion"
                                        placeholder="Describe el estado del espécimen si presenta alguna irregularidad (opcional)."
                                        rows="2" />
                                    <flux:error name="observaciones.{{ $i }}.descripcion" />
                                </flux:field>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    @endif

</div>

{{-- Barra de acciones fija al fondo --}}
<div class="fixed bottom-0 inset-x-0 z-20 bg-surface border-t border-border shadow-lg">
    <div class="px-6 py-3 flex items-center gap-3 justify-end">
        <flux:button variant="ghost" wire:navigate
            href="{{ route('prestamos.investigador.prestamo.detalle', $prestamo->prestamoId) }}">
            Cancelar
        </flux:button>
        <flux:button variant="primary" wire:click="registrar" wire:loading.attr="disabled">
            <flux:icon wire:loading wire:target="registrar" name="arrow-path" class="animate-spin" />
            <flux:icon name="check" class="size-4" />
            Enviar verificación
        </flux:button>
    </div>
</div>
</div>
