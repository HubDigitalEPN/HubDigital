<div class="space-y-6">

    <div>
        <flux:heading size="xl" level="1" class="font-display">Gestión de préstamos</flux:heading>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <a href="{{ route('prestamos.curador.solicitudes') }}" wire:navigate
           class="group flex items-start gap-4 rounded-lg border border-border bg-surface p-5 shadow-sm transition-all hover:border-science-blue hover:shadow-md">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-science-blue/10 transition-colors group-hover:bg-science-blue/15">
                <flux:icon name="clipboard-document-list" variant="outline" class="size-6 text-science-blue" />
            </div>
            <div>
                <p class="font-semibold text-text-primary">Solicitudes</p>

            </div>
        </a>

        <a href="{{ route('prestamos.curador.actas') }}" wire:navigate
           class="group flex items-start gap-4 rounded-lg border border-border bg-surface p-5 shadow-sm transition-all hover:border-bio-green hover:shadow-md">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-bio-green/10 transition-colors group-hover:bg-bio-green/15">
                <flux:icon name="document-text" variant="outline" class="size-6 text-bio-green" />
            </div>
            <div>
                <p class="font-semibold text-text-primary">Actas</p>

            </div>
        </a>

        <a href="{{ route('prestamos.curador.prestamos') }}" wire:navigate
           class="group flex items-start gap-4 rounded-lg border border-border bg-surface p-5 shadow-sm transition-all hover:border-warning hover:shadow-md">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-warning/10 transition-colors group-hover:bg-warning/15">
                <flux:icon name="archive-box" variant="outline" class="size-6 text-warning" />
            </div>
            <div>
                <p class="font-semibold text-text-primary">Préstamos</p>

            </div>
        </a>
    </div>

</div>
