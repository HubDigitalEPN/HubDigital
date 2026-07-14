<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">

    {{-- Info principal --}}
    <div class="min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="font-mono text-xs text-text-secondary">{{ $deposito->numero }}</span>
            <flux:badge :variant="$badgeVariant($deposito->estado)" size="sm">{{ $deposito->estado }}</flux:badge>
            @if(($estadoRecepcion ?? null) !== null)
                @php
                    $recepcionBadge = match($estadoRecepcion) {
                        'Verificado Físicamente' => ['green', 'Recibido físicamente'],
                        'Verificado con Observaciones' => ['amber', 'Recibido con observaciones'],
                        'Recepción Suspendida' => ['red', 'Recepción suspendida'],
                        default => ['sky', 'En recepción'],
                    };
                @endphp
                <flux:badge color="{{ $recepcionBadge[0] }}" size="sm">{{ $recepcionBadge[1] }}</flux:badge>
            @endif
            @if($actaFirmada ?? false)
                <flux:badge color="green" size="sm" icon="document-check">Acta disponible</flux:badge>
            @endif
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
        @if($deposito->estado === 'Requiere Corrección')
            <flux:button
                size="sm"
                variant="primary"
                icon="pencil-square"
                wire:navigate href="{{ route('prestamos.investigador.deposito.corregir', $deposito->id) }}"
            >
                Corregir
            </flux:button>
        @endif
        @if($actaFirmada ?? false)
            <flux:button
                size="sm"
                variant="ghost"
                icon="document-arrow-down"
                href="{{ route('prestamos.deposito.acta-recepcion', $deposito->id) }}"
                target="_blank"
                rel="noopener"
            >
                Acta
            </flux:button>
        @endif
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
