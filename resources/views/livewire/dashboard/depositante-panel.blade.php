<div class="flex h-full w-full flex-1 flex-col gap-4 p-6">

    <div class="flex flex-col gap-1">
        <h1 class="font-display text-2xl font-bold text-blue-navy">Dashboard</h1>
        <p class="text-sm text-text-secondary">Bienvenido, {{ auth()->user()->name }}</p>
    </div>

    <div class="flex-1 rounded-lg border border-border bg-surface p-6 shadow-sm">
        <div class="flex h-full flex-col items-center justify-center gap-3 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-bg-main">
                <flux:icon name="squares-2x2" variant="outline" class="size-7 text-text-secondary" />
            </div>
            <p class="text-base font-medium text-text-primary">Panel en construcción</p>
            <p class="text-sm text-text-secondary">
                Las funciones del módulo estarán disponibles próximamente.
            </p>
        </div>
    </div>

</div>
