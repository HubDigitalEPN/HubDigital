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
    <body @class(['min-h-screen bg-bg-main', 'hub-curator-ui' => auth()->user()?->esCurador()])>
        <x-estado-conectividad />
        <flux:sidebar sticky :collapsible="true" class="hub-app-sidebar border-e border-border bg-blue-navy">

            <div class="hub-sidebar-collapse-row">
                <a href="{{ route('dashboard') }}" wire:navigate class="hub-sidebar-logo" aria-label="Hub Digital, ir al dashboard" title="Hub Digital">
                    <x-app-logo-icon class="size-6 fill-current text-white" />
                </a>
                <span class="hub-sidebar-collapse-label">Menú</span>
                <flux:sidebar.collapse tooltip="Contraer o ampliar menú" class="hub-sidebar-collapse-control" />
            </div>

            <flux:sidebar.nav class="hub-sidebar-navigation mt-0 min-h-0 flex-1 overflow-y-auto pb-3 pt-4">
                @auth
                    @foreach(app(\App\Support\MenuLateral::class)->visiblePara(auth()->user()->rolActivo()) as $entrada)
                        @if($entrada['ruta'] !== null)
                            <flux:sidebar.item :icon="$entrada['icono']" :href="route($entrada['ruta'])" :title="$entrada['texto']"
                                :current="request()->routeIs(...$entrada['activas'])" wire:navigate>{{ $entrada['texto'] }}</flux:sidebar.item>
                        @else
                            @php
                                $activas = array_merge(...array_column($entrada['hijos'], 'activas'));
                            @endphp
                            <flux:sidebar.group :heading="$entrada['texto']" :icon="$entrada['icono']" :title="$entrada['texto']"
                                expandable :expanded="request()->routeIs(...$activas)" class="grid">
                                @foreach($entrada['hijos'] as $hijo)
                                    <flux:sidebar.item :icon="$hijo['icono']" :href="route($hijo['ruta'])" :title="$hijo['texto']"
                                        :current="request()->routeIs(...$hijo['activas'])" wire:navigate>{{ $hijo['texto'] }}</flux:sidebar.item>
                                @endforeach
                            </flux:sidebar.group>
                        @endif
                    @endforeach
                @endauth
            </flux:sidebar.nav>

            <div class="hub-sidebar-utilities z-10 -mx-4 -mb-4 shrink-0 border-t border-white/15 bg-blue-navy pt-2" style="box-shadow: 0 16px 0 0 var(--color-blue-navy);">
                <flux:modal.trigger name="account-settings">
                <a
                    href="#"
                    aria-label="Mi cuenta"
                    title="Mi cuenta"
                    x-on:click.prevent
                    class="hub-sidebar-utility mx-3 flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                >
                    <flux:icon name="cog-6-tooth" class="size-5 shrink-0" />
                    <span>Mi cuenta</span>
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
                <img src="{{ asset('images/hub-logo-v1.avif') }}" alt="Laboratorio de Invertebrados" class="h-10 w-auto max-w-20 rounded bg-white p-1 object-contain" />
                <span><strong>Plataforma de Gestión de Información</strong><small>Laboratorio de Invertebrados</small><small>Escuela Politécnica Nacional</small></span>
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
                                        RolUsuario::CURADOR, RolUsuario::AYUDANTE_CURADOR => ['bg-blue-navy/10 text-blue-navy', 'shield-check'],
                                        RolUsuario::PASANTE => ['bg-amber-100 text-amber-800', 'clipboard-document-check'],
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
                    <div class="p-3"><x-pwa-install-button /><x-pwa-notification-button /></div>

                    <flux:menu.radio.group>
                        <flux:modal.trigger name="account-settings">
                        <flux:menu.item as="button" type="button" icon="cog" class="w-full cursor-pointer">
                            Mi cuenta
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
