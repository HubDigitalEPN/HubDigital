<div class="hub-workspace flex h-full w-full flex-1 flex-col gap-7 p-4 sm:p-6">
    <header class="flex flex-col gap-4 border-b border-border pb-6 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="hub-page-kicker">Gobierno institucional</p>
            <h1 class="mt-1 hub-page-title">Centro de administración</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-text-secondary">Coordina cuentas, ingresos a colección y módulos operativos del Laboratorio de Invertebrados.</p>
        </div>
        <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-blue-navy/25 bg-surface px-4 py-2 text-sm font-semibold text-blue-navy transition hover:bg-blue-navy/[0.04]">
            <flux:icon name="chart-bar" class="size-4" /> Ver dashboard analítico
        </a>
    </header>

    <section aria-labelledby="titulo-prioridades">
        <h2 id="titulo-prioridades" class="font-display text-xl font-semibold text-blue-navy">Prioridades operativas</h2>
        <p class="mt-1 text-sm text-text-secondary">Acciones que requieren una decisión institucional o curatorial.</p>
        <div class="mt-4 grid gap-4 xl:grid-cols-3">
            <a href="{{ route('prestamos.curador.depositos') }}" wire:navigate class="hub-panel group p-5 transition hover:border-science-blue/45 hover:shadow-md">
                <div class="flex items-start justify-between gap-3"><span class="flex size-10 items-center justify-center rounded-lg bg-amber-50 text-amber-700"><flux:icon name="inbox-arrow-down" class="size-5" /></span><span class="font-display text-3xl font-semibold text-blue-navy">{{ $ingresos['porRevisar'] }}</span></div>
                <h3 class="mt-5 font-display text-lg font-semibold text-blue-navy">Expedientes por revisar</h3>
                <p class="mt-1 text-sm leading-6 text-text-secondary">Depósitos y donaciones que esperan evaluación documental.</p>
                <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-science-blue">Gestionar ingresos <flux:icon name="arrow-right" class="size-4" /></span>
            </a>
            <a href="{{ route('prestamos.curador.depositos', ['vista' => 'actas']) }}" wire:navigate class="hub-panel group p-5 transition hover:border-science-blue/45 hover:shadow-md">
                <div class="flex items-start justify-between gap-3"><span class="flex size-10 items-center justify-center rounded-lg bg-bio-green/10 text-bio-green"><flux:icon name="pencil-square" class="size-5" /></span><span class="font-display text-3xl font-semibold text-blue-navy">{{ $ingresos['actasPendientes'] }}</span></div>
                <h3 class="mt-5 font-display text-lg font-semibold text-blue-navy">Actas por firmar</h3>
                <p class="mt-1 text-sm leading-6 text-text-secondary">Lotes recibidos y constatados que requieren acta final.</p>
                <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-science-blue">Generar actas <flux:icon name="arrow-right" class="size-4" /></span>
            </a>
            <a href="{{ route('prestamos.curador.solicitudes') }}" wire:navigate class="hub-panel group p-5 transition hover:border-science-blue/45 hover:shadow-md">
                <div class="flex items-start justify-between gap-3"><span class="flex size-10 items-center justify-center rounded-lg bg-science-blue/10 text-science-blue"><flux:icon name="document-text" class="size-5" /></span><span class="font-display text-3xl font-semibold text-blue-navy">{{ $coleccion['prestamosPorRevisar'] }}</span></div>
                <h3 class="mt-5 font-display text-lg font-semibold text-blue-navy">Préstamos por resolver</h3>
                <p class="mt-1 text-sm leading-6 text-text-secondary">Solicitudes de consulta o préstamo pendientes de curaduría.</p>
                <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-science-blue">Revisar préstamos <flux:icon name="arrow-right" class="size-4" /></span>
            </a>
        </div>
    </section>

    <section class="grid gap-5 lg:grid-cols-[0.95fr_1.05fr]" aria-label="Resumen administrativo">
        <article class="hub-panel p-5">
            <div class="flex items-center justify-between gap-4"><div><h2 class="font-display text-xl font-semibold text-blue-navy">Usuarios y perfiles</h2><p class="mt-1 text-sm text-text-secondary">Altas, roles institucionales y verificación de correo.</p></div><a href="{{ route('admin.usuarios') }}" wire:navigate class="text-sm font-semibold text-science-blue hover:underline">Administrar</a></div>
            <div class="mt-6 grid grid-cols-3 divide-x divide-border rounded-lg border border-border bg-bg-main/50">
                <div class="px-4 py-3"><p class="text-xs text-text-secondary">Total</p><p class="mt-1 font-display text-2xl font-semibold text-blue-navy">{{ $usuarios['total'] }}</p></div>
                <div class="px-4 py-3"><p class="text-xs text-text-secondary">Verificados</p><p class="mt-1 font-display text-2xl font-semibold text-bio-green">{{ $usuarios['verificados'] }}</p></div>
                <div class="px-4 py-3"><p class="text-xs text-text-secondary">Pendientes</p><p class="mt-1 font-display text-2xl font-semibold text-amber-700">{{ $usuarios['pendientes'] }}</p></div>
            </div>
            <dl class="mt-5 grid grid-cols-2 gap-x-5 gap-y-3 text-sm sm:grid-cols-3">
                @foreach (\App\Enums\RolUsuario::cases() as $rol)
                    <div class="flex items-center justify-between border-b border-border pb-2"><dt class="text-text-secondary">{{ $rol->etiqueta() }}</dt><dd class="font-semibold text-blue-navy">{{ $usuarios['porRol'][$rol->value] ?? 0 }}</dd></div>
                @endforeach
            </dl>
        </article>

        <article class="hub-panel p-5">
            <h2 class="font-display text-xl font-semibold text-blue-navy">Colección e ingresos</h2>
            <p class="mt-1 text-sm text-text-secondary">Panorama de material, taxonomía y recepción institucional.</p>
            <dl class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach ([['Especímenes', $coleccion['especimenes'], 'archive-box'], ['Taxones', $coleccion['taxones'], 'tag'], ['Lotes constatados', $ingresos['recibidos'], 'clipboard-document-check'], ['Ingresos aprobados', $ingresos['aprobados'], 'check-circle']] as [$etiqueta, $valor, $icono])
                    <div class="border-l-2 border-bio-green bg-bg-main/55 px-3 py-3"><flux:icon :name="$icono" class="size-4 text-bio-green" /><dd class="mt-2 font-display text-2xl font-semibold text-blue-navy">{{ $valor }}</dd><dt class="mt-1 text-xs leading-5 text-text-secondary">{{ $etiqueta }}</dt></div>
                @endforeach
            </dl>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('inventario.taxonomia.especimenes') }}" wire:navigate class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-semibold text-blue-navy hover:border-science-blue/45"><flux:icon name="archive-box" class="size-4" />Colección</a>
                <a href="{{ route('inventario.taxonomia.taxones') }}" wire:navigate class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-semibold text-blue-navy hover:border-science-blue/45"><flux:icon name="tag" class="size-4" />Catálogos maestros</a>
                <a href="{{ route('admin.localidades') }}" wire:navigate class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-semibold text-blue-navy hover:border-science-blue/45"><flux:icon name="map-pin" class="size-4" />Localidades de Ecuador</a>
            </div>
        </article>
    </section>

    <section class="hub-panel flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between" aria-labelledby="titulo-configuracion-sistema">
        <div>
            <h2 id="titulo-configuracion-sistema" class="font-display text-xl font-semibold text-blue-navy">Configuración del sistema</h2>
            <p class="mt-1 text-sm text-text-secondary">Correo transaccional y parámetros operativos administrables sin editar archivos del servidor.</p>
        </div>
        <a href="{{ route('admin.configuracion') }}" wire:navigate class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-blue-navy/25 bg-surface px-4 py-2 text-sm font-semibold text-blue-navy transition hover:bg-blue-navy/[0.04]">
            <flux:icon name="cog-6-tooth" class="size-4" /> Administrar configuración
        </a>
    </section>
</div>
