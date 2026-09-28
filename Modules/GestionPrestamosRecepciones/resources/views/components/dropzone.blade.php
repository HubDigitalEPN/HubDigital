@props([
    'nombre',
    'propiedad',
    'requerido' => false,
    'cargado' => false,
    'estadoFirma' => null,
    'estadoArchivo' => null,
    'rechazo' => null,
    'validandoFirma' => false,
    'archivoNombre' => null,
    'plantilla' => null,
    'ayuda' => null,
])

<div
    wire:ignore.self
    x-data="{
        progreso: 0,
        subiendo: false,
        errorSubida: {{ $rechazo ? 'true' : 'false' }},
        rechazo: @js($rechazo),
        arrastrando: false,
        cargado: {{ $cargado ? 'true' : 'false' }},
        firmaValidada: {{ in_array($estadoFirma, ['firmado', 'firmado_sin_revocacion'], true) ? 'true' : 'false' }},
        archivoEstado: @js($estadoArchivo),
        nombreArchivo: @js($archivoNombre),
        soltar(e) {
            this.arrastrando = false;
            if (this.cargado) return;
            const archivo = e.dataTransfer.files[0];
            if (!archivo) return;
            if (archivo.type !== 'application/pdf') {
                this.errorSubida = true;
                return;
            }
            const input = this.$refs.fileInput;
            const dt = new DataTransfer();
            dt.items.add(archivo);
            input.files = dt.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            this.nombreArchivo = archivo.name;
        }
    }"
    x-on:livewire-upload-start="subiendo = true; progreso = 0; errorSubida = false"
    x-on:livewire-upload-progress="progreso = $event.detail.progress"
    x-on:livewire-upload-finish="subiendo = false; progreso = 100"
    x-on:livewire-upload-error="subiendo = false; progreso = 0; errorSubida = true"
    x-on:documento-aceptado.window="if ($event.detail.propiedad === '{{ $propiedad }}') { cargado = true; archivoEstado = 'valido'; firmaValidada = false; errorSubida = false; rechazo = null }"
    x-on:archivo-validado.window="if ($event.detail.nombre === @js($nombre)) { archivoEstado = $event.detail.estado; rechazo = ['valido', 'analizando'].includes(archivoEstado) ? null : { estado: archivoEstado, mensaje: $event.detail.mensaje }; errorSubida = !!rechazo }"
    x-on:firma-actualizada.window="if ($event.detail.nombre === @js($nombre)) firmaValidada = ['firmado', 'firmado_sin_revocacion'].includes($event.detail.estado)"
    x-on:documento-rechazado.window="if ($event.detail.propiedad === '{{ $propiedad }}') { cargado = false; progreso = 0; errorSubida = true; firmaValidada = false; archivoEstado = $event.detail.estado; rechazo = { estado: $event.detail.estado, mensaje: $event.detail.mensaje } }"
    x-on:click="if (!cargado) $refs.fileInput.click()"
    x-on:dragover.prevent="if (!cargado) arrastrando = true"
    x-on:dragleave.prevent="arrastrando = false"
    x-on:drop.prevent="soltar($event)"
    class="flex flex-col items-center gap-3 text-center sm:flex-row sm:items-center sm:gap-3 sm:text-left rounded-lg border-2 p-3 transition-all"
    x-bind:class="cargado
        ? (archivoEstado === 'valido' && firmaValidada && !rechazo ? 'border-success/40 bg-success/5 cursor-default' : 'border-error/60 bg-error/5 cursor-default')
        : arrastrando
            ? '!border-science-blue !bg-science-blue/10 border-dashed cursor-pointer group'
            : 'border-dashed cursor-pointer group {{ $requerido ? 'border-error/60 bg-error/5' : 'border-border bg-bg-main' }} hover:border-science-blue hover:bg-science-blue/5'"
