<div wire:poll.30s x-data="{ open: false }" class="relative">
    <button type="button" x-on:click="open = ! open"
        class="relative inline-flex items-center justify-center size-9 rounded-lg text-current hover:bg-black/5 transition-colors" aria-label="Notificaciones">
        <flux:icon name="bell" class="size-5" />
        @if($noLeidas > 0)
            <span class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-error text-white text-[10px] font-bold tabular-nums">
                {{ $noLeidas > 9 ? '9+' : $noLeidas }}
            </span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition.origin.top.right
        x-on:click.outside="open = false"
        class="absolute right-0 mt-2 w-80 max-w-[90vw] rounded-lg border border-border bg-surface shadow-lg z-50 overflow-hidden">

        <div class="flex items-center justify-between px-4 py-3 border-b border-border">
            <span class="text-sm font-semibold text-text-primary">Notificaciones</span>
            @if($noLeidas > 0)
                <button wire:click="marcarTodasLeidas" class="text-xs font-medium text-science-blue hover:underline">
                    Marcar todas como leídas
                </button>
            @endif
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
    </div>
</div>
