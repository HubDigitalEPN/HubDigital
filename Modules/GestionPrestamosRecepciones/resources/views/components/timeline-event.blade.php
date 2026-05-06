@props(['fecha', 'titulo', 'descripcion' => null, 'ultimo' => false])

<div class="flex gap-3">
    <div class="flex flex-col items-center">
        <div class="size-2.5 rounded-full bg-science-blue mt-1 shrink-0"></div>
        @unless($ultimo)
            <div class="w-px flex-1 bg-border mt-1"></div>
        @endunless
    </div>
    <div class="pb-4">
        <p class="text-xs text-text-secondary">{{ $fecha }}</p>
        <p class="text-sm font-medium text-text-primary">{{ $titulo }}</p>
        @if($descripcion)
            <p class="text-xs text-text-secondary mt-0.5">{{ $descripcion }}</p>
        @endif
    </div>
</div>
