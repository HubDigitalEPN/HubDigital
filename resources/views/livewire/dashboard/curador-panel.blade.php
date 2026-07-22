<div class="flex h-full w-full flex-1 flex-col gap-6 p-4 sm:p-6">

    <div class="flex flex-col gap-1">
        <h1 class="font-display text-2xl font-bold text-blue-navy">Panel del curador</h1>
        <p class="text-sm text-text-secondary">Bienvenido, {{ auth()->user()->name }}</p>
    </div>

    {{-- ── La colección ─────────────────────────────────────────────
         Gestión de información taxonómica + Divulgación. Va primero
         porque es la magnitud real del acervo bajo custodia. --}}
    <section class="flex flex-col gap-4">
        <h2 class="font-display text-lg font-semibold text-blue-navy">La colección</h2>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
            {{-- Número protagonista. Va en la tipografía de interfaz y no en la
                 serif de los títulos: una cifra grande en serif se lee como
                 adorno. Uno solo por pantalla. --}}
            <a href="{{ route('inventario.taxonomia.especimenes') }}" wire:navigate
                class="flex flex-col justify-center rounded-lg border border-border bg-surface p-6 shadow-sm transition hover:border-science-blue/40 hover:shadow">
                <p class="text-sm text-text-secondary">Especímenes bajo custodia</p>
                <p class="mt-1 text-5xl font-semibold leading-none text-blue-navy">
                    {{ number_format($colEspecimenes, 0, ',', '.') }}
                </p>
                <p class="mt-2 text-xs text-text-secondary">
                    En {{ number_format($colLocalidades, 0, ',', '.') }} localidades registradas
                </p>
            </a>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat-tile
                    label="Taxones" :value="$colTaxones"
                    icon="rectangle-stack" tone="blue"
                    :href="route('inventario.taxonomia.taxones')" />
                <x-stat-tile
                    label="Localidades" :value="$colLocalidades"
                    icon="map" tone="blue"
                    :href="route('inventario.taxonomia.localidades')" />
                <x-stat-tile
                    label="Publicados en el portal" :value="$divPublicados"
                    icon="globe-alt" tone="green"
                    hint="Visibles para el público"
                    :href="route('divulgacion.index')" />
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-bar-chart
                titulo="Familias mejor representadas"
                :subtitulo="'Las 8 con más registros, de '.number_format($colFamilias, 0, ',', '.').' familias en la colección'"
                :filas="$graficoFamilias" />
            <x-bar-chart
                titulo="Nivel de determinación"
                subtitulo="Hasta qué rango está identificado cada espécimen"
                :filas="$graficoDeterminacion" />
        </div>
    </section>

    {{-- ── Recepción y depósitos ──────────────────────────────────── --}}
    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-blue-navy">Recepción y depósitos</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-tile
                label="Depósitos por revisar" :value="$depPorRevisar"
                icon="clipboard-document-check"
                :tone="$depPorRevisar > 0 ? 'warning' : 'blue'"
                :href="route('prestamos.curador.depositos')" />
            <x-stat-tile
                label="Solicitudes recibidas" :value="$depEnviadas"
                icon="inbox-arrow-down" tone="blue"
                :href="route('prestamos.curador.depositos')" />
            <x-stat-tile
                label="Lotes recibidos físicamente" :value="$depLotesRecibidos"
                icon="archive-box-arrow-down" tone="green"
                :href="route('prestamos.curador.depositos')" />
            <x-stat-tile
                label="Registros en matrices" :value="$depRegistrosMatriz"
                icon="table-cells" tone="blue"
                hint="Filas validadas contra Darwin Core" />
        </div>
    </section>

    {{-- ── Préstamos ──────────────────────────────────────────────── --}}
    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-blue-navy">Préstamos</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-tile
                label="Solicitudes pendientes" :value="$statSolicitudesPendientes"
                icon="inbox"
                :tone="$statSolicitudesPendientes > 0 ? 'warning' : 'blue'"
                :href="route('prestamos.curador.solicitudes')" />
            <x-stat-tile
                label="Préstamos activos" :value="$prestActivos"
                icon="arrow-path" tone="green"
                :href="route('prestamos.curador.prestamos')" />
            <x-stat-tile
                label="Préstamos vencidos" :value="$prestVencidos"
                icon="exclamation-circle"
                :tone="$prestVencidos > 0 ? 'error' : 'blue'"
                :href="route('prestamos.curador.prestamos')" />
            <x-stat-tile
                label="Actas por validar" :value="$statActasPorValidar"
                icon="document-check"
                :tone="$statActasPorValidar > 0 ? 'warning' : 'blue'"
                :href="route('prestamos.curador.actas')" />
        </div>
    </section>

    {{-- ── Seguimiento físico ─────────────────────────────────────── --}}
    <section class="flex flex-col gap-3">
        <h2 class="font-display text-lg font-semibold text-blue-navy">Seguimiento físico</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-tile
                label="Alertas activas" :value="$statAlertasActivas"
                icon="exclamation-triangle"
                :tone="$statAlertasActivas > 0 ? 'error' : 'blue'"
                :href="route('inventario.alertas')" />
            <x-stat-tile
                label="Cajas fuera de su lugar" :value="$statCajasFueraDeLugar"
                icon="map-pin"
                :tone="$statCajasFueraDeLugar > 0 ? 'warning' : 'blue'"
                :href="route('inventario.cajas')" />
            <x-stat-tile
                label="Total de cajas" :value="$statCajasTotal"
                icon="archive-box" tone="blue"
                :href="route('inventario.cajas')" />
            <x-stat-tile
                label="Movimientos registrados" :value="$fisMovimientos"
                icon="clock" tone="navy"
                hint="Trazabilidad completa"
                :href="route('inventario.trazabilidad')" />
        </div>
    </section>

</div>
