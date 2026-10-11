@php
    $routeName = request()->route()?->getName() ?? '';

    $existingDetailBreadcrumbs = [
        'prestamos.investigador.solicitud.*',
        'prestamos.investigador.prestamo.*',
        'prestamos.investigador.deposito.detalle',
        'prestamos.curador.solicitud.*',
        'prestamos.curador.prestamo.*',
        'prestamos.curador.deposito.*',
        'prestamos.curador.acta.*',
        'prestamos.receptor.deposito.*',
    ];

    $items = match (true) {
        request()->routeIs('admin.centro') => [['Centro de administración', null]],
        request()->routeIs('admin.textos-portal') => [['Divulgación', route('divulgacion.index')], ['Textos del portal', null]],
        request()->routeIs('admin.usuarios') => [
            ['Centro de administración', route('admin.centro')],
            ['Usuarios y perfiles', null],
        ],
        request()->routeIs('admin.configuracion') => [
            ['Centro de administración', route('admin.centro')],
            ['Configuración del sistema', null],
        ],
        request()->routeIs('prestamos.curador.panel') => [['Gestión de préstamos', null]],
        request()->routeIs('prestamos.curador.solicitudes') => [['Gestión de préstamos', route('prestamos.curador.panel')], ['Solicitudes', null]],
        request()->routeIs('prestamos.curador.actas') => [['Gestión de préstamos', route('prestamos.curador.panel')], ['Actas', null]],
        request()->routeIs('prestamos.curador.prestamos') => [['Gestión de préstamos', route('prestamos.curador.panel')], ['Préstamos', null]],
        request()->routeIs('prestamos.curador.configuracion') => [['Gestión de préstamos', route('prestamos.curador.panel')], ['Configuración', null]],
        request()->routeIs('prestamos.curador.depositos') => [['Gestión de depósitos', null], ['Recepciones', null]],
        request()->routeIs('prestamos.receptor.depositos') => [['Recepción EPN', null], ['Lotes por recibir', null]],
        request()->routeIs('prestamos.investigador.mis-solicitudes') => [['Préstamos', null], ['Mis solicitudes', null]],
        request()->routeIs('prestamos.investigador.mis-actas') => [['Préstamos', null], ['Mis actas', null]],
        request()->routeIs('prestamos.investigador.mis-prestamos') => [['Préstamos', null], ['Mis préstamos', null]],
        request()->routeIs('prestamos.investigador.mis-depositos', 'depositos.mis-solicitudes') => [['Depósitos', null], ['Mis depósitos', null]],
        request()->routeIs('roles.activar') => [['Configuración de cuenta', null], ['Activar rol', null]],
        request()->routeIs('profile.edit', 'security.edit', 'appearance.edit') => [['Configuración de cuenta', null]],
        request()->routeIs('inventario.*') => [['Inventario', null], [str($routeName)->afterLast('.')->headline()->toString(), null]],
        request()->routeIs('divulgacion.*') => [['Divulgación', null], [str($routeName)->afterLast('.')->headline()->toString(), null]],
        default => $routeName !== ''
            ? [[str($routeName)->afterLast('.')->replace('-', ' ')->headline()->toString(), null]]
            : [],
    };

    $hasLocalBreadcrumbs = request()->routeIs(...$existingDetailBreadcrumbs);
@endphp

@if (! $hasLocalBreadcrumbs && $routeName !== 'dashboard' && count($items) > 0)
    <nav class="hub-global-breadcrumb" aria-label="Ruta de navegación">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item wire:navigate href="{{ route('dashboard') }}">Inicio</flux:breadcrumbs.item>
            @foreach ($items as [$label, $href])
                @if ($href)
                    <flux:breadcrumbs.item wire:navigate :href="$href">{{ $label }}</flux:breadcrumbs.item>
                @else
                    <flux:breadcrumbs.item>{{ $label }}</flux:breadcrumbs.item>
                @endif
            @endforeach
        </flux:breadcrumbs>
    </nav>
@endif
