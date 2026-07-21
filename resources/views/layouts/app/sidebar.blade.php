<!DOCTYPE html>
@php use App\Enums\RolUsuario; @endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-bg-main">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-border bg-surface">

            {{-- Brand header --}}
            <flux:sidebar.header class="border-b border-border px-4 pt-3 pb-5">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-navy shadow-sm">
                        <x-app-logo-icon class="size-5 fill-current text-white" />
                    </span>
                    <div class="flex flex-col leading-tight">
                        <span class="font-display text-sm font-bold text-blue-navy">Hub Digital</span>
                        <span class="text-[10px] font-medium uppercase tracking-wide text-text-secondary">Colección Entomológica</span>
                    </div>
                </a>
                <div class="ml-auto flex items-center text-text-secondary">
                    <div class="hidden lg:block">
                        <livewire:campana-notificaciones />
                    </div>
                    <flux:sidebar.collapse class="lg:hidden text-text-secondary" />
                </div>
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
                    @php
                        $rolActivo = auth()->user()->rolActivo();
                    @endphp
                    @if($rolActivo === RolUsuario::PRESTAMISTA)
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
                    @elseif($rolActivo === RolUsuario::DEPOSITANTE)
                        <flux:sidebar.group heading="Depósitos" class="grid">
                            <flux:sidebar.item icon="archive-box" :href="route('prestamos.investigador.mis-depositos')" :current="request()->routeIs('prestamos.investigador.mis-depositos')" wire:navigate>
                                Mis depósitos
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="plus-circle" :href="route('prestamos.investigador.deposito.crear')" :current="request()->routeIs('prestamos.investigador.deposito.crear')" wire:navigate>
                                Nueva solicitud
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    @elseif($rolActivo === RolUsuario::CURADOR)
                        <flux:sidebar.group
                            heading="Gestión de préstamos"
                            class="grid"
                            expandable
                            :expanded="request()->routeIs('prestamos.curador.solicitudes', 'prestamos.curador.solicitud.*', 'prestamos.curador.actas', 'prestamos.curador.acta.*', 'prestamos.curador.prestamos', 'prestamos.curador.prestamo.*', 'prestamos.curador.configuracion')"
                        >
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
                            <flux:sidebar.item
                                icon="shield-check"
                                :href="route('prestamos.curador.certificado')"
                                :current="request()->routeIs('prestamos.curador.certificado')"
                                wire:navigate
                            >
                                Certificado de firma
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                        <flux:sidebar.group
                            heading="Gestión de depósitos"
                            class="grid"
                            expandable
                            :expanded="request()->routeIs('prestamos.curador.depositos', 'prestamos.curador.deposito.*')"
                        >
                            <flux:sidebar.item
                                icon="inbox-arrow-down"
                                :href="route('prestamos.curador.depositos')"
                                :current="request()->routeIs('prestamos.curador.depositos', 'prestamos.curador.deposito.*')"
                                wire:navigate
                            >
                                Recepciones
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                        <flux:sidebar.group
                            heading="Catálogo"
                            class="grid"
                            expandable
                            :expanded="request()->routeIs('inventario.taxonomia.especimenes', 'inventario.taxonomia.etiquetas', 'inventario.taxonomia.taxones', 'inventario.taxonomia.localidades', 'inventario.taxonomia.entidades-depositantes')"
                        >
                            <flux:sidebar.item
                                icon="magnifying-glass"
                                :href="route('inventario.taxonomia.especimenes')"
                                :current="request()->routeIs('inventario.taxonomia.especimenes') && !request()->routeIs('inventario.taxonomia.especimenes.duplicados')"
                                wire:navigate
                            >
                                Especímenes
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="qr-code"
                                :href="route('inventario.taxonomia.etiquetas')"
                                :current="request()->routeIs('inventario.taxonomia.etiquetas')"
                                wire:navigate
                            >
                                Etiquetado QR
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
                                icon="map-pin"
                                :href="route('inventario.taxonomia.localidades')"
                                :current="request()->routeIs('inventario.taxonomia.localidades') && !request()->routeIs('inventario.taxonomia.localidades.revision')"
                                wire:navigate
                            >
                                Localidades
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="building-library"
                                :href="route('inventario.taxonomia.entidades-depositantes')"
                                :current="request()->routeIs('inventario.taxonomia.entidades-depositantes')"
                                wire:navigate
                            >
                                Instituciones depositantes
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                        <flux:sidebar.group
                            heading="Control de calidad"
                            class="grid"
                            expandable
                            :expanded="request()->routeIs('inventario.taxonomia.revision', 'inventario.taxonomia.especimenes.duplicados', 'inventario.taxonomia.fechas.revision', 'inventario.taxonomia.taxones.revision', 'inventario.taxonomia.localidades.revision', 'inventario.taxonomia.muestras')"
                        >
                            <flux:sidebar.item
                                icon="clipboard-document-check"
                                :href="route('inventario.taxonomia.revision')"
                                :current="request()->routeIs('inventario.taxonomia.revision')"
                                wire:navigate
                            >
                                Centro de revisión
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="document-duplicate"
                                :href="route('inventario.taxonomia.especimenes.duplicados')"
                                :current="request()->routeIs('inventario.taxonomia.especimenes.duplicados')"
                                wire:navigate
                            >
                                Especímenes duplicados
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="calendar-days"
                                :href="route('inventario.taxonomia.fechas.revision')"
                                :current="request()->routeIs('inventario.taxonomia.fechas.revision')"
                                wire:navigate
                            >
                                Fechas por normalizar
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="exclamation-triangle"
                                :href="route('inventario.taxonomia.taxones.revision')"
                                :current="request()->routeIs('inventario.taxonomia.taxones.revision')"
                                wire:navigate
                            >
                                Taxones por confirmar
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="exclamation-triangle"
                                :href="route('inventario.taxonomia.localidades.revision')"
                                :current="request()->routeIs('inventario.taxonomia.localidades.revision')"
                                wire:navigate
                            >
                                Localidades por confirmar
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="rectangle-stack"
                                :href="route('inventario.taxonomia.muestras')"
                                :current="request()->routeIs('inventario.taxonomia.muestras')"
                                wire:navigate
                            >
                                Muestras de colecta
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                        <flux:sidebar.group
                            heading="Configuración del catálogo"
                            class="grid"
                            expandable
                            :expanded="request()->routeIs('inventario.taxonomia.dataset.config', 'inventario.taxonomia.columnas.config')"
                        >
                            <flux:sidebar.item
                                icon="globe-alt"
                                :href="route('inventario.taxonomia.dataset.config')"
                                :current="request()->routeIs('inventario.taxonomia.dataset.config')"
                                wire:navigate
                            >
                                Publicación GBIF
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="swatch"
                                :href="route('inventario.taxonomia.columnas.config')"
                                :current="request()->routeIs('inventario.taxonomia.columnas.config')"
                                wire:navigate
                            >
                                Columnas de la tabla
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                        <flux:sidebar.group
                            heading="Seguimiento físico"
                            class="grid"
                            expandable
                            :expanded="request()->routeIs('inventario.dashboard', 'inventario.mapa', 'inventario.gabinetes*', 'inventario.cajas', 'inventario.unit-trays', 'inventario.alertas', 'inventario.orden-familias', 'inventario.horario', 'inventario.visitantes')"
                        >
                            <flux:sidebar.item
                                icon="chart-bar"
                                :href="route('inventario.dashboard')"
                                :current="request()->routeIs('inventario.dashboard')"
                                wire:navigate
                            >
                                Monitoreo
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="map"
                                :href="route('inventario.mapa')"
                                :current="request()->routeIs('inventario.mapa')"
                                wire:navigate
                            >
                                Mapa de la colección
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
                                icon="arrows-right-left"
                                :href="route('inventario.trazabilidad')"
                                :current="request()->routeIs('inventario.trazabilidad')"
                                wire:navigate
                            >
                                Trazabilidad
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
                            <flux:sidebar.item
                                icon="qr-code"
                                :href="route('inventario.visitantes')"
                                :current="request()->routeIs('inventario.visitantes')"
                                wire:navigate
                            >
                                Acceso de visitantes
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                        <flux:sidebar.group
                            heading="Divulgación"
                            class="grid"
                            expandable
                            :expanded="request()->routeIs('divulgacion.*')"
                        >
                            <flux:sidebar.item
                                icon="table-cells"
                                :href="route('divulgacion.index')"
                                :current="request()->routeIs('divulgacion.index')"
                                wire:navigate
                            >
                                Catálogo divulgado
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="cloud-arrow-up"
                                :href="route('divulgacion.sincronizar')"
                                :current="request()->routeIs('divulgacion.sincronizar')"
                                wire:navigate
                            >
                                Divulgar espécimenes
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="photo"
                                :href="route('divulgacion.imagenes')"
                                :current="request()->routeIs('divulgacion.imagenes')"
                                wire:navigate
                            >
                                Imágenes
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

            <div class="flex items-center gap-1 text-white">
                <livewire:campana-notificaciones />
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
                                @php
                                    $rolMovil = auth()->user()->rolActivo();
                                    [$badgeMovil, $iconoMovil] = match ($rolMovil) {
                                        RolUsuario::DEPOSITANTE => ['bg-bio-green/10 text-bio-green', 'archive-box'],
                                        RolUsuario::PRESTAMISTA => ['bg-science-blue/10 text-science-blue', 'document-text'],
                                        RolUsuario::CURADOR => ['bg-blue-navy/10 text-blue-navy', 'shield-check'],
                                    };
                                @endphp
                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate text-text-secondary">{{ auth()->user()->email }}</flux:text>
                                    <span class="mt-1 inline-flex items-center gap-1 self-start rounded-full {{ $badgeMovil }} px-2 py-0.5 text-xs font-medium">
                                        <flux:icon :name="$iconoMovil" class="size-3" />
                                        {{ $rolMovil->etiqueta() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <livewire:selector-rol-activo />

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

        <x-banner-activar-rol />

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
