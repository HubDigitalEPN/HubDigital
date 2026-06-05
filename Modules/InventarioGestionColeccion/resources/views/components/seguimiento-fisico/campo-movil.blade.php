@props(['etiqueta'])

<div class="flex justify-between gap-3">
    <dt class="shrink-0 text-text-secondary">{{ $etiqueta }}</dt>
    <dd class="text-right text-text-primary">{{ $slot }}</dd>
</div>
