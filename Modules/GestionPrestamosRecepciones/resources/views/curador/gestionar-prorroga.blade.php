<div>
<div class="space-y-5">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.prestamos') }}">
            Préstamos
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.prestamo.detalle', $prestamo->prestamoId) }}">
            {{ $prestamo->numeroPrestamo }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Gestionar prórroga</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="font-display">Gestionar prórroga</flux:heading>
            <flux:text class="text-text-secondary text-sm mt-1">
                Revisa la solicitud del investigador y resuélvela.
            </flux:text>
        </div>
        <x-gestionprestamosrecepciones::prestamo-status-badge :estado="$prestamo->estado" />
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
                    <dd class="font-mono font-medium text-text-primary mt-1">{{ $prestamo->numeroPrestamo }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-secondary uppercase tracking-wide">Fecha de vencimiento actual</dt>
                    <dd class="font-medium text-text-primary mt-1">{{ $prestamo->fechaFin?->format('d/m/Y') ?? '—' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Solicitud pendiente --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-border flex items-center gap-3">
            <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                <flux:icon name="calendar-days" class="size-3.5" />
            </div>
            <flux:heading size="base" level="2" class="font-display">Solicitud del investigador</flux:heading>
        </div>
        <div class="p-5">
            @if($solicitud)
                <dl class="space-y-4 text-sm">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Fecha propuesta</dt>
                            <dd class="font-medium text-text-primary mt-1">{{ $solicitud->fechaPropuesta->format('d/m/Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Solicitada el</dt>
                            <dd class="font-medium text-text-primary mt-1">{{ $solicitud->solicitadaEn->format('d/m/Y') }}</dd>
                        </div>
                    </div>
                    <div>
                        <dt class="text-xs text-text-secondary uppercase tracking-wide">Justificación</dt>
                        <dd class="text-text-primary mt-1 leading-relaxed">{{ $solicitud->justificacion }}</dd>
                    </div>
                </dl>
            @else
                <flux:text class="text-text-secondary text-sm">No se encontró una solicitud de prórroga pendiente.</flux:text>
            @endif
        </div>
    </div>

    {{-- Acciones --}}
    @if($solicitud)
        <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
            <flux:button variant="danger" icon="x-circle" class="w-full sm:w-auto"
                wire:click="$set('showRechazarModal', true)">
                Rechazar prórroga
            </flux:button>
            <flux:button variant="primary" icon="check-circle" class="w-full sm:w-auto"
                wire:click="$set('showAprobarModal', true)">
                Aprobar prórroga
            </flux:button>
        </div>
    @endif

</div>

{{-- Modal: aprobar prórroga --}}
<flux:modal wire:model="showAprobarModal" class="max-w-md">
    <div class="space-y-5 p-2">
        <flux:heading size="lg">Aprobar prórroga</flux:heading>
        <flux:text class="text-text-secondary text-sm">
            Confirma la nueva fecha de vencimiento para el préstamo.
        </flux:text>
        <flux:field>
            <flux:label>Nueva fecha de vencimiento <span class="text-error">*</span></flux:label>
            <flux:input type="date" wire:model="nuevaFechaFin" min="{{ now()->addDay()->format('Y-m-d') }}" />
            <flux:error name="nuevaFechaFin" />
        </flux:field>
        <div class="flex flex-col gap-2 sm:flex-row sm:justify-end pt-2">
            <flux:button variant="ghost" class="w-full sm:w-auto" wire:click="$set('showAprobarModal', false)">
                Cancelar
            </flux:button>
            <flux:button variant="primary" icon="check-circle" class="w-full sm:w-auto"
                wire:click="aprobar" wire:loading.attr="disabled" wire:target="aprobar">
                <flux:icon wire:loading wire:target="aprobar" name="arrow-path" class="animate-spin" />
                Confirmar aprobación
            </flux:button>
        </div>
    </div>
</flux:modal>

{{-- Modal: rechazar prórroga --}}
<flux:modal wire:model="showRechazarModal" class="max-w-md">
    <div class="space-y-4 p-2">
        <flux:heading size="lg">Rechazar prórroga</flux:heading>
        <flux:text class="text-text-secondary text-sm">
            Indica el motivo del rechazo. El préstamo conservará su fecha de vencimiento original.
        </flux:text>
        <flux:field>
            <flux:label>Motivo del rechazo <span class="text-error">*</span></flux:label>
            <flux:textarea wire:model="comentarioRechazo" rows="4"
                placeholder="Explica por qué no se concede la prórroga..." />
            <flux:error name="comentarioRechazo" />
        </flux:field>
        <div class="flex flex-col gap-2 sm:flex-row sm:justify-end pt-2">
            <flux:button variant="ghost" class="w-full sm:w-auto" wire:click="$set('showRechazarModal', false)">
                Cancelar
            </flux:button>
            <flux:button variant="danger" icon="x-circle" class="w-full sm:w-auto"
                wire:click="rechazar" wire:loading.attr="disabled" wire:target="rechazar">
                <flux:icon wire:loading wire:target="rechazar" name="arrow-path" class="animate-spin" />
                Rechazar prórroga
            </flux:button>
        </div>
    </div>
</flux:modal>
</div>
