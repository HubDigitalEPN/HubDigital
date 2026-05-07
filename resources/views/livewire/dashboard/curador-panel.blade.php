<div class="flex h-full w-full flex-1 flex-col gap-4 p-6">

    <div class="flex flex-col gap-1">
        <h1 class="font-display text-2xl font-bold text-blue-navy">Panel del Curador</h1>
        <p class="text-sm text-text-secondary">Bienvenido, {{ auth()->user()->name }}</p>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-lg border border-border bg-surface p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-science-blue/10">
                    <flux:icon name="inbox" variant="outline" class="size-5 text-science-blue" />
                </div>
                <div>
                    <p class="text-xs text-text-secondary">Solicitudes Pendientes</p>
                    <p class="text-xl font-semibold text-text-primary">—</p>
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
                    <p class="text-xl font-semibold text-text-primary">—</p>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-border bg-surface p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/10">
                    <flux:icon name="document-check" variant="outline" class="size-5 text-warning" />
                </div>
                <div>
                    <p class="text-xs text-text-secondary">Actas por Validar</p>
                    <p class="text-xl font-semibold text-text-primary">—</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <a href="{{ route('prestamos.curador.solicitudes') }}" wire:navigate
           class="flex items-center gap-4 rounded-lg border border-border bg-surface p-5 shadow-sm transition hover:border-science-blue">
            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-science-blue/10">
                <flux:icon name="clipboard-document-list" variant="outline" class="size-6 text-science-blue" />
            </div>
            <div>
                <p class="font-medium text-text-primary">Bandeja de Solicitudes</p>
                <p class="text-xs text-text-secondary">Revisar y aprobar solicitudes de préstamo</p>
            </div>
        </a>

        <a href="{{ route('prestamos.curador.actas') }}" wire:navigate
           class="flex items-center gap-4 rounded-lg border border-border bg-surface p-5 shadow-sm transition hover:border-bio-green">
            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-bio-green/10">
                <flux:icon name="document-text" variant="outline" class="size-6 text-bio-green" />
            </div>
            <div>
                <p class="font-medium text-text-primary">Bandeja de Actas</p>
                <p class="text-xs text-text-secondary">Validar actas de devolución</p>
            </div>
        </a>
    </div>

</div>
