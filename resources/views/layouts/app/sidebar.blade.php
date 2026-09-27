<!DOCTYPE html>
@php use App\Enums\RolUsuario; @endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <script>
            try {
                if (localStorage.getItem('flux-sidebar-collapsed-desktop') === null) {
                    localStorage.setItem('flux-sidebar-collapsed-desktop', 'true');
                }
            } catch (_) {}
        </script>
    </head>
    <body class="min-h-screen bg-bg-main">
        <x-estado-conectividad />
        <flux:sidebar sticky :collapsible="true" class="hub-app-sidebar border-e border-border bg-blue-navy">

            <div class="hub-sidebar-collapse-row">
                <a href="{{ route('dashboard') }}" wire:navigate class="hub-sidebar-logo" aria-label="Hub Digital, ir al dashboard" title="Hub Digital">
                    <x-app-logo-icon class="size-6 fill-current text-white" />
                </a>
                <span class="hub-sidebar-collapse-label">Menú</span>
                <flux:sidebar.collapse tooltip="Contraer o ampliar menú" class="hub-sidebar-collapse-control" />
            </div>

            {{-- Navigation --}}
            <flux:sidebar.nav class="hub-sidebar-navigation mt-0 min-h-0 flex-1 overflow-y-auto pb-3 pt-4">

                    <flux:sidebar.item
                        icon="home"
                        aria-label="Dashboard"
                        :href="route('dashboard')"
                        :current="request()->routeIs('dashboard')"
                        wire:navigate
                    >
                        Dashboard
                    </flux:sidebar.item>
                @auth
                    @php
                        $rolActivo = auth()->user()->rolActivo();
                    @endphp
                    @if($rolActivo === RolUsuario::PRESTAMISTA)
                        <flux:sidebar.group heading="Préstamos" icon="document-text" class="grid" expandable :expanded="request()->routeIs('prestamos.investigador.*')">
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
                        <flux:sidebar.group heading="Depósitos" icon="archive-box" class="grid" expandable :expanded="request()->routeIs('prestamos.investigador.*')">
                            <flux:sidebar.item icon="archive-box" :href="route('prestamos.investigador.mis-depositos')" :current="request()->routeIs('prestamos.investigador.mis-depositos')" wire:navigate>
                                Mis depósitos
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="plus-circle" :href="route('prestamos.investigador.deposito.crear')" :current="request()->routeIs('prestamos.investigador.deposito.crear')" wire:navigate>
                                Nueva solicitud
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    @elseif($rolActivo === RolUsuario::RECEPTOR)
                        <flux:sidebar.group heading="Recepción EPN" icon="clipboard-document-check" class="grid" expandable :expanded="request()->routeIs('prestamos.receptor.*')">
                            <flux:sidebar.item
                                icon="clipboard-document-check"
                                :href="route('prestamos.receptor.depositos')"
                                :current="request()->routeIs('prestamos.receptor.*')"
                                wire:navigate
                            >
                                Lotes por recibir
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    @elseif(in_array($rolActivo, [RolUsuario::CURADOR, RolUsuario::ADMIN], true))
                        <flux:sidebar.group
                            heading="Gestión de préstamos" icon="document-text"
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
                        </flux:sidebar.group>
                        <flux:sidebar.group
                            heading="Gestión de depósitos" icon="archive-box"
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
                            <flux:sidebar.item
                                icon="building-library"
                                :href="route('admin.instituciones')"
                                :current="request()->routeIs('admin.instituciones')"
                                wire:navigate
                            >
                                Instituciones
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="beaker"
                                :href="route('admin.grupos-animales')"
                                :current="request()->routeIs('admin.grupos-animales')"
                                wire:navigate
                            >
                                Grupos animales
                            </flux:sidebar.item>
                            <flux:sidebar.item
                                icon="shield-check"
                                :href="route('admin.fuentes-revocacion')"
                                :current="request()->routeIs('admin.fuentes-revocacion')"
                                wire:navigate
                            >
                                Revocación de firmas
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                        <flux:sidebar.group
                            heading="Catálogo" icon="rectangle-stack"
                            class="grid"
                            expandable
                            :expanded="request()->routeIs('inventario.taxonomia.especimenes', 'inventario.taxonomia.importar', 'inventario.taxonomia.etiquetas', 'inventario.taxonomia.taxones', 'inventario.taxonomia.localidades', 'inventario.taxonomia.entidades-depositantes')"
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
                                icon="arrow-up-tray"
                                :href="route('inventario.taxonomia.importar')"
                                :current="request()->routeIs('inventario.taxonomia.importar')"
                                wire:navigate
                            >
                                Importar catálogo
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
                            heading="Control de calidad" icon="shield-check"
                            class="grid"
                            expandable
                            :expanded="request()->routeIs('inventario.taxonomia.revision', 'inventario.taxonomia.localidades.revision', 'inventario.taxonomia.especimenes.duplicados', 'inventario.taxonomia.fechas.revision', 'inventario.taxonomia.muestras')"
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
                                icon="map-pin"
                                :href="route('inventario.taxonomia.localidades.revision')"
                                :current="request()->routeIs('inventario.taxonomia.localidades.revision')"
                                wire:navigate
                            >
                                Localidades por confirmar
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
                                icon="rectangle-stack"
                                :href="route('inventario.taxonomia.muestras')"
                                :current="request()->routeIs('inventario.taxonomia.muestras')"
                                wire:navigate
                            >
                                Muestras de colecta
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                        <flux:sidebar.group
                            heading="Configuración del catálogo" icon="adjustments-horizontal"
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
                            heading="Seguimiento físico" icon="map-pin"
                            class="grid"
                            expandable
                            :expanded="request()->routeIs('inventario.dashboard', 'inventario.mapa', 'inventario.gabinetes*', 'inventario.cajas', 'inventario.unit-trays', 'inventario.trazabilidad', 'inventario.alertas', 'inventario.orden-familias', 'inventario.horario', 'inventario.visitantes')"
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
                            heading="Divulgación" icon="megaphone"
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
                            <flux:sidebar.item
                                icon="chat-bubble-left-right"
                                :href="route('divulgacion.asistente')"
                                :current="request()->routeIs('divulgacion.asistente')"
                                wire:navigate
                            >
                                Asistente del portal
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                        @if($rolActivo === RolUsuario::ADMIN)
                            <flux:sidebar.group heading="Administración" icon="cog-6-tooth" expandable :expanded="request()->routeIs('admin.*')" class="grid">
                                <flux:sidebar.item
                                    icon="squares-2x2"
                                    :href="route('admin.centro')"
                                    :current="request()->routeIs('admin.centro')"
                                    wire:navigate
                                >
                                    Centro de administración
                                </flux:sidebar.item>
                                <flux:sidebar.item
                                    icon="users"
                                    :href="route('admin.usuarios')"
                                    :current="request()->routeIs('admin.usuarios')"
                                    wire:navigate
                                >
                                    Usuarios y perfiles
                                </flux:sidebar.item>
                                <flux:sidebar.item
                                    icon="cog-6-tooth"
                                    :href="route('admin.configuracion')"
                                    :current="request()->routeIs('admin.configuracion')"
                                    wire:navigate
                                >
                                    Configuración del sistema
                                </flux:sidebar.item>
                                <flux:sidebar.item icon="hashtag" :href="route('admin.siglas-expedientes')" :current="request()->routeIs('admin.siglas-expedientes')" wire:navigate>
                                    Siglas de expedientes
                                </flux:sidebar.item>
                                <flux:sidebar.item
                                    icon="pencil-square"
                                    :href="route('admin.textos-depositos')"
                                    :current="request()->routeIs('admin.textos-depositos')"
                                    wire:navigate
                                >
                                    Textos de depósitos
                                </flux:sidebar.item>
                            </flux:sidebar.group>
                        @endif
                    @endif
                @endauth
            </flux:sidebar.nav>

            <div class="hub-sidebar-utilities z-10 -mx-4 -mb-4 shrink-0 border-t border-white/15 bg-blue-navy pt-2" style="box-shadow: 0 16px 0 0 var(--color-blue-navy);">
                <flux:modal.trigger name="account-settings">
                <a
                    href="#"
                    aria-label="Configuración"
                    title="Configuración"
                    x-on:click.prevent
                    class="hub-sidebar-utility mx-3 flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                >
                    <flux:icon name="cog-6-tooth" class="size-5 shrink-0" />
                    <span>Configuración</span>
                </a>
                </flux:modal.trigger>

                <form method="POST" action="{{ route('logout') }}" class="mt-2 border-t border-white/15">
                    @csrf
                    <button
                        type="submit"
                        data-test="logout-button"
                        aria-label="Cerrar sesión"
                        title="Cerrar sesión"
                        class="flex min-h-12 w-full cursor-pointer items-center gap-3 px-6 text-start text-sm font-medium text-white/65 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-white"
                    >
                        <flux:icon name="arrow-right-start-on-rectangle" class="size-5 shrink-0" />
                        <span>Cerrar sesión</span>
                    </button>
                </form>
            </div>
        </flux:sidebar>

        {{-- Barra superior compartida por todas las pantallas internas. --}}
        <flux:header class="hub-mobile-header hub-app-topbar border-b border-blue-navy bg-blue-navy">
            <flux:sidebar.toggle class="text-white/80 hover:text-white lg:hidden" icon="bars-2" inset="left" />

            <a href="{{ route('dashboard') }}" wire:navigate class="hub-mobile-brand hub-topbar-brand">
                <span class="flex h-7 w-7 items-center justify-center rounded bg-white/15">
                    <x-app-logo-icon class="size-5 fill-current text-white" />
                </span>
                <span><strong>Hub Digital</strong><small>Museo de Historia Natural</small></span>
            </a>

            <div class="hub-mobile-actions flex items-center justify-end gap-1 text-white">
                <span class="hub-topbar-identity"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->rolActivo()->etiqueta() }}</small></span>
                <flux:dropdown position="top" align="end">
                    <flux:profile
                        :initials="auth()->user()->initials()"
                        icon-trailing="chevron-down"
                        class="text-white hover:bg-white/10"
                    />

                <flux:menu class="hub-account-menu min-w-[20rem] p-0!">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="hub-account-menu__identity flex items-center gap-2 px-3 py-3 text-start text-sm">
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
                                        RolUsuario::RECEPTOR => ['bg-amber-100 text-amber-800', 'clipboard-document-check'],
                                        RolUsuario::ADMIN => ['bg-bio-green/10 text-bio-green', 'users'],
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


                    <div class="border-y border-border px-3 py-3" x-data>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-[0.08em] text-text-secondary">Apariencia</p>
                        <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Apariencia">
                            @foreach ([['light', 'sun', 'Claro'], ['dark', 'moon', 'Oscuro'], ['system', 'computer-desktop', 'Sistema']] as [$value, $icon, $label])
                                <button type="button" role="radio" x-on:click="$flux.appearance = '{{ $value }}'" x-bind:aria-checked="$flux.appearance === '{{ $value }}'" x-bind:data-selected="$flux.appearance === '{{ $value }}'" class="hub-appearance-choice">
                                    <flux:icon :name="$icon" class="size-4" />
                                    <span>{{ $label }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:modal.trigger name="account-settings">
                        <flux:menu.item as="button" type="button" icon="cog" class="w-full cursor-pointer">
                            Configuración
                        </flux:menu.item>
                        </flux:modal.trigger>
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
                <div class="flex items-center">
                    <livewire:campana-notificaciones />
                </div>
            </div>
        </flux:header>

        {{ $slot }}

        @auth
            <x-account-settings-modal />
        @endauth

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
        <script>
            (() => {
                const breakpoint = window.matchMedia('(max-width: 1023px)');

                const protectCollapsedSidebar = () => {
                    const sidebar = document.querySelector('[data-flux-sidebar]');

                    if (! sidebar || sidebar.dataset.keyboardGuard === 'ready') {
                        return;
                    }

                    const syncInert = () => {
                        sidebar.inert = breakpoint.matches
                            && sidebar.hasAttribute('data-flux-sidebar-collapsed-mobile');
                    };

                    const observer = new MutationObserver(syncInert);
                    observer.observe(sidebar, {
                        attributes: true,
                        attributeFilter: ['data-flux-sidebar-collapsed-mobile'],
                    });

                    breakpoint.addEventListener('change', syncInert);
                    sidebar.dataset.keyboardGuard = 'ready';
                    syncInert();
                };

                const labelCollapsedGroups = () => {
                    document.querySelectorAll('[data-flux-sidebar-group-dropdown] > button').forEach((button) => {
                        const label = button.textContent.trim();
                        if (label) {
                            button.setAttribute('aria-label', label);
                            button.setAttribute('title', label);
                        }
                    });
                };

                protectCollapsedSidebar();
                labelCollapsedGroups();
                document.addEventListener('DOMContentLoaded', () => {
                    protectCollapsedSidebar();
                    labelCollapsedGroups();
                }, { once: true });
                document.addEventListener('livewire:navigated', () => {
                    protectCollapsedSidebar();
                    labelCollapsedGroups();
                });
            })();
        </script>
    </body>
</html>
