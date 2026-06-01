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
                @elseif($acta->estado === 'pendiente_firma' && $acta->pdf_firmado_ruta && !$successMessage)
                    <flux:callout variant="success" icon="check-circle">
                        <flux:heading size="sm">Firma digital registrada</flux:heading>
                        <flux:text class="mt-1 text-sm">
                            Sube tu documento de identidad (cédula o pasaporte) para completar el proceso.
                        </flux:text>
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
                    @endif
                @endif

                <div class="flex flex-wrap gap-2">
                    @if($acta->estado === 'pendiente_firma' && $acta->pdf_firmado_ruta)
                        {{-- Firma digital ya hecha: solo falta el documento de identidad --}}
                        <flux:button variant="primary" icon="arrow-up-tray" size="sm"
                            wire:click="$set('showIdentidadModal', true)">
                            Subir documento de identidad
                        </flux:button>
                    @elseif($acta->estado === 'pendiente_firma')
                        <flux:button variant="primary" icon="arrow-up-tray" size="sm"
                            wire:click="$set('showUploadModal', true)">
                            Adjuntar documentos
                        </flux:button>
                        <flux:button variant="outline" icon="pencil" size="sm"
                            wire:click="$set('showFirmaCanvasModal', true)">
                            Firmar digitalmente
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
                        'ActaFirmadaDigitalmente'        => 'Acta firmada digitalmente',
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
        {{-- En pendiente_firma: muestra el acta (con firma si ya se firmó digitalmente) --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <div class="flex items-center justify-between bg-bg-main px-4 py-2 border-b border-border">
                <flux:text class="text-sm font-medium text-text-primary">
                    {{ $acta->pdf_firmado_ruta ? 'Acta firmada digitalmente' : 'Acta de préstamo' }}
                </flux:text>
                <div class="flex items-center gap-1">
                    <a href="{{ route('prestamos.acta.embed', $acta->id) }}" target="_blank"
                        class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                        <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                        </svg>
                        Abrir
                    </a>
                    @if($acta->pdf_ruta && !$acta->pdf_firmado_ruta)
                        <a href="{{ route('prestamos.acta.pdf-original', $acta->id) }}"
                            download="acta-{{ $acta->numero_prestamo }}.pdf"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                            <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Descargar
                        </a>
                    @endif
                </div>
            </div>
            <iframe src="{{ route('prestamos.acta.embed', $acta->id) }}"
                class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                title="Acta de préstamo"></iframe>
        </div>

    @elseif(in_array($acta->estado, ['pendiente_validacion', 'validada']) && $acta->pdf_firmado_ruta)

        @php
            // La firma digital guarda la imagen en 'firmas-investigador/';
            // la firma subida (PDF upload) guarda en 'actas-firmadas/'.
            $esFirmaDigital = str_starts_with($acta->pdf_firmado_ruta ?? '', 'firmas-investigador/');
        @endphp

        @if($esFirmaDigital)
            {{-- Firma digital: muestra ver-acta con firma embebida + doc. identidad si existe --}}
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

                    <div class="ml-auto flex items-center gap-2">
                        <div x-show="tab === 'firmada'" class="flex items-center gap-1">
                            <a href="{{ route('prestamos.acta.embed', $acta->id) }}" target="_blank"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                                <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                </svg>
                                Abrir
                            </a>
                            <a href="{{ route('prestamos.acta.embed', $acta->id) }}?download=1" target="_blank"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                                <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                                Descargar
                            </a>
                        </div>
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
                    </div>
                </div>

                <div x-show="tab === 'firmada'">
                    <iframe src="{{ route('prestamos.acta.embed', $acta->id) }}"
                        class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                        title="Acta firmada digitalmente"></iframe>
                </div>

                @if($acta->documento_identidad_ruta)
                    <div x-show="tab === 'identidad'" x-cloak>
                        <iframe src="{{ route('prestamos.acta.documento-identidad', $acta->id) }}"
                            class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                            title="Documento de identidad"></iframe>
                    </div>
                @endif

            </div>
        @else
            {{-- Firma subida (PDF): muestra los documentos enviados con pestañas --}}
            <div x-data="{ tab: 'firmada' }" class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">

                <div class="flex items-center gap-1 border-b border-border bg-bg-main px-2">
                    <button @click="tab = 'firmada'"
                        :class="tab === 'firmada' ? 'border-b-2 border-science-blue text-science-blue' : 'text-text-secondary hover:text-text-primary'"
                        class="px-3 py-2.5 text-sm font-medium transition-colors whitespace-nowrap">
                        Acta firmada
                    </button>
                    <button @click="tab = 'identidad'"
                        :class="tab === 'identidad' ? 'border-b-2 border-science-blue text-science-blue' : 'text-text-secondary hover:text-text-primary'"
                        class="px-3 py-2.5 text-sm font-medium transition-colors whitespace-nowrap">
                        Doc. de identidad
                    </button>
                    @if($acta->documento_exportacion_ruta)
                        <button @click="tab = 'exportacion'"
                            :class="tab === 'exportacion' ? 'border-b-2 border-science-blue text-science-blue' : 'text-text-secondary hover:text-text-primary'"
                            class="px-3 py-2.5 text-sm font-medium transition-colors whitespace-nowrap">
                            Doc. Ministerio
                        </button>
                    @endif

                    <div class="ml-auto flex items-center gap-2">
                        <div x-show="tab === 'firmada'" class="flex items-center gap-1">
                            <a href="{{ route('prestamos.acta.pdf-firmado', $acta->id) }}" target="_blank"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                                <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                </svg>
                                Abrir
                            </a>
                            <a href="{{ route('prestamos.acta.pdf-firmado', $acta->id) }}" download="acta-firmada-{{ $acta->numero_prestamo }}.pdf"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                                <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                                Descargar
                            </a>
                        </div>
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

                <div x-show="tab === 'firmada'">
                    <iframe src="{{ route('prestamos.acta.pdf-firmado', $acta->id) }}"
                        class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                        title="Acta firmada"></iframe>
                </div>

                <div x-show="tab === 'identidad'" x-cloak>
                    <iframe src="{{ route('prestamos.acta.documento-identidad', $acta->id) }}"
                        class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                        title="Documento de identidad"></iframe>
                </div>

                @if($acta->documento_exportacion_ruta)
                    <div x-show="tab === 'exportacion'" x-cloak>
                        <iframe src="{{ route('prestamos.acta.documento-exportacion', $acta->id) }}"
                            class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                            title="Documento de exportación"></iframe>
                    </div>
                @endif

            </div>
        @endif
    @endif

    {{-- Modal: firma digital mediante canvas --}}
    <flux:modal wire:model="showFirmaCanvasModal" class="w-full max-w-2xl" :dismissible="false">
        <div
            x-data="{
                drawing: false,
                _currentEl: null,
                _currentD: '',
                _lastPt: null,
                errorMensaje: '',

                init() {},

                startDraw(event) {
                    event.preventDefault();
                    const svg = this.$refs.firmaCanvas;
                    svg.setPointerCapture(event.pointerId);
                    this.drawing = true;
                    const rect = svg.getBoundingClientRect();
                    const p = { x: event.clientX - rect.left, y: event.clientY - rect.top };
                    const el = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                    el.setAttribute('stroke', '#1e1e1e');
                    el.setAttribute('stroke-width', '2');
                    el.setAttribute('fill', 'none');
                    el.setAttribute('stroke-linecap', 'round');
                    el.setAttribute('stroke-linejoin', 'round');
                    this._currentEl = el;
                    this._currentD = `M ${p.x} ${p.y}`;
                    this._lastPt = p;
                    el.setAttribute('d', this._currentD);
                    svg.appendChild(el);
                },

                draw(event) {
                    if (!this.drawing || !this._currentEl) return;
                    event.preventDefault();
                    const svg = this.$refs.firmaCanvas;
                    const rect = svg.getBoundingClientRect();
                    const p = { x: event.clientX - rect.left, y: event.clientY - rect.top };
                    const mid = { x: (this._lastPt.x + p.x) / 2, y: (this._lastPt.y + p.y) / 2 };
                    this._currentD += ` Q ${this._lastPt.x} ${this._lastPt.y} ${mid.x} ${mid.y}`;
                    this._currentEl.setAttribute('d', this._currentD);
                    this._lastPt = p;
                },

                stopDraw() {
                    this.drawing = false;
                    this._currentEl = null;
                },

                limpiar() {
                    const svg = this.$refs.firmaCanvas;
                    svg.querySelectorAll('path').forEach(p => p.remove());
                    this.errorMensaje = '';
                },

                isEmpty() {
                    return !this.$refs.firmaCanvas.querySelector('path');
                },

                toDataURL() {
                    const svg = this.$refs.firmaCanvas;
                    const rect = svg.getBoundingClientRect();
                    const ratio = Math.max(1, window.devicePixelRatio || 1);
                    const canvas = document.createElement('canvas');
                    canvas.width  = Math.round(rect.width  * ratio);
                    canvas.height = Math.round(rect.height * ratio);
                    const ctx = canvas.getContext('2d');
                    ctx.scale(ratio, ratio);
                    ctx.fillStyle = 'white';
                    ctx.fillRect(0, 0, rect.width, rect.height);
                    ctx.strokeStyle = '#1e1e1e';
                    ctx.lineWidth   = 2;
                    ctx.lineCap     = 'round';
                    ctx.lineJoin    = 'round';
                    svg.querySelectorAll('path').forEach(path => {
                        ctx.stroke(new Path2D(path.getAttribute('d') ?? ''));
                    });
                    return canvas.toDataURL('image/png');
                },

                async confirmar() {
                    this.errorMensaje = '';
                    if (this.isEmpty()) {
                        this.errorMensaje = 'Dibuja tu firma antes de confirmar.';
                        return;
                    }
                    try {
                        const dataUrl = this.toDataURL();
                        await $wire.set('firmaBase64', dataUrl);
                        await $wire.call('firmarDigitalmente');
                    } catch (e) {
                        this.errorMensaje = e.message ?? 'Ocurrió un error al procesar la firma.';
                    }
                }
            }"
            @domain-error.window="errorMensaje = $event.detail.message"
            class="space-y-4 p-2"
        >
            <flux:heading size="lg">Firmar acta digitalmente</flux:heading>
            <flux:text class="text-text-secondary text-sm">
                Dibuja tu firma en el recuadro de abajo con el mouse o el dedo. Una vez confirmada,
                el sistema generará el PDF del acta con tu firma embebida.
            </flux:text>

            <div class="rounded-lg border-2 border-border bg-white overflow-hidden" style="cursor: crosshair;">
                <svg
                    x-ref="firmaCanvas"
                    style="width: 100%; height: 220px; display: block; touch-action: none; cursor: crosshair; background: white;"
                    @pointerdown="startDraw($event)"
                    @pointermove="draw($event)"
                    @pointerup="stopDraw()"
                    @pointerleave="stopDraw()"
                ></svg>
            </div>
            <p x-show="errorMensaje" x-text="errorMensaje" class="text-xs text-error text-center"></p>
            <p x-show="!errorMensaje" class="text-xs text-text-secondary text-center">— Área de firma —</p>

            <div class="flex items-center justify-between gap-2">
                <flux:button variant="ghost" size="sm" icon="arrow-path" @click="limpiar()">
                    Limpiar
                </flux:button>
                <div class="flex gap-2">
                    <flux:button variant="ghost" wire:click="$set('showFirmaCanvasModal', false)">
                        Cancelar
                    </flux:button>
                    <flux:button
                        variant="primary"
                        icon="check"
                        wire:loading.attr="disabled"
                        wire:target="firmarDigitalmente"
                        @click="confirmar()"
                    >
                        <flux:icon wire:loading wire:target="firmarDigitalmente" name="arrow-path" class="animate-spin" />
                        Confirmar firma
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

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

    {{-- Modal: subir solo documento de identidad (tras firma digital) --}}
    <flux:modal wire:model="showIdentidadModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <flux:heading size="lg">Subir documento de identidad</flux:heading>
            <flux:text class="text-text-secondary text-sm">
                Tu firma digital ya fue registrada. Adjunta tu documento de identidad
                (cédula o pasaporte) en PDF para completar el proceso (máximo 10 MB).
            </flux:text>

            <flux:field>
                <flux:label>Documento de identidad — cédula o pasaporte (PDF)</flux:label>
                <input type="file" wire:model="documentoIdentidadSolo" accept=".pdf"
                    class="block w-full text-sm text-text-secondary
                           file:mr-3 file:py-2 file:px-4 file:rounded-lg
                           file:border-0 file:text-sm file:font-medium
                           file:bg-science-blue file:text-white
                           hover:file:bg-blue-700" />
                <div wire:loading wire:target="documentoIdentidadSolo" class="flex items-center gap-1.5 mt-1 text-xs text-text-secondary">
                    <flux:icon name="arrow-path" class="animate-spin size-3" />
                    Subiendo archivo...
                </div>
                <flux:error name="documentoIdentidadSolo" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showIdentidadModal', false)">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="subirDocumentoIdentidad"
                    wire:loading.attr="disabled" wire:target="subirDocumentoIdentidad,documentoIdentidadSolo">
                    <flux:icon wire:loading wire:target="subirDocumentoIdentidad" name="arrow-path" class="animate-spin" />
                    Subir documento
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
