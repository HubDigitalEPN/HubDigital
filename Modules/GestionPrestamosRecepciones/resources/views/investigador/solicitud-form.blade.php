<div class="space-y-6">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.mis-solicitudes') }}">
            Mis Solicitudes
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>
            {{ $this->solicitudId ? 'Editar Solicitud' : 'Nueva Solicitud' }}
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <flux:heading size="xl" level="1">
        {{ $this->solicitudId ? 'Editar Solicitud de Préstamo' : 'Nueva Solicitud de Préstamo' }}
    </flux:heading>

    @if($successMessage)
        <flux:callout variant="success" icon="check-circle">{{ $successMessage }}</flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Datos Generales --}}
        <div class="lg:col-span-2 rounded-lg border border-border bg-surface shadow-sm p-6 space-y-4">
            <flux:heading size="lg" level="2">Datos Generales</flux:heading>
            <flux:separator />

            <flux:field>
                <flux:label>Título del Estudio</flux:label>
                <flux:input wire:model="tituloEstudio" placeholder="Ej. Análisis taxonómico de lepidópteros andinos" />
                <flux:error name="tituloEstudio" />
            </flux:field>

            <flux:field>
                <flux:label>Institución de Adscripción</flux:label>
                <flux:input wire:model="institucionAdscripcion" placeholder="Ej. Escuela Politécnica Nacional" />
                <flux:error name="institucionAdscripcion" />
            </flux:field>

            <flux:field>
                <flux:label>Línea de Investigación</flux:label>
                <flux:input wire:model="lineaInvestigacion" placeholder="Ej. Entomología sistemática" />
                <flux:error name="lineaInvestigacion" />
            </flux:field>

            <flux:field>
                <flux:label>Propósito del Préstamo</flux:label>
                <flux:textarea wire:model="propositoPrestamo" rows="4"
                    placeholder="Describe el objetivo científico del préstamo..." />
                <flux:error name="propositoPrestamo" />
            </flux:field>

            <flux:field>
                <flux:label>Duración Propuesta (meses)</flux:label>
                <flux:input type="number" wire:model="duracionPropuestaMeses" min="1" max="12" class="w-32" />
                <flux:error name="duracionPropuestaMeses" />
            </flux:field>
        </div>

        {{-- Resumen lateral --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm p-6 space-y-4 h-fit">
            <flux:heading size="lg" level="2">Acciones</flux:heading>
            <flux:separator />
            <flux:text class="text-text-secondary text-sm">
                Guarda el borrador en cualquier momento. Cuando la solicitud esté completa, envíala para revisión del curador.
            </flux:text>
            <div class="flex flex-col gap-2">
                <flux:button variant="primary" wire:click="guardarBorrador"
                    wire:loading.attr="disabled" wire:target="guardarBorrador">
                    <flux:icon wire:loading wire:target="guardarBorrador" name="arrow-path" class="animate-spin" />
                    Guardar Borrador
                </flux:button>
                <flux:button variant="filled" wire:click="enviarSolicitud"
                    wire:loading.attr="disabled" wire:target="enviarSolicitud">
                    <flux:icon wire:loading wire:target="enviarSolicitud" name="arrow-path" class="animate-spin" />
                    Enviar para Revisión
                </flux:button>
            </div>
            <flux:error name="solicitudId" />
        </div>

    </div>

    {{-- Especimenes solicitados --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm p-6 space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg" level="2">Especimenes Solicitados</flux:heading>
            <flux:button size="sm" variant="ghost" icon="plus" wire:click="addItem">
                Agregar espécimen
            </flux:button>
        </div>
        <flux:separator />

        <flux:error name="items" />

        @if(empty($items))
            <p class="text-sm text-text-secondary text-center py-4">
                Agrega al menos un espécimen para poder guardar la solicitud.
            </p>
        @else
            <div class="space-y-3">
                @foreach($items as $index => $item)
                    <div class="flex gap-3 items-end" wire:key="item-{{ $index }}">
                        <flux:field class="flex-1">
                            <flux:label>Código de Espécimen</flux:label>
                            <flux:input wire:model="items.{{ $index }}.especimen_codigo_externo"
                                placeholder="Ej. MEPN-0001" />
                        </flux:field>
                        <flux:field class="w-32">
                            <flux:label>Cantidad</flux:label>
                            <flux:input type="number" wire:model="items.{{ $index }}.cantidad_solicitada" min="1" />
                        </flux:field>
                        <flux:button size="sm" variant="ghost" icon="trash"
                            wire:click="removeItem({{ $index }})" class="text-error mb-1" />
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
