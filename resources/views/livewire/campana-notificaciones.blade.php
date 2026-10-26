<div wire:poll.30s
    x-data="{
        open: false,
        pwaStatus: 'checking',
        pwaMessage: '',
        coords: { left: 0, top: 0 },
        toggle() {
            this.open = ! this.open;
            if (this.open) this.reposition();
        },
        reposition() {
            const t = this.$refs.trigger.getBoundingClientRect();
            const margin = 8;
            const vw = window.innerWidth;
            const pw = (this.$refs.panel && this.$refs.panel.offsetWidth) || 320;
            let left = t.left;
            if (left + pw > vw - margin) left = vw - pw - margin;
            if (left < margin) left = margin;
            this.coords = { left, top: t.bottom + margin };
        },
    }"
    x-on:resize.window="open && reposition()"
    x-on:scroll.window.capture="open && reposition()"
    x-on:keydown.escape.window="open = false"
    x-on:hub-pwa-status.window="pwaStatus = $event.detail.status; pwaMessage = $event.detail.message || ''"
    class="relative"
    data-hub-notification-root>

    {{-- Cada identidad pendiente se observa por separado. El atributo no vive
         en el contenedor Livewire porque puede cambiar sin que el nodo cambie. --}}
    <div class="sr-only" aria-hidden="true">
        @foreach($notificacionesPendientes as $pendiente)
            <span
                wire:key="aviso-pwa-{{ $pendiente->id }}"
                data-hub-notification-id="{{ $pendiente->data['eventoId'] ?? $pendiente->id }}"
                data-hub-notification-record-id="{{ $pendiente->id }}"
                data-hub-notification-title="HubDigital · Laboratorio de Invertebrados"
                data-hub-notification-body="{{ $pendiente->data['mensaje'] ?? '' }}"
                data-hub-notification-url="{{ $pendiente->data['url'] ?? '' }}"
                data-hub-notification-action="{{ $pendiente->data['accion'] ?? 'Abrir expediente' }}"
            ></span>
        @endforeach
    </div>

    <button type="button" x-ref="trigger" x-on:click="toggle()"
        class="inline-flex items-center justify-center size-9 rounded-lg text-current transition-colors hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70" aria-label="Notificaciones">
        <span class="relative inline-flex">
            <flux:icon name="bell" class="size-5" />
            @if($noLeidas > 0)
                <span class="pointer-events-none absolute -top-2 -right-2 inline-flex items-center justify-center min-w-[1rem] h-4 px-1 rounded-full bg-error text-white text-[10px] leading-none font-bold tabular-nums ring-2 ring-surface">
                    {{ $noLeidas > 9 ? '9+' : $noLeidas }}
                </span>
            @endif
        </span>
    </button>

    <div x-ref="panel" x-show="open" x-cloak x-transition.origin.top.left
        x-on:click.outside="open = false"
        :style="`left: ${coords.left}px; top: ${coords.top}px`"
        class="fixed z-50 w-80 max-w-[calc(100dvi-1rem)] overflow-hidden rounded-xl border border-border bg-surface shadow-[0_18px_42px_rgba(16,43,70,.18)]">

        <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
            <div><span class="font-display text-base font-semibold text-blue-navy">Notificaciones</span><p class="mt-0.5 text-[11px] text-text-secondary">Actividad del laboratorio</p></div>
            @if($noLeidas > 0)
                <button wire:click="marcarTodasLeidas" class="shrink-0 whitespace-nowrap text-xs font-medium text-science-blue hover:underline">
                    Marcar todas como leídas
                </button>
            @endif
        </div>

        <div class="border-b border-border bg-bg-main px-4 py-2.5" data-hub-notification-opt-in>
            <button type="button" x-show="pwaStatus !== 'enabled'" x-on:click="window.hubPwaNotifications?.enable()"
                class="flex w-full items-center justify-center gap-1.5 text-xs font-semibold text-science-blue hover:underline">
                <flux:icon name="device-phone-mobile" class="size-3.5" />
                Activar avisos en este dispositivo
            </button>
            <button type="button" x-show="pwaStatus === 'enabled'" x-on:click="window.hubPwaNotifications?.disable()"
                class="flex w-full items-center justify-center gap-1.5 text-xs font-semibold text-bio-green hover:underline">
                <flux:icon name="check-circle" class="size-3.5" />
                Avisos activos · Desactivar
            </button>

        </div>

        <div class="max-h-96 overflow-y-auto divide-y divide-border">
            @forelse($notificaciones as $n)
                @php $leida = $n->read_at !== null; @endphp
                <button type="button" wire:click="abrir('{{ $n->id }}')"
                    class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-bg-main transition-colors {{ $leida ? '' : 'bg-science-blue/5' }}">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-bg-main border border-border">
                        <flux:icon name="{{ $n->data['icono'] ?? 'bell' }}" class="size-4 text-science-blue" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-text-primary leading-snug">{{ $n->data['mensaje'] ?? 'Notificación' }}</p>
                        <p class="text-xs text-text-secondary mt-0.5">{{ $n->created_at->diffForHumans() }}</p>
                    </div>
                    @unless($leida)
                        <span class="mt-1 size-2 shrink-0 rounded-full bg-science-blue"></span>
                    @endunless
                </button>
            @empty
                <div class="px-4 py-10 text-center">
                    <flux:icon name="bell-slash" class="size-7 mx-auto text-text-secondary/40" />
                    <p class="text-sm text-text-secondary mt-2">Sin notificaciones</p>
                </div>
            @endforelse
        </div>

        @if($notificaciones->isNotEmpty())
            <div class="border-t border-border px-4 py-2.5">
                @if($puedeCargarMasHistorial)
                    <button type="button" wire:click="cargarMasHistorial" class="mb-2 flex w-full items-center justify-center text-xs font-medium text-science-blue hover:underline">
                        Ver notificaciones anteriores
                    </button>
                @endif
                <button type="button" wire:click="eliminarTodas"
                    wire:confirm="¿Vaciar todas las notificaciones? Esta acción no se puede deshacer."
                    class="flex w-full items-center justify-center gap-1.5 text-xs font-medium text-error hover:underline">
                    <flux:icon name="trash" class="size-3.5" />
                    Vaciar notificaciones
                </button>
            </div>
        @endif
    </div>
</div>
