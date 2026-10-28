@use('App\Enums\RolUsuario')

@props(['context' => 'header'])

@php
    $usuarioMenu = auth()->user();
    $rolActivoMenu = $usuarioMenu->rolActivo();
    $enSidebar = $context === 'sidebar';
    $iconoRol = match ($rolActivoMenu) {
        RolUsuario::DEPOSITANTE => 'archive-box',
        RolUsuario::PRESTAMISTA => 'document-text',
        RolUsuario::CURADOR, RolUsuario::AYUDANTE_CURADOR => 'shield-check',
        RolUsuario::RECEPTOR => 'clipboard-document-check',
        RolUsuario::ADMIN => 'users',
    };
@endphp

<flux:dropdown :position="$enSidebar ? 'bottom' : 'top'" align="end">
    <button
        type="button"
        data-test="user-menu-button"
        @class([
            'flex items-center rounded-lg transition focus-visible:outline-none focus-visible:ring-2',
            'min-h-12 w-full gap-3 p-2 text-start text-white hover:bg-white/10 focus-visible:ring-white' => $enSidebar,
            'gap-2 p-1.5 text-white hover:bg-white/10 focus-visible:ring-white' => ! $enSidebar,
        ])
        aria-label="Abrir menú de usuario"
    >
        <flux:avatar :name="$usuarioMenu->name" :initials="$usuarioMenu->initials()" />
        @if ($enSidebar)
            <span class="min-w-0 flex-1 truncate text-sm font-semibold">{{ $usuarioMenu->name }}</span>
        @endif
        <flux:icon name="chevron-down" class="size-4 shrink-0 text-white/70" />
    </button>

    <flux:menu class="hub-account-menu min-w-[20rem] p-0!">
        <div class="p-4">
            <div class="flex items-start gap-3">
                <flux:avatar :name="$usuarioMenu->name" :initials="$usuarioMenu->initials()" class="size-11" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-text-primary">{{ $usuarioMenu->name }}</p>
                    <p class="truncate text-sm text-text-secondary">{{ $usuarioMenu->email }}</p>
                    <p class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-bio-green">
                        <flux:icon :name="$iconoRol" class="size-3.5" />
                        {{ $rolActivoMenu->etiqueta() }}
                    </p>
                </div>
            </div>
        </div>

        <div class="border-y border-border px-4 py-3" x-data>
            <p class="mb-2 text-xs font-semibold uppercase tracking-[0.08em] text-text-secondary">Apariencia</p>
            <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Apariencia">
                @foreach ([['light', 'sun', 'Claro'], ['dark', 'moon', 'Oscuro'], ['system', 'computer-desktop', 'Sistema']] as [$value, $icon, $label])
                    <button
                        type="button"
                        role="radio"
                        x-on:click="$flux.appearance = '{{ $value }}'"
                        x-bind:aria-checked="$flux.appearance === '{{ $value }}'"
                        x-bind:data-selected="$flux.appearance === '{{ $value }}'"
                        class="hub-appearance-choice"
                    >
                        <flux:icon :name="$icon" class="size-4" />
                        <span>{{ $label }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="p-2">
            <flux:modal.trigger name="account-settings">
                <flux:menu.item as="button" type="button" icon="cog-6-tooth" class="w-full cursor-pointer">
                    Configuración
                </flux:menu.item>
            </flux:modal.trigger>
            <form method="POST" action="{{ route('logout') }}" class="mt-1 w-full">
                @csrf
                <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                    Cerrar sesión
                </flux:menu.item>
            </form>
        </div>
    </flux:menu>
</flux:dropdown>
