<div>
<div class="space-y-5 pb-24">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.mis-prestamos') }}">
            Mis préstamos
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.prestamo.detalle', $prestamo->id) }}">
            {{ $prestamo->acta?->numero_prestamo ?? 'Préstamo' }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Registrar devolución</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div>
        <flux:heading size="xl" level="1" class="font-display">Registrar devolución</flux:heading>
        <flux:text class="text-text-secondary text-sm mt-1">
            Confirma que has enviado los especímenes de vuelta a la colección.
        </flux:text>
    </div>

    {{-- Aviso informativo --}}
    <div class="rounded-lg border border-warning/40 bg-warning/5 p-5 flex flex-col sm:flex-row items-start gap-4">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-warning/15">
            <flux:icon name="information-circle" class="size-5 text-warning" />
        </div>
        <div>
            <p class="text-sm font-semibold text-text-primary">¿Qué implica registrar la devolución?</p>
            <p class="text-xs text-text-secondary mt-1 leading-relaxed">
                Al confirmar, informas que los especímenes ya fueron enviados de vuelta.
                El préstamo pasará a estado <strong>«en revisión»</strong> y quedará pendiente de verificación por el curador.
            </p>
        </div>
    </div>

    {{-- Datos del préstamo --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-border flex items-center gap-3">
            <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                <flux:icon name="archive-box" class="size-3.5" />
            </div>
            <flux:heading size="base" level="2" class="font-display">Datos del préstamo</flux:heading>
        </div>
        <div class="p-5">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs text-text-secondary uppercase tracking-wide">N.º préstamo</dt>
                    <dd class="font-mono font-medium text-text-primary mt-1">
                        {{ $prestamo->acta?->numero_prestamo ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-text-secondary uppercase tracking-wide">Fecha límite de devolución</dt>
                    <dd class="font-medium text-text-primary mt-1">
                        {{ $prestamo->fecha_fin?->format('d/m/Y') ?? '—' }}
                    </dd>
                </div>
                @php $totalItems = $prestamo->acta?->solicitud?->items?->count() ?? 0; @endphp
                @if($totalItems > 0)
                    <div>
                        <dt class="text-xs text-text-secondary uppercase tracking-wide">Especímenes prestados</dt>
                        <dd class="font-medium text-text-primary mt-1">{{ $totalItems }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

</div>

{{-- Barra de acciones fija al fondo --}}
<div class="fixed bottom-0 inset-x-0 z-20 bg-surface border-t border-border shadow-lg">
    <div class="px-6 py-3 flex items-center gap-3 justify-end">
        <flux:button variant="ghost" wire:navigate
            href="{{ route('prestamos.investigador.prestamo.detalle', $prestamo->id) }}">
            Cancelar
        </flux:button>
        <flux:button variant="primary" wire:click="registrar" wire:loading.attr="disabled">
            <flux:icon wire:loading wire:target="registrar" name="arrow-path" class="animate-spin" />
            <flux:icon name="arrow-uturn-left" class="size-4" />
            Registrar devolución
        </flux:button>
    </div>
</div>
</div>
