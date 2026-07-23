@use('App\Enums\RolUsuario')

@php
    $usuarioMenu = auth()->user();
    $rolActivoMenu = $usuarioMenu->rolActivo();
    [$badgeRol, $iconoRol] = match ($rolActivoMenu) {
        RolUsuario::DEPOSITANTE => ['bg-bio-green/10 text-bio-green', 'archive-box'],
        RolUsuario::PRESTAMISTA => ['bg-science-blue/10 text-science-blue', 'document-text'],
        RolUsuario::CURADOR => ['bg-blue-navy/10 text-blue-navy', 'shield-check'],
    };
@endphp

<flux:dropdown position="top" align="start">
    <button
        type="button"
        data-test="sidebar-menu-button"
        class="flex w-full items-center gap-3 rounded-lg p-2 text-start transition hover:bg-bg-main"
    >
        <div class="relative shrink-0">
            <flux:avatar :name="$usuarioMenu->name" :initials="$usuarioMenu->initials()" />
            <span class="absolute -bottom-0.5 -end-0.5 size-2.5 rounded-full bg-success ring-2 ring-surface"></span>
        </div>
        <div class="flex min-w-0 flex-1 flex-col">
            <span class="truncate text-sm font-semibold text-text-primary">{{ $usuarioMenu->name }}</span>
            <span class="mt-1 inline-flex items-center gap-1 self-start rounded-full {{ $badgeRol }} px-2 py-0.5 text-xs font-medium">
                <flux:icon :name="$iconoRol" class="size-3" />
                {{ $rolActivoMenu->etiqueta() }}
            </span>
        </div>
        <flux:icon name="chevron-up-down" class="ms-auto size-4 shrink-0 text-text-secondary" />
    </button>

    <flux:menu>
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <flux:avatar
                :name="$usuarioMenu->name"
                :initials="$usuarioMenu->initials()"
            />
            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ $usuarioMenu->name }}</flux:heading>
                <flux:text class="truncate">{{ $usuarioMenu->email }}</flux:text>
            </div>
        </div>

        <livewire:selector-rol-activo />

        <flux:menu.separator />
        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                {{ __('Configuración') }}
            </flux:menu.item>
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer"
                    data-test="logout-button"
                >
                    {{ __('Cerrar sesión') }}
                </flux:menu.item>
            </form>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
