@props([
    // Ranura mapeada: ['numeroRanura', 'ocupada', 'codigoCaja', 'cajaId', 'clasificacion', ...].
    'ranura',
    // Clase de color de fondo del cajón ocupado (incluye color de texto).
    'clase' => '',
    // Etiqueta de taxón ya resuelta por el padre (con fallback por rango).
    'etiqueta' => 'Sin clasificar',
    // Variante reducida y NO interactiva para la vista general de gabinetes.
    'compacto' => false,
])

@php
    $ocupada = $ranura['ocupada'] ?? false;
    $numero = $ranura['numeroRanura'] ?? '';
    $codigo = $ranura['codigoCaja'] ?? '';
    $tooltip = $ocupada ? $codigo.' · '.$etiqueta : 'Ranura '.$numero.' — vacía';
@endphp

@if($compacto)
    {{-- Mini-cajón de la vista general: visual y con tooltip; navegar es tarea del botón «Abrir». --}}
    <flux:tooltip :content="$tooltip">
        <div @class([
            'flex h-7 items-center gap-1.5 rounded px-1.5 text-[10px] leading-none',
            $clase => $ocupada,
            'border border-dashed border-border bg-bg-main text-text-secondary' => ! $ocupada,
        ])>
            <span class="shrink-0 font-bold opacity-80">{{ $numero }}</span>
            @if($ocupada)
                <span class="truncate font-medium">{{ $codigo }}</span>
            @endif
        </div>
    </flux:tooltip>
@elseif($ocupada)
    {{-- Cajón ocupado: barra horizontal clicable (el padre aporta el wire:click). --}}
    <button
        type="button"
        {{ $attributes->merge(['class' => 'flex min-h-[44px] w-full items-center gap-2.5 rounded-md px-2.5 py-1.5 text-left shadow-sm transition-colors hover:ring-2 hover:ring-science-blue '.$clase]) }}
    >
        <span class="grid size-7 shrink-0 place-items-center rounded bg-black/15 text-xs font-bold">{{ $numero }}</span>
        <span class="flex min-w-0 flex-col leading-tight">
            <span class="truncate text-sm font-bold">{{ $codigo }}</span>
            <span class="truncate font-serif text-xs italic opacity-90">{{ $etiqueta }}</span>
        </span>
    </button>
@else
    {{-- Cajón vacío: barra discontinua, mismo alto que un cajón ocupado. --}}
    <div class="flex min-h-[44px] w-full items-center gap-2.5 rounded-md border border-dashed border-border bg-bg-main px-2.5 py-1.5 text-text-secondary">
        <span class="grid size-7 shrink-0 place-items-center rounded border border-dashed border-border text-xs font-bold">{{ $numero }}</span>
        <span class="text-xs">Vacía</span>
    </div>
@endif
