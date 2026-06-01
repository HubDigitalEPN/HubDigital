<div class="space-y-6">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.actas') }}">
            Bandeja de actas
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $acta?->numero_prestamo ?? 'Validar acta' }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if($successMessage)
        <flux:callout variant="success" icon="check-circle">{{ $successMessage }}</flux:callout>
    @endif

    @if(!$acta)
        <flux:callout variant="danger" icon="exclamation-triangle">Acta no encontrada.</flux:callout>
    @else
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="xl" level="1" class="font-display">
                {{ $acta->estado === 'validada' ? 'Acta de préstamo' : 'Validar acta firmada' }}
            </flux:heading>
            <x-gestionprestamosrecepciones::acta-status-badge :estado="$acta->estado" />
        </div>

        <div class="rounded-lg border border-border bg-surface shadow-sm p-5">
            <dl class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm lg:grid-cols-4">
                <div>
                    <dt class="text-text-secondary">N.º préstamo</dt>
                    <dd class="font-mono font-medium text-text-primary">{{ $acta->numero_prestamo }}</dd>
                </div>
                <div>
                    <dt class="text-text-secondary">N.º solicitud</dt>
                    <dd class="font-mono text-text-primary">{{ $acta->solicitud?->numero_solicitud ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-text-secondary">Fecha de inicio</dt>
                    <dd class="text-text-primary">{{ $acta->fecha_inicio?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-text-secondary">Fecha de vencimiento</dt>
                    <dd class="text-text-primary">{{ $acta->fecha_fin?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-text-secondary">Título del estudio</dt>
                    <dd class="font-medium text-text-primary">{{ $acta->solicitud?->titulo_estudio ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-text-secondary">Institución</dt>
                    <dd class="text-text-primary">{{ $acta->solicitud?->institucion_adscripcion ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-text-secondary">Tipo de préstamo</dt>
                    <dd class="text-text-primary capitalize">{{ str_replace('_', ' ', $acta->tipo_prestamo) }}</dd>
                </div>
                <div>
                    <dt class="text-text-secondary">Alcance</dt>
                    <dd class="text-text-primary">
                        @if(($acta->alcance_prestamo ?? 'nacional') === 'internacional')
                            <span class="inline-flex items-center gap-1 rounded-full bg-[#E3F2FD] text-[#1565C0] border border-[#90CAF9] px-2 py-0.5 text-xs font-semibold">
                                <flux:icon name="globe-alt" class="size-3" />
                                Internacional
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-[#E8F5E9] text-[#2E7D32] border border-[#A5D6A7] px-2 py-0.5 text-xs font-semibold">
                                <flux:icon name="map-pin" class="size-3" />
                                Nacional
                            </span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Visor de documentos con pestañas --}}
        <div x-data="{ tab: '{{ $acta->pdf_firmado_ruta ? 'firmada' : 'original' }}' }" class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">

            {{-- Barra de pestañas --}}
            <div class="flex items-center gap-1 border-b border-border bg-bg-main px-2">
                <button @click="tab = 'original'"
                    :class="tab === 'original' ? 'border-b-2 border-science-blue text-science-blue' : 'text-text-secondary hover:text-text-primary'"
                    class="px-3 py-2.5 text-sm font-medium transition-colors whitespace-nowrap">
                    Acta original
                </button>
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
                @if($acta->documento_exportacion_ruta)
                    <button @click="tab = 'exportacion'"
                        :class="tab === 'exportacion' ? 'border-b-2 border-science-blue text-science-blue' : 'text-text-secondary hover:text-text-primary'"
                        class="px-3 py-2.5 text-sm font-medium transition-colors whitespace-nowrap">
                        Doc. Ministerio
                    </button>
                @endif

                {{-- Botón "Abrir en nueva pestaña" contextual --}}
                <div class="ml-auto flex items-center gap-2">
                    {{-- Original --}}
                    <div x-show="tab === 'original'" class="flex items-center gap-1">
                        <a href="{{ route('prestamos.acta.embed', $acta->id) }}" target="_blank"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                            <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                            Abrir
                        </a>
                        <a href="{{ route('prestamos.acta.pdf-original', $acta->id) }}" download="acta-{{ $acta->numero_prestamo }}.pdf"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                            <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Descargar
                        </a>
                    </div>

                    {{-- Firmada --}}
                    @php
                        $esFirmaDigital = str_starts_with($acta->pdf_firmado_ruta ?? '', 'firmas-investigador/');
                    @endphp
                    <div x-show="tab === 'firmada'" x-cloak class="flex items-center gap-1">
                        <a href="{{ route('prestamos.acta.ver', $acta->id) }}" target="_blank"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                            <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                            Abrir
                        </a>
                        @if($acta->pdf_firmado_ruta)
                            @if($esFirmaDigital)
                                <a href="{{ route('prestamos.acta.ver', $acta->id) }}?download=1" target="_blank"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                                    <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                    Descargar
                                </a>
                            @else
                                <a href="{{ route('prestamos.acta.pdf-firmado', $acta->id) }}" download="acta-firmada-{{ $acta->numero_prestamo }}.pdf"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                                    <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                    Descargar
                                </a>
                            @endif
                        @endif
                    </div>

                    {{-- Identidad --}}
                    @if($acta->documento_identidad_ruta)
                        <div x-show="tab === 'identidad'" x-cloak class="flex items-center gap-1">
                            <a href="{{ route('prestamos.acta.documento-identidad', $acta->id) }}" target="_blank"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                                <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                </svg>
                                Abrir
                            </a>
                            <a href="{{ route('prestamos.acta.documento-identidad', $acta->id) }}" download="identidad-{{ $acta->numero_prestamo }}.pdf"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                                <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                                Descargar
                            </a>
                        </div>
                    @endif

                    {{-- Exportacion --}}
                    @if($acta->documento_exportacion_ruta)
                        <div x-show="tab === 'exportacion'" x-cloak class="flex items-center gap-1">
                            <a href="{{ route('prestamos.acta.documento-exportacion', $acta->id) }}" target="_blank"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                                <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                </svg>
                                Abrir
                            </a>
                            <a href="{{ route('prestamos.acta.documento-exportacion', $acta->id) }}" download="exportacion-{{ $acta->numero_prestamo }}.pdf"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                                <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                                Descargar
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Contenido de cada pestaña --}}
            <div x-show="tab === 'original'">
                <iframe src="{{ route('prestamos.acta.embed', $acta->id) }}"
                    class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                    title="Acta original"></iframe>
            </div>

            <div x-show="tab === 'firmada'" x-cloak>
                @if($acta->pdf_firmado_ruta)
                    <iframe src="{{ route('prestamos.acta.embed', $acta->id) }}"
                        class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                        title="Acta firmada"></iframe>
                @else
                    <div class="flex items-center justify-center text-text-secondary text-sm"
                        style="height: 300px;">
                        El investigador aún no ha firmado el acta digitalmente.
                    </div>
                @endif
            </div>

            @if($acta->documento_identidad_ruta)
                <div x-show="tab === 'identidad'" x-cloak>
                    <iframe src="{{ route('prestamos.acta.documento-identidad', $acta->id) }}"
                        class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                        title="Documento de identidad"></iframe>
                </div>
            @endif

            @if($acta->documento_exportacion_ruta)
                <div x-show="tab === 'exportacion'" x-cloak>
                    <iframe src="{{ route('prestamos.acta.documento-exportacion', $acta->id) }}"
                        class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                        title="Documento de exportación"></iframe>
                </div>
            @endif

        </div>

        {{-- Acciones de validación --}}
        @if($acta->estado === 'pendiente_validacion')
            <div class="flex gap-3 justify-end">
                <flux:button variant="ghost" icon="arrow-uturn-left"
                    wire:click="$set('showMotivoModal', true)">
                    Devolver para refirmar
                </flux:button>
                <flux:button variant="primary" icon="check-circle" wire:click="validar"
                    wire:loading.attr="disabled" wire:target="validar"
                    wire:confirm="¿Confirmas que la firma es válida y el acta puede cerrarse?">
                    <flux:icon wire:loading wire:target="validar" name="arrow-path" class="animate-spin" />
                    Validar firma
                </flux:button>
            </div>
        @endif
    @endif

    {{-- Modal: motivo de devolución --}}
    <flux:modal wire:model="showMotivoModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <flux:heading size="lg">Devolver para refirmar</flux:heading>
            <flux:text class="text-text-secondary text-sm">
                Indica el motivo por el que el investigador debe volver a firmar el acta.
            </flux:text>

            <flux:field>
                <flux:label>Motivo de la devolución</flux:label>
                <flux:textarea wire:model="motivoDevolucion" rows="4"
                    placeholder="Describe el problema con la firma (mínimo 10 caracteres)..." />
                <flux:error name="motivoDevolucion" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showMotivoModal', false)">Cancelar</flux:button>
                <flux:button variant="danger" wire:click="devolverParaRefirmar"
                    wire:loading.attr="disabled" wire:target="devolverParaRefirmar">
                    <flux:icon wire:loading wire:target="devolverParaRefirmar" name="arrow-path" class="animate-spin" />
                    Devolver acta
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
