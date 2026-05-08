@props([
    'activo'       => false,
    'deshabilitado' => false,
    'titulo',
    'descripcion'  => null,
    'grupo'        => '',
])

<div
    x-data="{ active: @js($activo) }"
    x-on:radio-card-select.window="if ($event.detail.grupo === @js($grupo)) active = ($event.detail.valor === @js($titulo))"
    {{ $attributes->merge(['class' => 'relative rounded-lg border-2 p-4 transition-all select-none ' . ($deshabilitado ? 'opacity-55 cursor-not-allowed pointer-events-none' : 'cursor-pointer')]) }}
    x-bind:class="active
        ? 'border-blue-navy bg-blue-navy/5 shadow-sm'
        : 'border-border bg-surface hover:border-science-blue hover:bg-science-blue/5'"
>
    <div class="flex items-start gap-3">
        {{-- Radio ring --}}
        <div
            class="mt-0.5 size-4 shrink-0 rounded-full border-2 flex items-center justify-center transition-colors"
            x-bind:class="active ? 'border-blue-navy bg-blue-navy' : 'border-border bg-surface'"
        >
            <div class="size-2 rounded-full bg-white transition-opacity" x-bind:class="active ? 'opacity-100' : 'opacity-0'"></div>
        </div>

        {{-- Content --}}
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                @isset($icono)
                    <div class="flex items-center justify-center size-8 rounded-md bg-blue-navy/10 shrink-0">
                        {{ $icono }}
                    </div>
                @endisset
                <p class="font-semibold text-sm text-text-primary">{{ $titulo }}</p>
                @isset($badge)
                    {{ $badge }}
                @endisset
            </div>
            @if($descripcion)
                <p class="mt-1 text-xs text-text-secondary leading-relaxed">{{ $descripcion }}</p>
            @endif
        </div>
    </div>
</div>
