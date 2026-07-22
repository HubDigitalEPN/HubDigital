<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-bg-main">

        <flux:header container class="border-b border-blue-navy/80 bg-blue-navy! shadow-sm">
            <flux:sidebar.toggle class="mr-2 text-white/70 hover:text-white lg:hidden" icon="bars-2" inset="left" />

            {{-- Brand --}}
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
                <span class="flex h-7 w-7 items-center justify-center rounded bg-white/15">
                    <x-app-logo-icon class="size-4 fill-current text-white" />
                </span>
                <div class="hidden flex-col leading-tight sm:flex">
                    <span class="font-display text-sm font-bold text-white">Hub Digital</span>
                    <span class="text-[9px] font-medium uppercase tracking-widest text-white/50">Laboratorio de Invertebrados · EPN</span>
                </div>
            </a>

            {{-- Nav items --}}
            <flux:navbar class="-mb-px max-lg:hidden ml-6">
                <flux:navbar.item
                    icon="home"
                    :href="route('dashboard')"
                    :current="request()->routeIs('dashboard')"
                    wire:navigate
                    class="text-white/80 hover:text-white data-[current]:text-white data-[current]:border-white"
                >
                    Dashboard
                </flux:navbar.item>

                @auth
                    @if(auth()->user()->rol === 'PRESTAMISTA')
                        <flux:navbar.item icon="document-text" :href="route('dashboard')" wire:navigate
                            class="text-white/80 hover:text-white">
                            Mis Solicitudes
                        </flux:navbar.item>
                    @elseif(auth()->user()->rol === 'CURADOR')
                        <flux:navbar.item icon="inbox" :href="route('dashboard')" wire:navigate
                            class="text-white/80 hover:text-white">
                            Solicitudes
                        </flux:navbar.item>
                    @endif
                @endauth
            </flux:navbar>

            <flux:spacer />

            {{-- Right side: online indicator + user --}}
            <div class="flex items-center gap-4">
                <div class="hidden items-center gap-1.5 text-xs text-white/60 sm:flex">
                    <span class="size-2 rounded-full bg-success"></span>
                    En línea
                </div>
                <x-desktop-user-menu />
            </div>
        </flux:header>

        {{-- Mobile sidebar --}}
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-border bg-surface">
            <flux:sidebar.header class="border-b border-border">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-navy">
                        <x-app-logo-icon class="size-4 fill-current text-white" />
                    </span>
                    <span class="font-display text-sm font-bold text-blue-navy">Hub Digital</span>
                </div>
                <flux:sidebar.collapse class="ml-auto in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav class="mt-2">
                <flux:sidebar.group heading="Principal">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        Dashboard
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @auth
                    @if(auth()->user()->rol === 'PRESTAMISTA')
                        <flux:sidebar.group heading="Préstamos">
                            <flux:sidebar.item icon="document-text" :href="route('dashboard')" wire:navigate>
                                Mis Solicitudes
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    @elseif(auth()->user()->rol === 'CURADOR')
                        <flux:sidebar.group heading="Gestión">
                            <flux:sidebar.item icon="inbox" :href="route('dashboard')" wire:navigate>
                                Solicitudes
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    @endif
                @endauth
            </flux:sidebar.nav>
        </flux:sidebar>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
