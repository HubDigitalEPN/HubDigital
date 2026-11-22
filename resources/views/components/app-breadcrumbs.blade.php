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
        request()->routeIs('admin.catalogos') => [['Tablas de catálogos', null]],
        request()->routeIs('admin.matrices') => [['Matrices', null]],
        request()->routeIs('admin.botones') => [['Botones y permisos', null]],
        request()->routeIs('admin.menu-lateral') => [['Menú lateral', null]],
        request()->routeIs('admin.contenidos') => [['Contenidos y textos', null]],
        request()->routeIs('admin.usuarios') => [
            ['Usuarios y perfiles', null],
        ],
        request()->routeIs('admin.configuracion') => [
            ['Correo y descargas', null],
        ],
        request()->routeIs('prestamos.curador.panel') => [['Gestión de préstamos', null]],
        request()->routeIs('prestamos.curador.solicitudes') => [['Gestión de préstamos', route('prestamos.curador.panel')], ['Solicitudes', null]],
        request()->routeIs('prestamos.curador.actas') => [['Gestión de préstamos', route('prestamos.curador.panel')], ['Actas', null]],
        request()->routeIs('prestamos.curador.prestamos') => [['Gestión de préstamos', route('prestamos.curador.panel')], ['Préstamos', null]],
        request()->routeIs('prestamos.curador.configuracion') => [['Gestión de préstamos', route('prestamos.curador.panel')], ['Recordatorios de préstamos', null]],
        request()->routeIs('prestamos.curador.depositos') => [['Gestión de depósitos', null], ['Revisión documental y actas', null]],
        request()->routeIs('prestamos.receptor.depositos') => [['Gestión de depósitos', null], ['Recepción física', null]],
        request()->routeIs('prestamos.investigador.mis-solicitudes') => [['Préstamos', null], ['Mis solicitudes', null]],
        request()->routeIs('prestamos.investigador.mis-actas') => [['Préstamos', null], ['Mis actas', null]],
        request()->routeIs('prestamos.investigador.mis-prestamos') => [['Préstamos', null], ['Mis préstamos', null]],
        request()->routeIs('prestamos.investigador.mis-depositos', 'depositos.mis-solicitudes') => [['Depósitos', null], ['Mis depósitos', null]],
        request()->routeIs('roles.activar') => [['Configuración de cuenta', null], ['Activar rol', null]],
        request()->routeIs('profile.edit', 'security.edit', 'appearance.edit') => [['Configuración de cuenta', null]],
        request()->routeIs('inventario.taxonomia.especimenes') => [['Inventario', null], ['Creación de especímenes', null]],
        request()->routeIs('inventario.*') => [['Inventario', null], [str($routeName)->afterLast('.')->headline()->toString(), null]],
        request()->routeIs('divulgacion.index') => [['Divulgación', null], ['Publicación del catálogo', null]],
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
