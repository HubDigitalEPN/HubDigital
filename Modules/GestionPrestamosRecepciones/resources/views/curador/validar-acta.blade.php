<div class="space-y-5">

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

        {{-- Encabezado --}}
        <div>
            <flux:heading size="xl" level="1" class="font-display">
                {{ $acta->estado === 'validada' ? 'Acta de préstamo' : 'Validar acta firmada' }}
            </flux:heading>
            <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                <p class="font-mono text-xs text-text-secondary">{{ $acta->numero_prestamo }}</p>
                <x-gestionprestamosrecepciones::acta-status-badge :estado="$acta->estado" />
            </div>
        </div>

        {{-- Grid: datos + historial --}}
        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Datos del acta --}}
            <div class="lg:col-span-2 rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                        <flux:icon name="clipboard-document" class="size-3.5" />
                    </div>
                    <flux:heading size="base" level="2" class="font-display">Información del acta</flux:heading>
                </div>
                <div class="p-5">
                    <dl class="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">N.º préstamo</dt>
                            <dd class="font-mono font-medium text-text-primary mt-1">{{ $acta->numero_prestamo }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">N.º solicitud</dt>
                            <dd class="font-mono text-text-primary mt-1">{{ $acta->solicitud?->numero_solicitud ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Fecha de inicio</dt>
                            <dd class="font-medium text-text-primary mt-1">{{ $acta->fecha_inicio?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Fecha de vencimiento</dt>
                            <dd class="font-medium text-text-primary mt-1">{{ $acta->fecha_fin?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Título del estudio</dt>
                            <dd class="font-medium text-text-primary mt-1">{{ $acta->solicitud?->titulo_estudio ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Institución</dt>
                            <dd class="font-medium text-text-primary mt-1">{{ $acta->solicitud?->institucion_adscripcion ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Tipo / Alcance</dt>
                            <dd class="mt-1 capitalize font-medium text-text-primary">{{ str_replace('_', ' ', $acta->tipo_prestamo) }} ·
                                @if(($acta->alcance_prestamo ?? 'nacional') === 'internacional')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-science-blue/10 text-science-blue px-2 py-0.5 text-xs font-semibold">
                                        <flux:icon name="globe-alt" class="size-3" /> Internacional
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-bio-green/10 text-bio-green px-2 py-0.5 text-xs font-semibold">
                                        <flux:icon name="map-pin" class="size-3" /> Nacional
                                    </span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Historial del acta --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden h-fit">
                <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                        <flux:icon name="clock" class="size-3.5" />
                    </div>
                    <flux:heading size="base" level="2" class="font-display">Historial</flux:heading>
                </div>
                <div class="p-5">
                    @php
                        $etiquetasActa = [
                            'ActaEnviada'                  => 'Acta enviada',
                            'ActaFirmadaSubida'            => 'Firma subida',
                            'ActaFirmadaDigitalmente'      => 'Firmada digitalmente',
                            'ActaDevueltaPorFirmaInvalida' => 'Devuelta para refirmar',
                            'ActaValidada'                 => 'Acta validada',
                        ];
                    @endphp
                    @forelse($historialActa as $i => $evento)
                        <x-gestionprestamosrecepciones::timeline-event
                            :fecha="$evento->ocurridoEn->format('d/m/Y H:i')"
                            :titulo="$etiquetasActa[$evento->tipo] ?? $evento->tipo"
                            :ultimo="$i === count($historialActa) - 1" />
                    @empty
                        <flux:text class="text-xs text-text-secondary">Sin eventos registrados.</flux:text>
                    @endforelse
                </div>
            </div>

        </div>

        {{-- Visor de documentos --}}
        <div x-data="{ tab: '{{ $acta->pdf_firmado_ruta ? 'firmada' : 'original' }}' }"
             class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">

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

                <div class="ml-auto flex items-center gap-2">
                    <div x-show="tab === 'original'" class="flex items-center gap-1">
                        <a href="{{ route('prestamos.acta.embed', $acta->id) }}" target="_blank"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                            <flux:icon name="arrow-top-right-on-square" class="size-3.5" /> Abrir
                        </a>
                        <a href="{{ route('prestamos.acta.pdf-original', $acta->id) }}" download="acta-{{ $acta->numero_prestamo }}.pdf"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                            <flux:icon name="arrow-down-tray" class="size-3.5" /> Descargar
                        </a>
                    </div>

                    @php $esFirmaDigital = str_starts_with($acta->pdf_firmado_ruta ?? '', 'firmas-investigador/'); @endphp
                    <div x-show="tab === 'firmada'" x-cloak class="flex items-center gap-1">
                        <a href="{{ route('prestamos.acta.ver', $acta->id) }}" target="_blank"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                            <flux:icon name="arrow-top-right-on-square" class="size-3.5" /> Abrir
                        </a>
                        @if($acta->pdf_firmado_ruta)
                            @if($esFirmaDigital)
                                <a href="{{ route('prestamos.acta.ver', $acta->id) }}?download=1" target="_blank"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                                    <flux:icon name="arrow-down-tray" class="size-3.5" /> Descargar
                                </a>
                            @else
                                <a href="{{ route('prestamos.acta.pdf-firmado', $acta->id) }}" download="acta-firmada-{{ $acta->numero_prestamo }}.pdf"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                                    <flux:icon name="arrow-down-tray" class="size-3.5" /> Descargar
                                </a>
                            @endif
                        @endif
                    </div>

                    @if($acta->documento_identidad_ruta)
                        <div x-show="tab === 'identidad'" x-cloak class="flex items-center gap-1">
                            <a href="{{ route('prestamos.acta.documento-identidad', $acta->id) }}" target="_blank"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                                <flux:icon name="arrow-top-right-on-square" class="size-3.5" /> Abrir
                            </a>
                            <a href="{{ route('prestamos.acta.documento-identidad', $acta->id) }}" download="identidad-{{ $acta->numero_prestamo }}.pdf"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                                <flux:icon name="arrow-down-tray" class="size-3.5" /> Descargar
                            </a>
                        </div>
                    @endif

                    @if($acta->documento_exportacion_ruta)
                        <div x-show="tab === 'exportacion'" x-cloak class="flex items-center gap-1">
                            <a href="{{ route('prestamos.acta.documento-exportacion', $acta->id) }}" target="_blank"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                                <flux:icon name="arrow-top-right-on-square" class="size-3.5" /> Abrir
                            </a>
                            <a href="{{ route('prestamos.acta.documento-exportacion', $acta->id) }}" download="exportacion-{{ $acta->numero_prestamo }}.pdf"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                                <flux:icon name="arrow-down-tray" class="size-3.5" /> Descargar
                            </a>
                        </div>
                    @endif
                </div>
            </div>

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
                    <div class="flex flex-col items-center justify-center text-text-secondary text-sm gap-2" style="height: 300px;">
                        <flux:icon name="clock" class="size-8 opacity-40" />
                        <span>El investigador aún no ha firmado el acta.</span>
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
