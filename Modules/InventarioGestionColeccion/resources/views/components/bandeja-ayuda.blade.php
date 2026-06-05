@props([
    'titulo' => 'Cómo usar esta pantalla',
    'storageKey' => null,
])

@php
    $key = $storageKey ?? 'bandeja-ayuda-'.\Illuminate\Support\Str::slug($titulo);
@endphp

<div x-data="{
        abierto: localStorage.getItem('{{ $key }}') !== 'cerrado',
        toggle() {
            this.abierto = ! this.abierto;
            localStorage.setItem('{{ $key }}', this.abierto ? 'abierto' : 'cerrado');
        }
     }"
     class="rounded-lg border border-info/30 bg-info/5 overflow-hidden">
    <button type="button" @click="toggle()"
            class="w-full px-4 py-3 flex items-center justify-between gap-2 text-left hover:bg-info/10 transition-colors">
        <span class="inline-flex items-center gap-2 text-sm font-medium text-info">
            <flux:icon name="information-circle" class="size-4 shrink-0" />
            {{ $titulo }}
        </span>
        <flux:icon name="chevron-down" class="size-4 text-info shrink-0 transition-transform" x-bind:class="abierto ? 'rotate-180' : ''" />
    </button>
    <div x-show="abierto" x-cloak x-transition class="px-4 py-3 border-t border-info/20 text-sm text-text-primary space-y-2">
        {{ $slot }}
    </div>
</div>
