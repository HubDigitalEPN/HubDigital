@props(['id', 'modelo', 'etiqueta' => 'Fotografía'])

<div class="space-y-2" x-data="{ nombre: '' }"
    x-init="$watch(() => $wire.$get(@js($modelo)), foto => { if (foto == null || foto === '') nombre = '' })">
    <p id="{{ $id }}-etiqueta" class="text-sm font-semibold">{{ $etiqueta }}</p>
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
        <div class="inline-flex rounded-lg focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-science-blue">
            <input id="{{ $id }}" type="file" class="sr-only" wire:model="{{ $modelo }}"
                accept="image/jpeg,image/png,image/webp,image/avif"
                aria-describedby="{{ $id }}-etiqueta {{ $id }}-seleccion"
                x-on:change="nombre = $event.target.files[0]?.name ?? ''"
                x-on:livewire-upload-error="nombre = ''" x-on:livewire-upload-cancel="nombre = ''">
            <label for="{{ $id }}" class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-border bg-surface px-3 py-2 text-sm font-semibold text-text-primary shadow-sm hover:border-science-blue hover:bg-bg-main">
                <flux:icon name="paper-clip" class="size-4 shrink-0" aria-hidden="true" />
                Elegir fotografía
            </label>
        </div>
        <span id="{{ $id }}-seleccion" role="status" aria-atomic="true" class="min-w-0 break-all text-sm text-text-secondary"
            x-text="nombre || 'Ninguna foto seleccionada'">Ninguna foto seleccionada</span>
    </div>
</div>
