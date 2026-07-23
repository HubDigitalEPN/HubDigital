<div>
    @if ($roles->count() > 1)
        <flux:menu.separator />

        <div class="px-2 py-1.5 text-xs font-medium uppercase tracking-wide text-text-secondary">
            Operar como
        </div>

        @foreach ($roles as $rol)
            <flux:menu.item
                as="button"
                wire:click="cambiar('{{ $rol->value }}')"
                :icon="$rol === $rolActivo ? 'check-circle' : 'user-circle'"
                class="{{ $rol === $rolActivo ? 'font-semibold text-blue-navy' : '' }}"
            >
                {{ $rol->etiqueta() }}
            </flux:menu.item>
        @endforeach
    @elseif ($rolActivable)
        <flux:menu.separator />

        <flux:menu.item
            :href="route('roles.activar', strtolower($rolActivable->value))"
            icon="plus-circle"
            wire:navigate
        >
            Activar rol de {{ $rolActivable->etiqueta() }}
        </flux:menu.item>
    @endif
</div>
