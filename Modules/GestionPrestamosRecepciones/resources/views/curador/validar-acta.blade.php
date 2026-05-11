<div class="space-y-6">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.actas') }}">
            Bandeja de Actas
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $acta?->numero_prestamo ?? 'Validar Acta' }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if($successMessage)
        <flux:callout variant="success" icon="check-circle">{{ $successMessage }}</flux:callout>
    @endif

    @if(!$acta)
        <flux:callout variant="danger" icon="exclamation-triangle">Acta no encontrada.</flux:callout>
    @else
        <div class="flex items-center justify-between">
            <flux:heading size="xl" level="1" class="font-display">Validar Acta Firmada</flux:heading>
            <x-gestionprestamosrecepciones::acta-status-badge :estado="$acta->estado" />
        </div>

        <dl class="flex gap-6 text-sm">
            <div>
                <dt class="text-text-secondary">N.º Préstamo</dt>
                <dd class="font-mono font-medium text-text-primary">{{ $acta->numero_prestamo }}</dd>
            </div>
            <div>
                <dt class="text-text-secondary">Solicitud</dt>
                <dd class="text-text-primary">{{ $acta->solicitud?->titulo_estudio }}</dd>
            </div>
        </dl>

        {{-- Comparación lado a lado de PDFs --}}
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                <div class="bg-bg-main px-4 py-2 border-b border-border flex items-center justify-between">
                    <flux:text class="text-sm font-medium text-text-primary">Acta Original (generada)</flux:text>
                    <flux:button size="xs" variant="ghost" icon="arrow-top-right-on-square"
                        href="{{ route('prestamos.acta.ver', $acta->id) }}" target="_blank">
                        Abrir
                    </flux:button>
                </div>
                <iframe src="{{ route('prestamos.acta.ver', $acta->id) }}"
                    class="w-full h-[600px]" title="Acta Original"></iframe>
            </div>

            <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                <div class="bg-bg-main px-4 py-2 border-b border-border">
                    <flux:text class="text-sm font-medium text-text-primary">Acta Firmada (por investigador)</flux:text>
                </div>
                @if($acta->pdf_firmado_ruta)
                    <iframe src="{{ route('prestamos.acta.pdf-firmado', $acta->id) }}"
                        class="w-full h-[600px]" title="Acta Firmada"></iframe>
                @else
                    <div class="flex items-center justify-center h-40 text-text-secondary text-sm">
                        Acta firmada no disponible aún
                    </div>
                @endif
            </div>
        </div>

        {{-- Acciones de validación --}}
        @if($acta->estado === 'pendiente_validacion')
            <div class="flex gap-3 justify-end">
                <flux:button variant="ghost" icon="arrow-uturn-left"
                    wire:click="$set('showMotivoModal', true)">
                    Devolver para Refirmar
                </flux:button>
                <flux:button variant="primary" icon="check-circle" wire:click="validar"
                    wire:loading.attr="disabled" wire:target="validar"
                    wire:confirm="¿Confirmas que la firma es válida y el acta puede cerrarse?">
                    <flux:icon wire:loading wire:target="validar" name="arrow-path" class="animate-spin" />
                    Validar Firma
                </flux:button>
            </div>
        @elseif($acta->estado === 'validada')
            <flux:callout variant="success" icon="check-circle">
                Esta acta ha sido validada y el proceso de préstamo está completo.
            </flux:callout>
        @endif
    @endif

    {{-- Modal: motivo de devolución --}}
    <flux:modal wire:model="showMotivoModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <flux:heading size="lg">Devolver para Refirmar</flux:heading>
            <flux:text class="text-text-secondary text-sm">
                Indica el motivo por el que el investigador debe volver a firmar el acta.
            </flux:text>

            <flux:field>
                <flux:label>Motivo de la Devolución</flux:label>
                <flux:textarea wire:model="motivoDevolucion" rows="4"
                    placeholder="Describe el problema con la firma (mínimo 10 caracteres)..." />
                <flux:error name="motivoDevolucion" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showMotivoModal', false)">Cancelar</flux:button>
                <flux:button variant="danger" wire:click="devolverParaRefirmar"
                    wire:loading.attr="disabled" wire:target="devolverParaRefirmar">
                    <flux:icon wire:loading wire:target="devolverParaRefirmar" name="arrow-path" class="animate-spin" />
                    Devolver Acta
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
