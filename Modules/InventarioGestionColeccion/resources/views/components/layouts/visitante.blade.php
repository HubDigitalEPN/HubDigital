<!DOCTYPE html>
<html lang="es">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-bg-main">
        {{-- El visitante ve el mismo chrome que el curador, pero el sidebar solo expone
             el mapa de la colección: ninguna otra área le es accesible. --}}
        <flux:sidebar sticky collapsible="mobile" class="border-e border-border bg-surface">

            {{-- Brand header --}}
            <flux:sidebar.header class="border-b border-border px-4 py-3">
                <a href="{{ route('inventario.visitante.mapa') }}" wire:navigate class="flex items-center gap-2.5">
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
                        icon="map"
                        :href="route('inventario.visitante.mapa')"
                        :current="request()->routeIs('inventario.visitante.mapa')"
                        wire:navigate
                    >
                        Mapa de la colección
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            {{-- En lugar del menú de usuario del admin: nombre del visitante y salida al portal. --}}
            <div class="hidden border-t border-border px-3 py-3 lg:block">
                @if(session('visitante_nombre'))
                    <div class="mb-2 flex items-center gap-2 px-1">
                        <flux:icon name="user" class="size-4 shrink-0 text-text-secondary" />
                        <span class="truncate text-sm font-medium text-text-primary">{{ session('visitante_nombre') }}</span>
                    </div>
                @endif
                <form method="POST" action="{{ route('inventario.visitante.salir') }}" class="w-full">
                    @csrf
                    <flux:button type="submit" variant="ghost" icon="arrow-right-start-on-rectangle" class="w-full justify-start">
                        Salir
                    </flux:button>
                </form>
            </div>
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

            <form method="POST" action="{{ route('inventario.visitante.salir') }}">
                @csrf
                <flux:button type="submit" variant="ghost" icon="arrow-right-start-on-rectangle" class="text-white/80 hover:text-white">
                    Salir
                </flux:button>
            </form>
        </flux:header>

        {{ $slot }}

        {{-- Domain exception toast: la búsqueda del visitante puede emitir errores de dominio. --}}
        <div
            x-data="{ show: false, message: '' }"
            x-on:domain-error.window="message = $event.detail.message; show = true; setTimeout(() => show = false, 6000)"
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-4"
            class="fixed bottom-5 right-5 z-50 flex items-start gap-3 rounded-lg border border-error/30 bg-surface px-4 py-3 shadow-lg max-w-sm"
            style="display: none"
        >
            <flux:icon name="exclamation-triangle" class="mt-0.5 size-5 shrink-0 text-error" />
            <div class="flex-1">
                <p class="text-sm font-medium text-text-primary">Operación no permitida</p>
                <p class="text-xs text-text-secondary mt-0.5" x-text="message"></p>
            </div>
            <button x-on:click="show = false" class="text-text-secondary hover:text-text-primary">
                <flux:icon name="x-mark" class="size-4" />
            </button>
        </div>

        @fluxScripts
    </body>
</html>
