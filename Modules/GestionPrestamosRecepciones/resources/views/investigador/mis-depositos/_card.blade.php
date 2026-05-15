<div class="flex items-center justify-between gap-4">

    {{-- Info principal --}}
    <div class="min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="font-mono text-xs text-text-secondary">{{ $deposito->numero }}</span>
            <flux:badge :variant="$badgeVariant($deposito->estado)" size="sm">{{ $deposito->estado }}</flux:badge>
        </div>
        <p class="text-sm font-medium text-text-primary mt-1">
            {{ $deposito->tipo_tramite }}
            @if($deposito->provincia_origen || $deposito->origen_recoleccion)
                <span class="font-normal text-text-secondary">
                    · {{ $deposito->provincia_origen ?? $deposito->origen_recoleccion }}
                </span>
            @endif
        </p>
        @if($deposito->grupo_animal)
            <p class="text-xs text-text-secondary italic font-serif mt-0.5">{{ $deposito->grupo_animal }}</p>
        @endif
    </div>

    {{-- Fecha + acción --}}
    <div class="flex items-center gap-4 shrink-0">
        <span class="text-xs text-text-secondary hidden sm:block">
            {{ $deposito->created_at->format('d/m/Y') }}
        </span>
        <flux:button
            size="sm"
            variant="ghost"
            icon="eye"
            wire:navigate href="{{ route('prestamos.investigador.deposito.detalle', $deposito->id) }}"
        >
            Ver detalle
        </flux:button>
    </div>

</div>
