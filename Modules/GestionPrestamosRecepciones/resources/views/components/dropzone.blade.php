@props([
    'nombre',
    'propiedad',
    'requerido' => false,
    'cargado' => false,
])

<div
    x-data="{ progreso: 0, subiendo: false, errorSubida: false }"
    x-on:livewire-upload-start="subiendo = true; progreso = 0; errorSubida = false"
    x-on:livewire-upload-progress="progreso = $event.detail.progress"
    x-on:livewire-upload-finish="subiendo = false"
    x-on:livewire-upload-error="subiendo = false; errorSubida = true"
    x-on:click="$refs.fileInput.click()"
    class="flex items-center gap-4 rounded-lg border-2 border-dashed p-4 transition-all cursor-pointer group
        {{ $cargado
            ? 'border-success bg-success/5'
            : ($requerido ? 'border-warning/60 bg-warning/5' : 'border-border bg-bg-main')
        }}
        hover:border-science-blue hover:bg-science-blue/5"
>
    {{-- Icon --}}
    <div class="shrink-0 size-11 rounded-lg border border-border bg-surface flex items-center justify-center shadow-sm">
        @if($cargado)
            <flux:icon name="document-check" class="size-5 text-success" />
        @else
            <flux:icon name="arrow-up-tray" class="size-5 text-text-secondary group-hover:text-science-blue transition-colors" />
        @endif
    </div>

    {{-- Body --}}
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-text-primary leading-snug">{{ $nombre }}</p>

        <div class="flex items-center gap-2 mt-1 flex-wrap">
            @if($cargado)
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-success/15 text-success">
                    <flux:icon name="check" class="size-2.5" />
                    Cargado
                </span>
            @elseif($requerido)
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-error/15 text-error">
                    Requerido
                </span>
            @else
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-border/60 text-text-secondary">
                    Opcional
                </span>
            @endif

            <span x-show="!subiendo && !$cargado" class="text-xs text-text-secondary">
                PDF, JPG o PNG
            </span>
        </div>

        {{-- Progress bar --}}
        <div x-show="subiendo" class="mt-2 h-1.5 w-full rounded-full bg-border overflow-hidden">
            <div
                class="h-full rounded-full bg-science-blue transition-all duration-200"
                :style="'width: ' + progreso + '%'"
            ></div>
        </div>
        <span x-show="subiendo" x-text="progreso + '%'" class="text-xs text-text-secondary mt-0.5 block"></span>

        <p x-show="errorSubida" class="text-xs text-error mt-1">Error al subir. Inténtalo de nuevo.</p>
    </div>

    {{-- Hidden file input --}}
    <input
        type="file"
        wire:model="{{ $propiedad }}"
        class="hidden"
        x-ref="fileInput"
        accept=".pdf,.jpg,.jpeg,.png"
        x-on:click.stop
    />
</div>
