<div class="space-y-5">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.actas') }}">
            Bandeja de actas
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $acta?->numeroPrestamo ?? 'Validar acta' }}</flux:breadcrumbs.item>
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
                <p class="font-mono text-xs text-text-secondary">{{ $acta->numeroPrestamo }}</p>
                <x-gestionprestamosrecepciones::acta-status-badge :estado="$acta->estado" />
            </div>

            @if($acta->firmadoCuradorCommonName)
                <div class="mt-2 inline-flex items-center gap-2 rounded-lg bg-bio-green/10 px-3 py-1.5">
                    <flux:icon name="shield-check" class="size-4 text-bio-green" />
                    <span class="text-xs text-text-primary">
                        Firmado digitalmente por <span class="font-medium">{{ $acta->firmadoCuradorCommonName }}</span>
                        @if($acta->firmadoCuradorSelloDeTiempo)
                            · sello de tiempo {{ $acta->firmadoCuradorSelloDeTiempo->format('d/m/Y H:i') }}
                        @endif
                    </span>
                </div>
            @endif
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
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Fecha de inicio</dt>
                            <dd class="font-medium text-text-primary mt-1">{{ $acta->fechaInicio?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Fecha de vencimiento</dt>
                            <dd class="font-medium text-text-primary mt-1">{{ $acta->fechaFin?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Título del estudio</dt>
                            <dd class="font-medium text-text-primary mt-1">{{ $acta->tituloEstudio ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Institución</dt>
                            <dd class="font-medium text-text-primary mt-1">{{ $acta->institucionAdscripcion ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Tipo / Alcance</dt>
                            <dd class="mt-1 capitalize font-medium text-text-primary">{{ str_replace('_', ' ', $acta->tipoPrestamo) }} ·
                                @if(($acta->alcancePrestamo ?? 'nacional') === 'internacional')
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
                            'ActaFirmadaCriptograficamentePorCurador' => 'Firmada por el curador',
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
        <div x-data="{ tab: '{{ $acta->pdfFirmadoRuta ? 'firmada' : 'original' }}' }"
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
                @if($acta->documentoIdentidadRuta)
                    <button @click="tab = 'identidad'"
                        :class="tab === 'identidad' ? 'border-b-2 border-science-blue text-science-blue' : 'text-text-secondary hover:text-text-primary'"
                        class="px-3 py-2.5 text-sm font-medium transition-colors whitespace-nowrap">
                        Doc. de identidad
                    </button>
                @endif
                @if($acta->documentoExportacionRuta)
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
                        <a href="{{ route('prestamos.acta.pdf-original', $acta->id) }}" download="acta-{{ $acta->numeroPrestamo }}.pdf"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                            <flux:icon name="arrow-down-tray" class="size-3.5" /> Descargar
                        </a>
                    </div>

                    @php $esFirmaDigital = str_starts_with($acta->pdfFirmadoRuta ?? '', 'firmas-investigador/'); @endphp
                    <div x-show="tab === 'firmada'" x-cloak class="flex items-center gap-1">
                        <a href="{{ $esFirmaDigital ? route('prestamos.acta.ver', $acta->id) : route('prestamos.acta.pdf-firmado', $acta->id) }}" target="_blank"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                            <flux:icon name="arrow-top-right-on-square" class="size-3.5" /> Abrir
                        </a>
                        @if($acta->pdfFirmadoRuta)
                            @if($esFirmaDigital)
                                <a href="{{ route('prestamos.acta.ver', $acta->id) }}?download=1" target="_blank"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                                    <flux:icon name="arrow-down-tray" class="size-3.5" /> Descargar
                                </a>
                            @else
                                <a href="{{ route('prestamos.acta.pdf-firmado', $acta->id) }}" download="acta-firmada-{{ $acta->numeroPrestamo }}.pdf"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                                    <flux:icon name="arrow-down-tray" class="size-3.5" /> Descargar
                                </a>
                            @endif
                        @endif
                    </div>

                    @if($acta->documentoIdentidadRuta)
                        <div x-show="tab === 'identidad'" x-cloak class="flex items-center gap-1">
                            <a href="{{ route('prestamos.acta.documento-identidad', $acta->id) }}" target="_blank"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                                <flux:icon name="arrow-top-right-on-square" class="size-3.5" /> Abrir
                            </a>
                            <a href="{{ route('prestamos.acta.documento-identidad', $acta->id) }}" download="identidad-{{ $acta->numeroPrestamo }}.pdf"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                                <flux:icon name="arrow-down-tray" class="size-3.5" /> Descargar
                            </a>
                        </div>
                    @endif

                    @if($acta->documentoExportacionRuta)
                        <div x-show="tab === 'exportacion'" x-cloak class="flex items-center gap-1">
                            <a href="{{ route('prestamos.acta.documento-exportacion', $acta->id) }}" target="_blank"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                                <flux:icon name="arrow-top-right-on-square" class="size-3.5" /> Abrir
                            </a>
                            <a href="{{ route('prestamos.acta.documento-exportacion', $acta->id) }}" download="exportacion-{{ $acta->numeroPrestamo }}.pdf"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                                <flux:icon name="arrow-down-tray" class="size-3.5" /> Descargar
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <div x-show="tab === 'original'">
                <iframe src="{{ route('prestamos.acta.embed', $acta->id) }}?sin_firma=1"
                    class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                    title="Acta original"></iframe>
            </div>

            <div x-show="tab === 'firmada'" x-cloak>
                @if($acta->pdfFirmadoRuta)
                    @if($esFirmaDigital)
                        <iframe src="{{ route('prestamos.acta.embed', $acta->id) }}"
                            class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                            title="Acta firmada digitalmente"></iframe>
                    @else
                        <iframe src="{{ route('prestamos.acta.pdf-firmado', $acta->id) }}"
                            class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                            title="Acta firmada subida"></iframe>
                    @endif
                @else
                    <div class="flex flex-col items-center justify-center text-text-secondary text-sm gap-2" style="height: 300px;">
                        <flux:icon name="clock" class="size-8 opacity-40" />
                        <span>El investigador aún no ha firmado el acta.</span>
                    </div>
                @endif
            </div>

            @if($acta->documentoIdentidadRuta)
                <div x-show="tab === 'identidad'" x-cloak>
                    <iframe src="{{ route('prestamos.acta.documento-identidad', $acta->id) }}"
                        class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                        title="Documento de identidad"></iframe>
                </div>
            @endif

            @if($acta->documentoExportacionRuta)
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
                <flux:button variant="primary" icon="check-circle"
                    wire:click="$set('showValidarFirmaModal', true)">
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
                Selecciona qué documentos debe corregir el investigador e indica el motivo.
            </flux:text>
            <flux:field>
                <flux:label>Documentos a devolver</flux:label>
                <div class="space-y-2">
                    <flux:checkbox wire:model="devolverActa" label="Acta firmada" />
                    <flux:checkbox wire:model="devolverIdentidad" label="Documento de identidad" />
                </div>
                <flux:error name="devolverActa" />
            </flux:field>
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

    {{-- Modal: confirmar validación de firma --}}
    <flux:modal wire:model="showValidarFirmaModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-bio-green/15 shrink-0">
                    <flux:icon name="check-badge" class="size-5 text-bio-green" />
                </div>
                <flux:heading size="lg">Validar firma del acta</flux:heading>
            </div>
            <flux:text class="text-text-secondary text-sm">
                Confirma que la firma digital es válida. El acta quedará cerrada y el proceso de préstamo concluirá.
            </flux:text>
            <div class="flex justify-end gap-2 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" icon="check-circle"
                    wire:click="validar"
                    wire:loading.attr="disabled"
                    wire:target="validar">
                    <flux:icon wire:loading wire:target="validar" name="arrow-path" class="animate-spin" />
                    Sí, validar firma
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
