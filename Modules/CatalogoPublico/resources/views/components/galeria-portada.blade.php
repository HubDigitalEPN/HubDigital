@props([
    'titulo',
    'etiqueta',
    'nivel',
    'valor',
    'imagenes' => [],
])

<section class="mb-6">
    <div class="mb-3 flex items-center gap-2">
        <h3 class="text-sm font-semibold text-text-primary">
            Galería de {{ $titulo }}:
            <span class="font-serif italic">{{ $etiqueta }}</span>
        </h3>
        <span class="text-xs text-text-secondary tabular-nums">
            {{ count($imagenes) }} {{ count($imagenes) === 1 ? 'imagen' : 'imágenes' }}
        </span>
    </div>

    @if(count($imagenes) === 0)
        <div class="flex items-center justify-center rounded-lg border border-dashed border-border bg-bg-main py-10 text-sm text-text-secondary gap-2">
            <flux:icon name="photo" class="size-5 text-border" />
            Sin imágenes para este nivel todavía.
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($imagenes as $img)
                <div @class([
                    'group relative overflow-hidden rounded-lg border bg-surface shadow-sm',
                    'border-bio-green ring-2 ring-bio-green/30' => $img['esPortada'],
                    'border-border' => ! $img['esPortada'],
                ])>
                    <div class="aspect-square bg-bg-main">
                        <img src="{{ $img['url'] }}" alt="{{ $img['nombreArchivo'] }}" class="h-full w-full object-cover" />
                    </div>

                    @if($img['esPortada'])
                        <span class="absolute left-2 top-2 inline-flex items-center gap-1 rounded bg-bio-green px-2 py-0.5 text-xs font-medium text-white">
                            <flux:icon name="star" class="size-3" />
                            Portada
                        </span>
                    @endif

                    <div class="p-2">
                        <p class="truncate text-xs text-text-secondary" title="{{ $img['nombreArchivo'] }}">
                            {{ $img['nombreArchivo'] }}
                        </p>
                        @unless($img['esPortada'])
                            <flux:button
                                wire:click="usarComoPortada('{{ $nivel }}', '{{ $img['imagenId'] }}')"
                                wire:loading.attr="disabled"
                                wire:target="usarComoPortada"
                                variant="subtle"
                                icon="star"
                                class="mt-1.5 w-full"
                            >
                                Usar como portada
                            </flux:button>
                        @endunless
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
