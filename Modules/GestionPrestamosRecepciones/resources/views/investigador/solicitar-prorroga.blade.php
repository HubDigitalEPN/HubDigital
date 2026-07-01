<div>
<div class="space-y-5 pb-24">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.mis-prestamos') }}">
            Mis préstamos
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.prestamo.detalle', $prestamo->prestamoId) }}">
            {{ $prestamo->numeroPrestamo }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Solicitar prórroga</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div>
        <flux:heading size="xl" level="1" class="font-display">Solicitar prórroga</flux:heading>
        <flux:text class="text-text-secondary text-sm mt-1">
            Pide una extensión del plazo de devolución antes de que el préstamo venza.
        </flux:text>
    </div>

    {{-- Contexto del plazo actual --}}
    <div class="rounded-lg border border-warning/40 bg-warning/5 p-5 flex flex-col sm:flex-row items-start gap-4">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-warning/15">
            <flux:icon name="clock" class="size-5 text-warning" />
        </div>
        <div>
            <p class="text-sm font-semibold text-text-primary">Plazo actual de devolución</p>
            <p class="text-xs text-text-secondary mt-1 leading-relaxed">
                Tu préstamo <strong>{{ $prestamo->numeroPrestamo }}</strong> vence el
                <strong>{{ $prestamo->fechaFin?->format('d/m/Y') ?? '—' }}</strong>.
                Al enviar la solicitud, el préstamo quedará en estado «prórroga solicitada» hasta que el curador la resuelva.
            </p>
        </div>
    </div>

    {{-- Formulario --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-border flex items-center gap-3">
            <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                <flux:icon name="calendar-days" class="size-3.5" />
            </div>
            <flux:heading size="base" level="2" class="font-display">Datos de la solicitud</flux:heading>
        </div>
        <div class="p-5 space-y-5">
            <flux:field>
                <flux:label>Nueva fecha de vencimiento <span class="text-error">*</span></flux:label>
                <flux:input
                    type="date"
                    wire:model="nuevaFechaFin"
                    min="{{ now()->addDay()->format('Y-m-d') }}" />
                <flux:error name="nuevaFechaFin" />
            </flux:field>

            <flux:field>
                <flux:label>Justificación <span class="text-error">*</span></flux:label>
                <flux:textarea
                    wire:model="justificacion"
                    rows="4"
                    placeholder="Explica por qué necesitas más tiempo para devolver los especímenes..." />
                <flux:error name="justificacion" />
            </flux:field>
        </div>
    </div>

</div>

{{-- Barra de acciones fija al fondo --}}
<div class="fixed bottom-0 inset-x-0 z-20 bg-surface border-t border-border shadow-lg">
    <div class="px-6 py-3 flex items-center gap-3 justify-end">
        <flux:button variant="ghost" wire:navigate
            href="{{ route('prestamos.investigador.prestamo.detalle', $prestamo->prestamoId) }}">
            Cancelar
        </flux:button>
        <flux:button variant="primary" wire:click="solicitar" wire:loading.attr="disabled" wire:target="solicitar">
            <flux:icon wire:loading wire:target="solicitar" name="arrow-path" class="animate-spin" />
            <flux:icon name="paper-airplane" class="size-4" />
            Enviar solicitud
        </flux:button>
    </div>
</div>
</div>
