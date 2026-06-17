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

            {{-- Mismo menú de perfil que el curador (flux:sidebar.profile + dropdown), pero con los
                 datos del visitante y una única acción: salir. No hay entidad User ni perfil editable. --}}
            @php
                $nombreVisitante = session('visitante_nombre', 'Visitante');
                $inicialesVisitante = collect(explode(' ', (string) $nombreVisitante))
                    ->filter()
                    ->take(2)
                    ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                    ->implode('') ?: 'V';
            @endphp
            <flux:dropdown position="bottom" align="start" class="hidden lg:block">
                <flux:sidebar.profile
                    :name="$nombreVisitante"
                    :initials="$inicialesVisitante"
                    icon:trailing="chevrons-up-down"
                />

                <flux:menu>
                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                        <flux:avatar
                            :name="$nombreVisitante"
                            :initials="$inicialesVisitante"
                        />
                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <flux:heading class="truncate">{{ $nombreVisitante }}</flux:heading>
                            <flux:text class="truncate text-text-secondary">Acceso de visitante</flux:text>
                        </div>
                    </div>
                    <flux:menu.separator />
                    <flux:menu.radio.group>
                        <form method="POST" action="{{ route('inventario.visitante.salir') }}" class="w-full">
                            @csrf
                            <flux:menu.item
                                as="button"
                                type="submit"
                                icon="arrow-right-start-on-rectangle"
                                class="w-full cursor-pointer"
                            >
                                Salir
                            </flux:menu.item>
                        </form>
                    </flux:menu.radio.group>
                </flux:menu>
            </flux:dropdown>
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

        {{-- flux:main establece el layout horizontal junto al sidebar: sin él, el sidebar se
             apila verticalmente y se sobrepone al mapa. Es exactamente el wrapper de layouts.app. --}}
        <flux:main>
            {{ $slot }}
        </flux:main>

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
