@props(['pagina', 'totalPaginas', 'totalItems', 'inicio', 'fin'])

@if($totalItems > 0)
<div class="flex items-center justify-between px-4 py-3 border-t border-border">
    <span class="text-xs text-text-secondary">
        Mostrando {{ $inicio }}–{{ $fin }} de {{ $totalItems }} registros
    </span>

    @if($totalPaginas > 1)
    <div class="flex items-center gap-1">
        <flux:button
            size="sm"
            variant="ghost"
            icon="chevron-left"
            wire:click="prevPage"
            :disabled="$pagina === 1"
        />

        @php
            $rango = collect(range(1, $totalPaginas));
            $visible = $rango->filter(fn($p) =>
                $p === 1 || $p === $totalPaginas ||
                abs($p - $pagina) <= 1
            )->values();
        @endphp

        @foreach($visible as $i => $p)
            @if($i > 0 && $p - $visible[$i - 1] > 1)
                <span class="px-1 text-text-secondary text-xs">…</span>
            @endif
            <flux:button
                size="sm"
                :variant="$pagina === $p ? 'primary' : 'ghost'"
                wire:click="goToPage({{ $p }})"
            >{{ $p }}</flux:button>
        @endforeach

        <flux:button
            size="sm"
            variant="ghost"
            icon="chevron-right"
            wire:click="nextPage"
            :disabled="$pagina === $totalPaginas"
        />
    </div>
    @endif
</div>
@endif
