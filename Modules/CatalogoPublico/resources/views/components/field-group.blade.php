@props(['titulo'])

<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    <p class="text-xs font-medium text-text-secondary uppercase tracking-wide px-3 pt-2">{{ $titulo }}</p>
    {{ $slot }}
</div>
