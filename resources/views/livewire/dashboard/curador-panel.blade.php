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


</div>
