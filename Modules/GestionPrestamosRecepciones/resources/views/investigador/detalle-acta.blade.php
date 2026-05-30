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
                        El acta está lista. Visualízala, imprímela, fírmala y adjunta el PDF firmado
                        junto con tu documento de identidad (cédula o pasaporte).
                    </flux:callout>
                @elseif($acta->estado === 'pendiente_validacion')
                    <flux:callout variant="info" icon="information-circle">
                        Tus documentos están en revisión por el curador.
                    </flux:callout>
                @elseif($acta->estado === 'validada')
                    @if($prestamo?->estado === 'en_transito')
                        <flux:callout variant="success" icon="check-circle">
                            El acta ha sido validada. Los especímenes serán despachados a tu dirección. Cuando los recibas, deberás confirmar la recepción desde el detalle del préstamo.
                        </flux:callout>
                    @elseif($prestamo?->estado === 'pendiente_aprobacion_verificacion')
                        <flux:callout variant="info" icon="clock">
                            Has reportado la recepción de los especímenes. El curador está revisando tu informe.
                        </flux:callout>
                    @else
                        <flux:callout variant="success" icon="check-circle">
                            El acta ha sido validada y el préstamo está activo.
                        </flux:callout>
                    @endif
                @endif

                <div class="flex flex-wrap gap-2">
                    @if($acta->estado === 'pendiente_firma')
                        <flux:button variant="primary" icon="arrow-up-tray" size="sm"
                            wire:click="$set('showUploadModal', true)">
                            Adjuntar documentos
                        </flux:button>
                    @endif

                    @if($prestamo)
                        <flux:button variant="ghost" icon="archive-box" size="sm" wire:navigate
                            href="{{ route('prestamos.investigador.prestamo.detalle', $prestamo->id) }}">
                            Ver préstamo
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

    {{-- Visor de documentos (adapta según el estado del acta) --}}
    @if($acta->estado === 'pendiente_firma')
        {{-- En pendiente_firma: muestra el acta original para que el investigador la vea y firme --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <div class="flex items-center justify-between bg-bg-main px-4 py-2 border-b border-border">
                <flux:text class="text-sm font-medium text-text-primary">Acta de préstamo</flux:text>
                <a href="{{ route('prestamos.acta.embed', $acta->id) }}" target="_blank"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                    <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                    Abrir
                </a>
            </div>
            <iframe src="{{ route('prestamos.acta.embed', $acta->id) }}"
                class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                title="Acta de préstamo"></iframe>
        </div>

    @elseif(in_array($acta->estado, ['pendiente_validacion', 'validada']) && $acta->pdf_firmado_ruta)
        {{-- En pendiente_validacion o validada: muestra los documentos enviados con pestañas --}}
        <div x-data="{ tab: 'firmada' }" class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">

            <div class="flex items-center gap-1 border-b border-border bg-bg-main px-2">
                <button @click="tab = 'firmada'"
                    :class="tab === 'firmada' ? 'border-b-2 border-science-blue text-science-blue' : 'text-text-secondary hover:text-text-primary'"
                    class="px-3 py-2.5 text-sm font-medium transition-colors whitespace-nowrap">
                    Acta firmada
                </button>
                @if($acta->documento_identidad_ruta)
                    <button @click="tab = 'identidad'"
                        :class="tab === 'identidad' ? 'border-b-2 border-science-blue text-science-blue' : 'text-text-secondary hover:text-text-primary'"
                        class="px-3 py-2.5 text-sm font-medium transition-colors whitespace-nowrap">
                        Doc. de identidad
                    </button>
                @endif

                <div class="ml-auto">
                    <a x-show="tab === 'firmada'"
                        href="{{ route('prestamos.acta.pdf-firmado', $acta->id) }}" target="_blank"
                        class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                        <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                        </svg>
                        Abrir
                    </a>
                    @if($acta->documento_identidad_ruta)
                        <a x-show="tab === 'identidad'" x-cloak
                            href="{{ route('prestamos.acta.documento-identidad', $acta->id) }}" target="_blank"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                            <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                            Abrir
                        </a>
                    @endif
                </div>
            </div>

            <div x-show="tab === 'firmada'">
                <iframe src="{{ route('prestamos.acta.pdf-firmado', $acta->id) }}"
                    class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                    title="Acta firmada"></iframe>
            </div>

            @if($acta->documento_identidad_ruta)
                <div x-show="tab === 'identidad'" x-cloak>
                    <iframe src="{{ route('prestamos.acta.documento-identidad', $acta->id) }}"
                        class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                        title="Documento de identidad"></iframe>
                </div>
            @endif

        </div>
    @endif

    {{-- Modal: subir acta firmada y documento de identidad --}}
    <flux:modal wire:model="showUploadModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <flux:heading size="lg">Subir documentos para validación</flux:heading>
            <flux:text class="text-text-secondary text-sm">
                Debes adjuntar dos documentos en PDF (máximo 10 MB cada uno).
            </flux:text>

            <flux:field>
                <flux:label>Acta firmada (PDF)</flux:label>
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

            <flux:field>
                <flux:label>Documento de identidad — cédula o pasaporte (PDF)</flux:label>
                <input type="file" wire:model="documentoIdentidad" accept=".pdf"
                    class="block w-full text-sm text-text-secondary
                           file:mr-3 file:py-2 file:px-4 file:rounded-lg
                           file:border-0 file:text-sm file:font-medium
                           file:bg-science-blue file:text-white
                           hover:file:bg-blue-700" />
                <div wire:loading wire:target="documentoIdentidad" class="flex items-center gap-1.5 mt-1 text-xs text-text-secondary">
                    <flux:icon name="arrow-path" class="animate-spin size-3" />
                    Subiendo archivo...
                </div>
                <flux:error name="documentoIdentidad" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showUploadModal', false)">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="subirActa"
                    wire:loading.attr="disabled" wire:target="subirActa,pdfFirmado,documentoIdentidad">
                    <flux:icon wire:loading wire:target="subirActa" name="arrow-path" class="animate-spin" />
                    Subir documentos
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
