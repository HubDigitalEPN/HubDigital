<div class="flex h-full w-full flex-1 flex-col gap-4 p-6">

    <div class="flex flex-col gap-1">
        <h1 class="font-display text-2xl font-bold text-blue-navy">Panel del curador</h1>
        <p class="text-sm text-text-secondary">Bienvenido, {{ auth()->user()->name }}</p>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-lg border border-border bg-surface p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-science-blue/10">
                    <flux:icon name="inbox" variant="outline" class="size-5 text-science-blue" />
                </div>
                <div>
                    <p class="text-xs text-text-secondary">Solicitudes pendientes</p>
                    <p class="text-xl font-semibold text-text-primary">{{ $statSolicitudesPendientes }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-border bg-surface p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-bio-green/10">
                    <flux:icon name="check-circle" variant="outline" class="size-5 text-bio-green" />
                </div>
                <div>
                    <p class="text-xs text-text-secondary">Aprobadas</p>
                    <p class="text-xl font-semibold text-text-primary">{{ $statAprobadas }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-border bg-surface p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/10">
                    <flux:icon name="document-check" variant="outline" class="size-5 text-warning" />
                </div>
                <div>
                    <p class="text-xs text-text-secondary">Actas por validar</p>
                    <p class="text-xl font-semibold text-text-primary">{{ $statActasPorValidar }}</p>
                </div>
            </div>
        </div>
    </div>

    <h2 class="font-display text-lg font-semibold text-blue-navy">Seguimiento físico</h2>

    <div class="grid gap-4 md:grid-cols-3">
        <a href="{{ route('inventario.alertas') }}" wire:navigate
            class="rounded-lg border border-border bg-surface p-5 shadow-sm transition hover:border-error/40 hover:shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-error/40">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-error/10">
                    <flux:icon name="exclamation-triangle" variant="outline" class="size-5 text-error" />
                </div>
                <div>
                    <p class="text-xs text-text-secondary">Alertas activas</p>
                    <p class="text-xl font-semibold text-text-primary">{{ $statAlertasActivas }}</p>
                </div>
            </div>
        </a>

        <a href="{{ route('inventario.cajas') }}" wire:navigate
            class="rounded-lg border border-border bg-surface p-5 shadow-sm transition hover:border-warning/40 hover:shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-warning/40">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/10">
                    <flux:icon name="map-pin" variant="outline" class="size-5 text-warning" />
                </div>
                <div>
                    <p class="text-xs text-text-secondary">Cajas fuera de su lugar</p>
                    <p class="text-xl font-semibold text-text-primary">{{ $statCajasFueraDeLugar }}</p>
                </div>
            </div>
        </a>

        <a href="{{ route('inventario.cajas') }}" wire:navigate
            class="rounded-lg border border-border bg-surface p-5 shadow-sm transition hover:border-science-blue/40 hover:shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-science-blue/40">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-science-blue/10">
                    <flux:icon name="archive-box" variant="outline" class="size-5 text-science-blue" />
                </div>
                <div>
                    <p class="text-xs text-text-secondary">Total de cajas</p>
                    <p class="text-xl font-semibold text-text-primary">{{ $statCajasTotal }}</p>
                </div>
            </div>
        </a>
    </div>


</div>
