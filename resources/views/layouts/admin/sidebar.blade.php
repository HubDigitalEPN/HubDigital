<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-bg-main">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-blue-navy bg-blue-navy">
            <flux:sidebar.header>
                <flux:heading class="font-semibold text-white">
                    {{ __('Admin Panel') }}
                </flux:heading>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Principal')" class="grid" heading-class="text-white/70">
                    <flux:sidebar.item
                        icon="home"
                        :href="route('admin.dashboard')"
                        :current="request()->routeIs('admin.dashboard')"
                        wire:navigate
                    >
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                {{-- Los módulos añaden sus pestañas aquí con @push('admin-nav-items') --}}
                @stack('admin-nav-items')

                <flux:sidebar.group :heading="__('Inventario IoT')" heading-class="text-white/70">
                    <flux:sidebar.item
                        icon="chart-bar"
                        :href="route('admin.inventario.dashboard')"
                        :current="request()->routeIs('admin.inventario.dashboard')"
                        wire:navigate
                    >
                        {{ __('Monitoreo') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item
                        icon="archive-box"
                        :href="route('admin.inventario.gabinetes')"
                        :current="request()->routeIs('admin.inventario.gabinetes*')"
                        wire:navigate
                    >
                        {{ __('Gabinetes') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item
                        icon="cube"
                        :href="route('admin.inventario.cajas')"
                        :current="request()->routeIs('admin.inventario.cajas')"
                        wire:navigate
                    >
                        {{ __('Cajas') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item
                        icon="bell-alert"
                        :href="route('admin.inventario.alertas')"
                        :current="request()->routeIs('admin.inventario.alertas')"
                        wire:navigate
                    >
                        {{ __('Alertas') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item
                        icon="clock"
                        :href="route('admin.inventario.horario')"
                        :current="request()->routeIs('admin.inventario.horario')"
                        wire:navigate
                    >
                        {{ __('Horario') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <flux:sidebar.item
                        icon="arrow-right-start-on-rectangle"
                        as="button"
                        type="submit"
                        class="w-full cursor-pointer"
                    >
                        {{ __('Cerrar sesión') }}
                    </flux:sidebar.item>
                </form>
            </flux:sidebar.nav>
        </flux:sidebar>

        <!-- Mobile header -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <flux:spacer />
            <flux:heading size="sm" class="text-text-secondary">Admin</flux:heading>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
