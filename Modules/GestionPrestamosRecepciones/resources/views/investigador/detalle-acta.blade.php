<div class="space-y-5">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.mis-actas') }}">
            Mis actas
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $acta->numeroPrestamo ?? 'Detalle' }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if($successMessage)
        <flux:callout variant="success" icon="check-circle">{{ $successMessage }}</flux:callout>
    @endif

    {{-- Encabezado --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1" class="font-display font-mono">
                {{ $acta->numeroPrestamo }}
            </flux:heading>
            <p class="text-xs text-text-secondary mt-1">Acta de préstamo</p>
        </div>
        <x-gestionprestamosrecepciones::acta-status-badge :estado="$acta->estado" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Columna principal --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- Datos del acta --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                        <flux:icon name="clipboard-document" class="size-3.5" />
                    </div>
                    <flux:heading size="base" level="2" class="font-display">Información del acta</flux:heading>
                </div>
                <div class="p-5">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Tipo de préstamo</dt>
                            <dd class="font-medium text-text-primary mt-1 capitalize">{{ str_replace('_', ' ', $acta->tipoPrestamo) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Fecha de inicio</dt>
                            <dd class="font-medium text-text-primary mt-1">{{ $acta->fechaInicio?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-secondary uppercase tracking-wide">Fecha de vencimiento</dt>
                            <dd class="font-medium text-text-primary mt-1">{{ $acta->fechaFin?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        @if($acta->condicionesGenerales)
                            <div class="sm:col-span-2">
                                <dt class="text-xs text-text-secondary uppercase tracking-wide">Condiciones generales</dt>
                                <dd class="text-text-primary mt-1 leading-relaxed">{{ $acta->condicionesGenerales }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Callout contextual según estado --}}
            @if($acta->estado === 'pendiente_firma' && $acta->motivoDevolucion)
                <flux:callout variant="warning" icon="arrow-uturn-left">
                    <flux:heading size="sm">El acta fue devuelta para refirmar</flux:heading>
                    <flux:text class="mt-1 text-sm">{{ $acta->motivoDevolucion }}</flux:text>
                </flux:callout>
            @elseif($acta->estado === 'pendiente_firma' && $acta->pdfFirmadoRuta && !$successMessage)
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
                @if($acta->prestamoEstado === 'en_transito')
                    <flux:callout variant="success" icon="check-circle">
                        El acta ha sido validada. Los especímenes serán despachados a tu dirección. Cuando los recibas, deberás confirmar la recepción desde el detalle del préstamo.
                    </flux:callout>
                @elseif($acta->prestamoEstado === 'pendiente_aprobacion_verificacion')
                    <flux:callout variant="info" icon="clock">
                        Has reportado la recepción de los especímenes. El curador está revisando tu informe.
                    </flux:callout>
                @endif
            @endif

            {{-- Acciones --}}
            @if($acta->estado === 'pendiente_firma')
                <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                            <flux:icon name="pencil" class="size-3.5" />
                        </div>
                        <flux:heading size="base" level="2" class="font-display">Firma y documentos</flux:heading>
                    </div>
                    <div class="p-5">
                        <div class="flex flex-wrap gap-2">
                            @if($acta->pdfFirmadoRuta)
                                <flux:button variant="primary" icon="arrow-up-tray" size="sm"
                                    wire:click="$set('showIdentidadModal', true)">
                                    Subir documento de identidad
                                </flux:button>
                            @else
                                <flux:button variant="primary" icon="arrow-up-tray" size="sm"
                                    wire:click="$set('showUploadModal', true)">
                                    Adjuntar documentos
                                </flux:button>
                                <flux:button variant="outline" icon="pencil" size="sm"
                                    wire:click="$set('showFirmaCanvasModal', true)"
                                    :disabled="$pdfFirmado !== null">
                                    Firmar digitalmente
                                </flux:button>
                            @endif

                            @if($acta->prestamoId)
                                <flux:button variant="ghost" icon="archive-box" size="sm" wire:navigate
                                    href="{{ route('prestamos.investigador.prestamo.detalle', $acta->prestamoId) }}">
                                    Ver préstamo
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </div>
            @elseif($acta->prestamoId)
                <div class="flex">
                    <flux:button variant="ghost" icon="archive-box" size="sm" wire:navigate
                        href="{{ route('prestamos.investigador.prestamo.detalle', $acta->prestamoId) }}">
                        Ver préstamo
                    </flux:button>
                </div>
            @endif

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

    {{-- Visor de documentos --}}
    @if($acta->estado === 'pendiente_firma')
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <div class="flex items-center justify-between bg-bg-main px-4 py-2 border-b border-border">
                <flux:text class="text-sm font-medium text-text-primary">
                    {{ $acta->pdfFirmadoRuta ? 'Acta firmada digitalmente' : 'Acta de préstamo' }}
                </flux:text>
                <div class="flex items-center gap-1">
                    <a href="{{ route('prestamos.acta.embed', $acta->id) }}" target="_blank"
                        class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                        <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                        Abrir
                    </a>
                    @if($acta->pdfRuta && !$acta->pdfFirmadoRuta)
                        <a href="{{ route('prestamos.acta.pdf-original', $acta->id) }}"
                            download="acta-{{ $acta->numeroPrestamo }}.pdf"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                            <flux:icon name="arrow-down-tray" class="size-3.5" />
                            Descargar
                        </a>
                    @endif
                </div>
            </div>
            <iframe src="{{ route('prestamos.acta.embed', $acta->id) }}"
                class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                title="Acta de préstamo"></iframe>
        </div>

    @elseif(in_array($acta->estado, ['pendiente_validacion', 'validada']) && $acta->pdfFirmadoRuta)

        @php
            $esFirmaDigital = str_starts_with($acta->pdfFirmadoRuta ?? '', 'firmas-investigador/');
        @endphp

        @if($esFirmaDigital)
            <div x-data="{ tab: 'firmada' }" class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                <div class="flex items-center gap-1 border-b border-border bg-bg-main px-2">
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
                    <div class="ml-auto flex items-center gap-2">
                        <div x-show="tab === 'firmada'" class="flex items-center gap-1">
                            <a href="{{ route('prestamos.acta.embed', $acta->id) }}" target="_blank"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                                <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                                Abrir
                            </a>
                            <a href="{{ route('prestamos.acta.embed', $acta->id) }}?download=1" target="_blank"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                                <flux:icon name="arrow-down-tray" class="size-3.5" />
                                Descargar
                            </a>
                        </div>
                        @if($acta->documentoIdentidadRuta)
                            <div x-show="tab === 'identidad'" x-cloak class="flex items-center gap-1">
                                <a href="{{ route('prestamos.acta.documento-identidad', $acta->id) }}" target="_blank"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                                    <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                                    Abrir
                                </a>
                                <a href="{{ route('prestamos.acta.documento-identidad', $acta->id) }}" download="identidad-{{ $acta->numeroPrestamo }}.pdf"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                                    <flux:icon name="arrow-down-tray" class="size-3.5" />
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
                @if($acta->documentoIdentidadRuta)
                    <div x-show="tab === 'identidad'" x-cloak>
                        <iframe src="{{ route('prestamos.acta.documento-identidad', $acta->id) }}"
                            class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                            title="Documento de identidad"></iframe>
                    </div>
                @endif
            </div>

        @else
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
                    @if($acta->documentoExportacionRuta)
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
                                <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                                Abrir
                            </a>
                            <a href="{{ route('prestamos.acta.pdf-firmado', $acta->id) }}" download="acta-firmada-{{ $acta->numeroPrestamo }}.pdf"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                                <flux:icon name="arrow-down-tray" class="size-3.5" />
                                Descargar
                            </a>
                        </div>
                        <div x-show="tab === 'identidad'" x-cloak class="flex items-center gap-1">
                            <a href="{{ route('prestamos.acta.documento-identidad', $acta->id) }}" target="_blank"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                                <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                                Abrir
                            </a>
                            <a href="{{ route('prestamos.acta.documento-identidad', $acta->id) }}" download="identidad-{{ $acta->numeroPrestamo }}.pdf"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                                <flux:icon name="arrow-down-tray" class="size-3.5" />
                                Descargar
                            </a>
                        </div>
                        @if($acta->documentoExportacionRuta)
                            <div x-show="tab === 'exportacion'" x-cloak class="flex items-center gap-1">
                                <a href="{{ route('prestamos.acta.documento-exportacion', $acta->id) }}" target="_blank"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                                    <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                                    Abrir
                                </a>
                                <a href="{{ route('prestamos.acta.documento-exportacion', $acta->id) }}" download="exportacion-{{ $acta->numeroPrestamo }}.pdf"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded">
                                    <flux:icon name="arrow-down-tray" class="size-3.5" />
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
                @if($acta->documentoExportacionRuta)
                    <div x-show="tab === 'exportacion'" x-cloak>
                        <iframe src="{{ route('prestamos.acta.documento-exportacion', $acta->id) }}"
                            class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                            title="Documento de exportación"></iframe>
                    </div>
                @endif
            </div>
        @endif
    @endif

    {{-- Modales (sin cambios de estructura) --}}
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
                stopDraw() { this.drawing = false; this._currentEl = null; },
                limpiar() {
                    this.$refs.firmaCanvas.querySelectorAll('path').forEach(p => p.remove());
                    this.errorMensaje = '';
                },
                isEmpty() { return !this.$refs.firmaCanvas.querySelector('path'); },
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
                    if (this.isEmpty()) { this.errorMensaje = 'Dibuja tu firma antes de confirmar.'; return; }
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
                Dibuja tu firma en el recuadro de abajo con el mouse o el dedo.
            </flux:text>
            <div class="rounded-lg border-2 border-border bg-white overflow-hidden" style="cursor: crosshair;">
                <svg x-ref="firmaCanvas"
                    style="width: 100%; height: 220px; display: block; touch-action: none; cursor: crosshair; background: white;"
                    @pointerdown="startDraw($event)" @pointermove="draw($event)"
                    @pointerup="stopDraw()" @pointerleave="stopDraw()"></svg>
            </div>
            <p x-show="errorMensaje" x-text="errorMensaje" class="text-xs text-error text-center"></p>
            <p x-show="!errorMensaje" class="text-xs text-text-secondary text-center">— Área de firma —</p>
            <div class="flex items-center justify-between gap-2">
                <flux:button variant="ghost" size="sm" icon="arrow-path" @click="limpiar()">Limpiar</flux:button>
                <div class="flex gap-2">
                    <flux:button variant="ghost" wire:click="$set('showFirmaCanvasModal', false)">Cancelar</flux:button>
                    <flux:button variant="primary" icon="check" wire:loading.attr="disabled"
                        wire:target="firmarDigitalmente" @click="confirmar()">
                        <flux:icon wire:loading wire:target="firmarDigitalmente" name="arrow-path" class="animate-spin" />
                        Confirmar firma
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showUploadModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <flux:heading size="lg">Subir documentos para validación</flux:heading>
            <flux:text class="text-text-secondary text-sm">Debes adjuntar dos documentos en PDF (máximo 10 MB cada uno).</flux:text>
            <flux:field>
                <flux:label>Acta firmada (PDF)</flux:label>
                @if($pdfFirmado)
                    <div class="flex items-center justify-between rounded-lg border border-success/30 bg-success/5 px-3 py-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <flux:icon name="document-check" class="size-4 text-success shrink-0" />
                            <span class="text-sm text-text-primary truncate">{{ $pdfFirmado->getClientOriginalName() }}</span>
                        </div>
                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="limpiarPdfFirmado" />
                    </div>
                @else
                    <label class="flex items-center gap-2 cursor-pointer rounded-lg border border-dashed border-border px-3 py-2.5 hover:border-science-blue hover:bg-science-blue/5 transition-colors">
                        <flux:icon name="arrow-up-tray" class="size-4 text-text-secondary" />
                        <span class="text-sm text-text-secondary">Seleccionar archivo PDF</span>
                        <input type="file" wire:model="pdfFirmado" accept=".pdf" class="hidden" />
                    </label>
                @endif
                <div wire:loading wire:target="pdfFirmado" class="flex items-center gap-1.5 mt-1 text-xs text-text-secondary">
                    <flux:icon name="arrow-path" class="animate-spin size-3" /> Subiendo archivo...
                </div>
                <flux:error name="pdfFirmado" />
            </flux:field>
            <flux:field>
                <flux:label>Documento de identidad — cédula o pasaporte (PDF)</flux:label>
                @if($documentoIdentidad)
                    <div class="flex items-center justify-between rounded-lg border border-success/30 bg-success/5 px-3 py-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <flux:icon name="document-check" class="size-4 text-success shrink-0" />
                            <span class="text-sm text-text-primary truncate">{{ $documentoIdentidad->getClientOriginalName() }}</span>
                        </div>
                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="limpiarDocumentoIdentidad" />
                    </div>
                @else
                    <label class="flex items-center gap-2 cursor-pointer rounded-lg border border-dashed border-border px-3 py-2.5 hover:border-science-blue hover:bg-science-blue/5 transition-colors">
                        <flux:icon name="arrow-up-tray" class="size-4 text-text-secondary" />
                        <span class="text-sm text-text-secondary">Seleccionar archivo PDF</span>
                        <input type="file" wire:model="documentoIdentidad" accept=".pdf" class="hidden" />
                    </label>
                @endif
                <div wire:loading wire:target="documentoIdentidad" class="flex items-center gap-1.5 mt-1 text-xs text-text-secondary">
                    <flux:icon name="arrow-path" class="animate-spin size-3" /> Subiendo archivo...
                </div>
                <flux:error name="documentoIdentidad" />
            </flux:field>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="cancelarUploadActa">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="subirActa"
                    wire:loading.attr="disabled" wire:target="subirActa,pdfFirmado,documentoIdentidad">
                    <flux:icon wire:loading wire:target="subirActa" name="arrow-path" class="animate-spin" />
                    Subir documentos
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showIdentidadModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <flux:heading size="lg">Subir documento de identidad</flux:heading>
            <flux:text class="text-text-secondary text-sm">
                Tu firma digital ya fue registrada. Adjunta tu documento de identidad (cédula o pasaporte) en PDF (máximo 10 MB).
            </flux:text>
            <flux:field>
                <flux:label>Documento de identidad — cédula o pasaporte (PDF)</flux:label>
                @if($documentoIdentidadSolo)
                    <div class="flex items-center justify-between rounded-lg border border-success/30 bg-success/5 px-3 py-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <flux:icon name="document-check" class="size-4 text-success shrink-0" />
                            <span class="text-sm text-text-primary truncate">{{ $documentoIdentidadSolo->getClientOriginalName() }}</span>
                        </div>
                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="limpiarDocumentoIdentidadSolo" />
                    </div>
                @else
                    <label class="flex items-center gap-2 cursor-pointer rounded-lg border border-dashed border-border px-3 py-2.5 hover:border-science-blue hover:bg-science-blue/5 transition-colors">
                        <flux:icon name="arrow-up-tray" class="size-4 text-text-secondary" />
                        <span class="text-sm text-text-secondary">Seleccionar archivo PDF</span>
                        <input type="file" wire:model="documentoIdentidadSolo" accept=".pdf" class="hidden" />
                    </label>
                @endif
                <div wire:loading wire:target="documentoIdentidadSolo" class="flex items-center gap-1.5 mt-1 text-xs text-text-secondary">
                    <flux:icon name="arrow-path" class="animate-spin size-3" /> Subiendo archivo...
                </div>
                <flux:error name="documentoIdentidadSolo" />
            </flux:field>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="cancelarUploadIdentidad">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="subirDocumentoIdentidad"
                    wire:loading.attr="disabled" wire:target="subirDocumentoIdentidad,documentoIdentidadSolo">
                    <flux:icon wire:loading wire:target="subirDocumentoIdentidad" name="arrow-path" class="animate-spin" />
                    Subir documento
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
