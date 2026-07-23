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
                            'ActaFirmadaPorCurador'        => 'Firmada por el curador',
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
                        <a href="{{ route('prestamos.acta.descargar-pdf', $acta->id) }}?sin_firma=1" target="_blank"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                            <flux:icon name="arrow-top-right-on-square" class="size-3.5" /> Abrir
                        </a>
                        <a href="{{ route('prestamos.acta.descargar-pdf', $acta->id) }}?sin_firma=1" download="acta-{{ $acta->numeroPrestamo }}.pdf"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                            <flux:icon name="arrow-down-tray" class="size-3.5" /> Descargar
                        </a>
                    </div>

                    @php $esFirmaDigital = str_starts_with($acta->pdfFirmadoRuta ?? '', 'firmas-investigador/'); @endphp
                    <div x-show="tab === 'firmada'" x-cloak class="flex items-center gap-1">
                        <a href="{{ $esFirmaDigital ? route('prestamos.acta.descargar-pdf', $acta->id) : route('prestamos.acta.pdf-firmado', $acta->id) }}" target="_blank"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs text-text-secondary hover:text-text-primary rounded transition-colors">
                            <flux:icon name="arrow-top-right-on-square" class="size-3.5" /> Abrir
                        </a>
                        @if($acta->pdfFirmadoRuta)
                            @if($esFirmaDigital)
                                <a href="{{ route('prestamos.acta.descargar-pdf', $acta->id) }}" download="acta-firmada-{{ $acta->numeroPrestamo }}.pdf"
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
                <iframe src="{{ route('prestamos.acta.descargar-pdf', $acta->id) }}?sin_firma=1"
                    class="w-full" style="height: calc(100vh - 310px); min-height: 520px;"
                    title="Acta original"></iframe>
            </div>

            <div x-show="tab === 'firmada'" x-cloak>
                @if($acta->pdfFirmadoRuta)
                    @if($esFirmaDigital)
                        <iframe src="{{ route('prestamos.acta.descargar-pdf', $acta->id) }}"
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
            @php $investigadorFirmoEnCanvas = str_starts_with($acta->pdfFirmadoRuta ?? '', 'firmas-investigador/'); @endphp
            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                <flux:button variant="ghost" icon="arrow-uturn-left" class="w-full sm:w-auto"
                    wire:click="$set('showMotivoModal', true)">
                    Devolver para refirmar
                </flux:button>
                <flux:button variant="ghost" icon="arrow-up-tray" class="w-full sm:w-auto"
                    wire:click="$set('showUploadModal', true)">
                    Subir acta firmada
                </flux:button>
                @if($investigadorFirmoEnCanvas)
                    <flux:button variant="primary" icon="pencil-square" class="w-full sm:w-auto"
                        wire:click="$set('showFirmaCanvasModal', true)">
                        Firmar en canvas
                    </flux:button>
                @endif
            </div>
            @unless($investigadorFirmoEnCanvas)
                <p class="text-xs text-text-secondary text-right">
                    El investigador subió su acta firmada como archivo; adjunta tu versión firmada para validar.
                </p>
            @endunless
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

    {{-- Modal: firma en canvas del curador --}}
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
                        await $wire.call('firmarConCanvas');
                    } catch (e) {
                        this.errorMensaje = e.message ?? 'Ocurrió un error al procesar la firma.';
                    }
                }
            }"
            @domain-error.window="errorMensaje = $event.detail.message"
            class="space-y-4 p-2"
        >
            <flux:heading size="lg">Firmar y validar el acta</flux:heading>
            <flux:text class="text-text-secondary text-sm">
                Dibuja tu firma en el recuadro. Al confirmar, el acta quedará validada y el proceso de préstamo continuará.
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
                        wire:target="firmarConCanvas" @click="confirmar()">
                        <flux:icon wire:loading wire:target="firmarConCanvas" name="arrow-path" class="animate-spin" />
                        Confirmar firma
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: subir acta firmada por el curador --}}
    <flux:modal wire:model="showUploadModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <flux:heading size="lg">Subir acta firmada</flux:heading>
            <flux:text class="text-text-secondary text-sm">
                Adjunta el acta firmada por ti en PDF (máximo 10 MB). Al validarla, el proceso de préstamo continuará.
            </flux:text>
            <flux:field>
                <flux:label>Acta firmada (PDF)</flux:label>
                @if($pdfFirmadoCurador)
                    <div class="flex items-center justify-between rounded-lg border border-success/30 bg-success/5 px-3 py-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <flux:icon name="document-check" class="size-4 text-success shrink-0" />
                            <span class="text-sm text-text-primary truncate">{{ $pdfFirmadoCurador->getClientOriginalName() }}</span>
                        </div>
                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="limpiarPdfFirmadoCurador" />
                    </div>
                @else
                    <label class="flex items-center gap-2 cursor-pointer rounded-lg border border-dashed border-border px-3 py-2.5 hover:border-science-blue hover:bg-science-blue/5 transition-colors">
                        <flux:icon name="arrow-up-tray" class="size-4 text-text-secondary" />
                        <span class="text-sm text-text-secondary">Seleccionar archivo PDF</span>
                        <input type="file" wire:model="pdfFirmadoCurador" accept=".pdf" class="hidden" />
                    </label>
                @endif
                <div wire:loading wire:target="pdfFirmadoCurador" class="flex items-center gap-1.5 mt-1 text-xs text-text-secondary">
                    <flux:icon name="arrow-path" class="animate-spin size-3" /> Subiendo archivo...
                </div>
                <flux:error name="pdfFirmadoCurador" />
            </flux:field>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="cancelarUploadActa">Cancelar</flux:button>
                <flux:button variant="primary" icon="check-circle" wire:click="subirActaFirmada"
                    wire:loading.attr="disabled" wire:target="subirActaFirmada,pdfFirmadoCurador">
                    <flux:icon wire:loading wire:target="subirActaFirmada" name="arrow-path" class="animate-spin" />
                    Validar acta
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
