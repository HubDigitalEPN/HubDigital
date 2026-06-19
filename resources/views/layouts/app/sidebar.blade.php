<!DOCTYPE html>
@php use App\Enums\RolUsuario; @endphp
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
                    @if(auth()->user()->rol === RolUsuario::PRESTAMISTA)
                        <flux:sidebar.group heading="Préstamos" class="grid">
                            <flux:sidebar.item
                                icon="document-text"
                                :href="route('prestamos.investigador.mis-solicitudes')"
                                :current="request()->routeIs('prestamos.investigador.mis-solicitudes', 'prestamos.investigador.solicitud.*')"
                                wire:navigate
                            >
                                Mis solicitudes
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="clipboard-document"
                                :href="route('prestamos.investigador.mis-actas')"
                                :current="request()->routeIs('prestamos.investigador.mis-actas')"
                                wire:navigate
                            >
                                Mis actas
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="archive-box"
                                :href="route('prestamos.investigador.mis-prestamos')"
                                :current="request()->routeIs('prestamos.investigador.mis-prestamos', 'prestamos.investigador.prestamo.*')"
                                wire:navigate
                            >
                                Mis préstamos
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    @elseif(auth()->user()->rol === RolUsuario::DEPOSITANTE)
                        <flux:sidebar.group heading="Depósitos" class="grid">
                            <flux:sidebar.item icon="archive-box" :href="route('prestamos.investigador.mis-depositos')" :current="request()->routeIs('prestamos.investigador.mis-depositos')" wire:navigate>
                                Mis depósitos
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="plus-circle" :href="route('prestamos.investigador.deposito.crear')" :current="request()->routeIs('prestamos.investigador.deposito.crear')" wire:navigate>
                                Nueva solicitud
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    @elseif(auth()->user()->rol === RolUsuario::CURADOR)
                        <flux:sidebar.group heading="Gestión de préstamos" class="grid">
                            <flux:sidebar.item
                                icon="document-text"
                                :href="route('prestamos.curador.solicitudes')"
                                :current="request()->routeIs('prestamos.curador.solicitudes', 'prestamos.curador.solicitud.*')"
                                wire:navigate
                            >
                                Solicitudes
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="clipboard-document"
                                :href="route('prestamos.curador.actas')"
                                :current="request()->routeIs('prestamos.curador.actas', 'prestamos.curador.acta.*')"
                                wire:navigate
                            >
                                Actas
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="archive-box"
                                :href="route('prestamos.curador.prestamos')"
                                :current="request()->routeIs('prestamos.curador.prestamos', 'prestamos.curador.prestamo.*')"
                                wire:navigate
                            >
                                Préstamos
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="cog-6-tooth"
                                :href="route('prestamos.curador.configuracion')"
                                :current="request()->routeIs('prestamos.curador.configuracion')"
                                wire:navigate
                            >
                                Configuración
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                        <flux:sidebar.group heading="Inventario" class="grid">
                            <flux:sidebar.item
                                icon="clipboard-document-check"
                                :href="route('inventario.taxonomia.revision')"
                                :current="request()->routeIs('inventario.taxonomia.revision')"
                                wire:navigate
                            >
                                Centro de revisión
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="magnifying-glass"
                                :href="route('inventario.taxonomia.especimenes')"
                                :current="request()->routeIs('inventario.taxonomia.especimenes') && !request()->routeIs('inventario.taxonomia.especimenes.duplicados')"
                                wire:navigate
                            >
                                Especímenes
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="exclamation-triangle"
                                :href="route('inventario.taxonomia.especimenes.duplicados')"
                                :current="request()->routeIs('inventario.taxonomia.especimenes.duplicados')"
                                wire:navigate
                            >
                                Duplicados catalog#
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="calendar-days"
                                :href="route('inventario.taxonomia.fechas.revision')"
                                :current="request()->routeIs('inventario.taxonomia.fechas.revision')"
                                wire:navigate
                            >
                                Parseo de fechas
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="rectangle-stack"
                                :href="route('inventario.taxonomia.muestras')"
                                :current="request()->routeIs('inventario.taxonomia.muestras')"
                                wire:navigate
                            >
                                Muestras de colecta
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="tag"
                                :href="route('inventario.taxonomia.taxones')"
                                :current="request()->routeIs('inventario.taxonomia.taxones') && !request()->routeIs('inventario.taxonomia.taxones.revision')"
                                wire:navigate
                            >
                                Taxones
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="exclamation-triangle"
                                :href="route('inventario.taxonomia.taxones.revision')"
                                :current="request()->routeIs('inventario.taxonomia.taxones.revision')"
                                wire:navigate
                            >
                                Revisión taxa
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="map-pin"
                                :href="route('inventario.taxonomia.localidades')"
                                :current="request()->routeIs('inventario.taxonomia.localidades') && !request()->routeIs('inventario.taxonomia.localidades.revision')"
                                wire:navigate
                            >
                                Localidades
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="exclamation-triangle"
                                :href="route('inventario.taxonomia.localidades.revision')"
                                :current="request()->routeIs('inventario.taxonomia.localidades.revision')"
                                wire:navigate
                            >
                                Revisión localidades
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="building-library"
                                :href="route('inventario.taxonomia.entidades-depositantes')"
                                :current="request()->routeIs('inventario.taxonomia.entidades-depositantes')"
                                wire:navigate
                            >
                                Entidades depositantes
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="globe-alt"
                                :href="route('inventario.taxonomia.dataset.config')"
                                :current="request()->routeIs('inventario.taxonomia.dataset.config')"
                                wire:navigate
                            >
                                Dataset GBIF
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="swatch"
                                :href="route('inventario.taxonomia.columnas.config')"
                                :current="request()->routeIs('inventario.taxonomia.columnas.config')"
                                wire:navigate
                            >
                                Prioridad columnas
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                        <flux:sidebar.group heading="Seguimiento físico" class="grid">
                            <flux:sidebar.item
                                icon="chart-bar"
                                :href="route('inventario.dashboard')"
                                :current="request()->routeIs('inventario.dashboard')"
                                wire:navigate
                            >
                                Monitoreo
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="archive-box"
                                :href="route('inventario.gabinetes')"
                                :current="request()->routeIs('inventario.gabinetes*')"
                                wire:navigate
                            >
                                Gabinetes
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="cube"
                                :href="route('inventario.cajas')"
                                :current="request()->routeIs('inventario.cajas')"
                                wire:navigate
                            >
                                Cajas
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="squares-2x2"
                                :href="route('inventario.unit-trays')"
                                :current="request()->routeIs('inventario.unit-trays')"
                                wire:navigate
                            >
                                Unit trays
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="bell-alert"
                                :href="route('inventario.alertas')"
                                :current="request()->routeIs('inventario.alertas')"
                                wire:navigate
                            >
                                Alertas
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="bars-arrow-down"
                                :href="route('inventario.orden-familias')"
                                :current="request()->routeIs('inventario.orden-familias')"
                                wire:navigate
                            >
                                Orden de familias
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="clock"
                                :href="route('inventario.horario')"
                                :current="request()->routeIs('inventario.horario')"
                                wire:navigate
                            >
                                Horario
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                        <flux:sidebar.group heading="Divulgación" class="grid">
                            <flux:sidebar.item
                                icon="table-cells"
                                :href="route('divulgacion.index')"
                                :current="request()->routeIs('divulgacion.index')"
                                wire:navigate
                            >
                                Tabla divulgada
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="cloud-arrow-up"
                                :href="route('divulgacion.sincronizar')"
                                :current="request()->routeIs('divulgacion.sincronizar')"
                                wire:navigate
                            >
                                Sincronización
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    @endif
                @endauth
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="sticky bottom-0 z-10 -mx-4 -mb-4 border-t border-border bg-surface p-4 hidden lg:block" style="box-shadow: 0 16px 0 0 var(--color-surface);">
                <x-desktop-user-menu :name="auth()->user()->name" />
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

        {{-- Domain exception toast --}}
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
