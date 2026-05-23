<div class="p-6 space-y-6">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.mis-actas') }}">
            Mis actas
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $acta?->numero_prestamo ?? 'Detalle' }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if($successMessage)
        <flux:callout variant="success" icon="check-circle">{{ $successMessage }}</flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Información del acta --}}
        <div class="lg:col-span-2 space-y-6">

            <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between gap-4">
                    <flux:heading size="xl" level="1" class="font-display font-mono">
                        {{ $acta->numero_prestamo }}
                    </flux:heading>
                    <x-gestionprestamosrecepciones::acta-status-badge :estado="$acta->estado" />
                </div>
                <flux:separator />

                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-text-secondary">Tipo de préstamo</dt>
                        <dd class="text-text-primary capitalize">{{ str_replace('_', ' ', $acta->tipo_prestamo) }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-secondary">Fecha de inicio</dt>
                        <dd class="text-text-primary">{{ $acta->fecha_inicio?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-secondary">Fecha de vencimiento</dt>
                        <dd class="text-text-primary">{{ $acta->fecha_fin?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    @if($acta->condiciones_generales)
                        <div class="col-span-2">
                            <dt class="text-text-secondary">Condiciones generales</dt>
                            <dd class="text-text-primary mt-1">{{ $acta->condiciones_generales }}</dd>
                        </div>
                    @endif
                </dl>

                @if($acta->estado === 'pendiente_firma' && $acta->motivo_devolucion)
                    <flux:callout variant="warning" icon="arrow-uturn-left">
                        <flux:heading size="sm">El acta fue devuelta para refirmar</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ $acta->motivo_devolucion }}</flux:text>
                    </flux:callout>
                @elseif($acta->estado === 'pendiente_firma')
                    <flux:callout variant="info" icon="information-circle">
                        El acta está lista. Visualízala, imprímela, fírmala y sube el PDF firmado.
                    </flux:callout>
                @elseif($acta->estado === 'pendiente_validacion')
                    <flux:callout variant="info" icon="information-circle">
                        El acta firmada está en proceso de validación por el curador.
                    </flux:callout>
                @elseif($acta->estado === 'validada')
                    <flux:callout variant="success" icon="check-circle">
                        El acta ha sido validada. El préstamo está activo.
                    </flux:callout>
                @endif

                <div class="flex flex-wrap gap-2">
                    <flux:button variant="ghost" icon="eye" size="sm"
                        href="{{ route('prestamos.acta.ver', $acta->id) }}" target="_blank">
                        Ver / imprimir acta
                    </flux:button>

                    @if($acta->estado === 'pendiente_firma')
                        <flux:button variant="primary" icon="arrow-up-tray" size="sm"
                            wire:click="$set('showUploadModal', true)">
                            Subir acta firmada
                        </flux:button>
                    @endif

                    @if($prestamo)
                        <flux:button variant="ghost" icon="archive-box" size="sm" wire:navigate
                            href="{{ route('prestamos.investigador.prestamo.detalle', $prestamo->id) }}">
                            Ver préstamo activo
                        </flux:button>
                    @endif
                </div>
            </div>

        </div>

        {{-- Historial del acta --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-2 h-fit">
            <flux:heading size="lg" level="2" class="font-display">Historial del acta</flux:heading>
            <flux:separator />
            <div class="mt-3">
                @php
                    $etiquetasActa = [
                        'ActaEnviada'                   => 'Acta enviada al investigador',
                        'ActaFirmadaSubida'              => 'Acta firmada subida',
                        'ActaDevueltaPorFirmaInvalida'   => 'Acta devuelta por el curador',
                        'ActaValidada'                   => 'Acta validada',
                    ];
                @endphp
                @forelse($historialActa as $i => $evento)
                    @php
                        $titulo = $etiquetasActa[$evento->tipo] ?? $evento->tipo;
                        $esUltimo = $i === count($historialActa) - 1;
                    @endphp
                    <x-gestionprestamosrecepciones::timeline-event
                        :fecha="$evento->ocurridoEn->format('d/m/Y H:i')"
                        :titulo="$titulo"
                        :ultimo="$esUltimo" />
                @empty
                    <flux:text class="text-xs text-text-secondary">Sin eventos registrados para este acta.</flux:text>
                @endforelse
            </div>
        </div>

    </div>

    {{-- Modal: subir acta firmada --}}
    <flux:modal wire:model="showUploadModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <flux:heading size="lg">Subir acta firmada</flux:heading>
            <flux:text class="text-text-secondary text-sm">
                Sube el PDF del acta con tu firma. Máximo 10 MB, solo formato PDF.
            </flux:text>

            <flux:field>
                <flux:label>Archivo PDF</flux:label>
                <input type="file" wire:model="pdfFirmado" accept=".pdf"
                    class="block w-full text-sm text-text-secondary
                           file:mr-3 file:py-2 file:px-4 file:rounded-lg
                           file:border-0 file:text-sm file:font-medium
                           file:bg-science-blue file:text-white
                           hover:file:bg-blue-700" />
                <div wire:loading wire:target="pdfFirmado" class="flex items-center gap-1.5 mt-1 text-xs text-text-secondary">
                    <flux:icon name="arrow-path" class="animate-spin size-3" />
                    Subiendo archivo...
                </div>
                <flux:error name="pdfFirmado" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showUploadModal', false)">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="subirActa"
                    wire:loading.attr="disabled" wire:target="subirActa,pdfFirmado">
                    <flux:icon wire:loading wire:target="subirActa" name="arrow-path" class="animate-spin" />
                    Subir acta
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