>
    {{-- Icono --}}
    <div class="shrink-0 size-11 rounded-lg border border-border bg-surface flex items-center justify-center shadow-sm">
        <flux:icon x-show="cargado && archivoEstado === 'valido' && firmaValidada" x-cloak name="document-check" class="size-5 text-success" />
        <flux:icon x-show="cargado && !(archivoEstado === 'valido' && firmaValidada)" x-cloak name="document-text" class="size-5 text-error" />
        <flux:icon x-show="!cargado" name="arrow-up-tray" class="size-5 text-text-secondary group-hover:text-science-blue transition-colors" />
    </div>

    {{-- Body --}}
    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-1.5">
            <p class="text-sm font-semibold text-text-primary leading-snug">{{ $nombre }}</p>
            @if($ayuda)
                <div
                    x-data="{
                        infoAbierta: false,
                        x: 0,
                        y: 0,
                        abrir() {
                            const r = this.$el.getBoundingClientRect();
                            this.x = Math.max(8, r.right - 256);
                            this.y = r.bottom + 6;
                            this.infoAbierta = true;
                        }
                    }"
                    x-on:mouseenter="abrir()"
                    x-on:mouseleave="infoAbierta = false"
                    class="shrink-0"
                >
                    <span
                        x-on:click.stop="infoAbierta ? infoAbierta = false : abrir()"
                        :class="infoAbierta ? 'text-science-blue' : 'text-text-secondary'"
                        class="-m-2 flex cursor-help p-2 transition-colors duration-200"
                        aria-label="Más información sobre este documento"
                    >
                        <flux:icon name="information-circle" class="size-4" />
                    </span>

                    <template x-teleport="body">
                        <div
                            x-show="infoAbierta"
                            x-cloak
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.97]"
                            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                            x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.97]"
                            :style="'position:fixed;left:'+x+'px;top:'+y+'px;z-index:9999'"
                            class="w-64 max-w-[calc(100dvi-2rem)] origin-top-right overflow-hidden rounded-lg bg-surface shadow-lg ring-1 ring-science-blue/30 sm:w-72"
                        >
                            <div class="flex gap-2.5 p-3">
                                <div class="flex size-7 shrink-0 items-center justify-center rounded-full bg-science-blue/15 ring-1 ring-science-blue/20">
                                    <flux:icon name="light-bulb" class="size-4 text-science-blue" />
                                </div>
                                <div class="space-y-0.5">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-science-blue">¿Qué subo aquí?</p>
                                    <p class="text-xs text-text-secondary leading-relaxed">{{ $ayuda }}</p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            @endif
        </div>

        <div class="flex items-center gap-2 mt-1 flex-wrap">
            <template x-if="cargado && !rechazo">
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold" x-bind:class="archivoEstado === 'valido' && firmaValidada ? 'bg-success/15 text-success' : (['valido', 'analizando'].includes(archivoEstado) ? 'bg-blue-navy/10 text-blue-navy' : 'bg-error/10 text-error')">
                    <flux:icon x-show="archivoEstado === 'valido' && firmaValidada" name="check" class="size-2.5" />
                    <flux:icon x-show="archivoEstado === 'analizando'" name="arrow-path" class="size-2.5 animate-spin" />
                    <span x-text="archivoEstado === 'valido' ? 'Cargado' : (archivoEstado === 'analizando' ? 'Revisando archivo' : 'Documento no válido')"></span>
                </span>
            </template>
            @if($cargado)
                @php
                    $etiquetaFirma = match($estadoFirma) {
                        'validando' => 'Validando firma',
                        'firmado' => 'Firma validada',
                        'firmado_sin_revocacion' => 'Firma validada',
                        'almacen_incompleto' => 'Almacén pendiente',
                        'sin_firma' => 'Sin firma',
                        'firma_invalida' => 'Firma inválida',
                        'certificado_caducado' => 'Certificado caducado',
                        'certificado_aun_no_vigente' => 'Certificado aún no vigente',
                        'certificado_revocado' => 'Certificado revocado',
                        'certificado_no_confiable' => 'Certificado no confiable',
                        'revocacion_no_comprobable' => 'Revocación no comprobada',
                        'verificacion_no_disponible' => 'Error de verificación',
                        default => $validandoFirma ? 'Validando firma' : 'Firma por validar',
                    };
                    $firmaCorrecta = in_array($estadoFirma, ['firmado', 'firmado_sin_revocacion'], true);
                    $firmaConError = $estadoFirma !== null && !in_array($estadoFirma, ['firmado', 'firmado_sin_revocacion', 'validando'], true);
                @endphp
                <span x-show="cargado && !rechazo && archivoEstado === 'valido'" class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $firmaCorrecta ? 'bg-success/15 text-success' : ($firmaConError ? 'bg-error/10 text-error' : 'bg-blue-navy/10 text-blue-navy') }}">
                    @if($firmaCorrecta)
                        <flux:icon name="check" class="size-2.5" />
                    @endif
                    @if($estadoFirma === 'validando')
                        <flux:icon name="arrow-path" class="size-2.5 animate-spin" />
                    @endif
                    {{ $etiquetaFirma }}
                </span>
                @if($estadoFirma === 'firmado_sin_revocacion')
                    <span x-show="cargado" class="text-[10px] text-text-secondary">Revocación no comprobada</span>
                @endif
            @endif
            <span x-show="cargado" x-cloak class="text-xs text-text-secondary">
                @if($estadoArchivo === 'tipo_incorrecto')
                    El contenido no corresponde a este tipo de documento. Elimina el archivo y carga el correcto.
                @elseif($estadoArchivo === 'revision_fallida')
                    No se pudo revisar el contenido. Elimina el archivo y vuelve a cargarlo.
                @else
                    Haz clic en eliminar para subir otro archivo.
                @endif
            </span>

            @if(!$cargado)
                @if($requerido)
                    <span x-show="!cargado" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-error/15 text-error">
                        Requerido
                    </span>
                @else
                    <span x-show="!cargado" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-border/60 text-text-secondary">
                        Opcional
                    </span>
                @endif
            @endif

            <span x-show="!cargado && !subiendo && !nombreArchivo" class="text-xs text-text-secondary">
                Solo PDF · Haz clic o arrastra aquí
            </span>
        </div>

        {{-- Enlace a plantilla de ejemplo --}}
        @if($plantilla)
            <a
                x-show="!cargado"
                href="{{ $plantilla }}"
                download
                x-on:click.stop
                class="inline-flex items-center gap-1 text-xs text-science-blue hover:text-blue-navy transition-colors mt-1"
            >
                <flux:icon name="arrow-down-tray" class="size-3" />
                Descargar ejemplo de referencia
            </a>
        @endif

        <div x-show="nombreArchivo && nombreArchivo.trim().length > 0" x-cloak class="flex items-center gap-1 mt-1">
            <flux:icon name="document-text" class="size-3 text-text-secondary shrink-0" />
            <span x-text="nombreArchivo" class="text-xs font-medium text-text-primary truncate max-w-sm"></span>
        </div>

        {{-- Progress bar --}}
        <div x-show="!cargado && (subiendo || progreso > 0)" x-cloak class="mt-2 h-1.5 w-full rounded-full bg-border overflow-hidden">
            <div
                class="h-full rounded-full transition-all duration-300"
                :class="progreso === 100 ? 'bg-success' : 'bg-science-blue'"
                :style="'width: ' + progreso + '%'"
            ></div>
        </div>
        <span x-show="!cargado && (subiendo || progreso > 0)" x-cloak x-text="progreso + '%'" class="text-xs text-text-secondary mt-0.5 block"></span>

        <p x-show="rechazo" x-cloak role="alert" class="mt-2 text-sm font-semibold text-error"
            x-text="rechazo?.estado === 'tipo_incorrecto' ? 'Documento de tipo incorrecto o no identificable' : 'Documento rechazado'"></p>
        <p x-show="rechazo" x-cloak class="mt-1 text-xs text-error" x-text="rechazo?.mensaje"></p>
        <p x-show="errorSubida && !rechazo" class="text-xs text-error mt-1">Error al subir. Inténtalo de nuevo.</p>
        <flux:error :name="$propiedad" />
    </div>

    {{-- Botón eliminar --}}
    <button
        x-show="cargado"
        x-cloak
        type="button"
        wire:click.stop="eliminarDocumento('{{ $nombre }}')"
        wire:loading.attr="disabled"
        wire:target="eliminarDocumento('{{ $nombre }}')"
        class="shrink-0 self-end flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-error border border-error/30 bg-error/5 hover:bg-error/15 transition-colors"
        x-on:click.stop="cargado = false; nombreArchivo = null; progreso = 0; rechazo = null; errorSubida = false"
    >
        <flux:icon wire:loading wire:target="eliminarDocumento('{{ $nombre }}')" name="arrow-path" class="size-3.5 animate-spin" />
        <flux:icon wire:loading.remove wire:target="eliminarDocumento('{{ $nombre }}')" name="trash" class="size-3.5" />
        Eliminar
    </button>

    {{-- Hidden file input (siempre en DOM) --}}
    <input
        type="file"
        wire:model="{{ $propiedad }}"
        class="hidden"
        x-ref="fileInput"
        accept=".pdf"
        x-on:click.stop
        x-on:change="nombreArchivo = $event.target.files[0]?.name ?? null"
    />

</div>
