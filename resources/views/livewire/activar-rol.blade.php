<div class="p-4 sm:p-6">
    <div class="mx-auto max-w-xl">
        <div class="mb-6">
            <flux:heading size="xl" class="font-serif text-blue-navy">Activar rol de Depositante</flux:heading>
            <flux:text class="mt-1 text-text-secondary">
                Con este rol podrás depositar material biológico en la colección. Declara tu cargo e
                institución: se usan en el Acta de recepción-depósito.
            </flux:text>
        </div>

        {{-- novalidate: la validación la resuelve el servidor y se muestra con los estilos
             del sistema de diseño, igual que en el registro. Sin esto el navegador muestra
             sus propios globos nativos, ajenos al diseño y al idioma de la aplicación. --}}
        <form wire:submit="confirmar" class="rounded-lg border border-border bg-surface p-4 shadow-sm sm:p-6" novalidate>
            <div class="grid gap-4">
                <flux:field>
                    <flux:label class="font-medium text-text-primary">Cargo</flux:label>
                    <flux:input
                        wire:model="cargo"
                        placeholder="Ej. Investigador titular"
                        autocomplete="organization-title"
                    />
                    <flux:error name="cargo" />
                </flux:field>

                <flux:field>
                    <flux:label class="font-medium text-text-primary">Institución</flux:label>
                    <flux:input
                        wire:model="institucion"
                        placeholder="Ej. Escuela Politécnica Nacional"
                        autocomplete="organization"
                    />
                    <flux:error name="institucion" />
                </flux:field>
            </div>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
                <flux:button
                    variant="ghost"
                    :href="route('dashboard')"
                    wire:navigate
                    class="w-full sm:w-auto"
                >
                    Cancelar
                </flux:button>
                <flux:button
                    type="submit"
                    variant="primary"
                    icon="check-circle"
                    class="w-full sm:w-auto"
                >
                    Activar rol
                </flux:button>
            </div>
        </form>
    </div>
</div>
