<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-bg-main">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-border bg-surface">

            {{-- Brand header --}}
            <flux:sidebar.header class="border-b border-border px-4 py-3">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-navy shadow-sm">
                        <x-app-logo-icon class="size-5 fill-current text-white" />
                    </span>
                    <div class="flex flex-col leading-tight">
                        <span class="font-display text-sm font-bold text-blue-navy">Hub Digital</span>
                        <span class="text-[10px] font-medium uppercase tracking-wider text-text-secondary">Colección Entomológica</span>
                    </div>
                </a>
                <flux:sidebar.collapse class="lg:hidden ml-auto text-text-secondary" />
            </flux:sidebar.header>

            {{-- Navigation --}}
            <flux:sidebar.nav class="mt-2">
                <flux:sidebar.group heading="Principal" class="grid">
                    <flux:sidebar.item
                        icon="home"
                        :href="route('dashboard')"
                        :current="request()->routeIs('dashboard')"
                        wire:navigate
                    >
                        Dashboard
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @auth
                    @if(auth()->user()->rol === 'PRESTAMISTA')
                        <flux:sidebar.group heading="Préstamos" class="grid">
                            <flux:sidebar.item icon="document-text" :href="route('dashboard')" wire:navigate>
                                Mis Solicitudes
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    @elseif(auth()->user()->rol === 'DEPOSITANTE')
                        <flux:sidebar.group heading="Depósitos" class="grid">
                            <flux:sidebar.item icon="archive-box" :href="route('dashboard')" wire:navigate>
                                Mis Depósitos
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    @elseif(auth()->user()->rol === 'CURADOR')
                        <flux:sidebar.group heading="Gestión" class="grid">
                            <flux:sidebar.item icon="inbox" :href="route('dashboard')" wire:navigate>
                                Solicitudes
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="squares-2x2" :href="route('dashboard')" wire:navigate>
                                Inventario
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    @endif
                @endauth
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        {{-- Mobile top bar --}}
        <flux:header class="lg:hidden border-b border-blue-navy bg-blue-navy">
            <flux:sidebar.toggle class="text-white/80 hover:text-white" icon="bars-2" inset="left" />

            <div class="flex items-center gap-2 mx-auto">
                <span class="flex h-6 w-6 items-center justify-center rounded bg-white/20">
                    <x-app-logo-icon class="size-4 fill-current text-white" />
                </span>
                <span class="font-display text-sm font-bold text-white">Hub Digital</span>
            </div>

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />
                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate text-text-secondary">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            Configuración
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                        >
                            Cerrar sesión
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
