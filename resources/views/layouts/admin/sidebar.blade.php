<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-bg-main">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-border bg-surface">
            <flux:sidebar.header>
                <flux:heading class="font-semibold">
                    {{ __('Admin Panel') }}
                </flux:heading>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Principal')" class="grid">
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
