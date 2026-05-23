@props([
    'nombre',
    'propiedad',
    'requerido' => false,
    'cargado' => false,
    'archivoNombre' => null,
    'plantilla' => null,
])

<div
    x-data="{
        progreso: 0,
        subiendo: false,
        errorSubida: false,
        arrastrando: false,
        cargado: {{ $cargado ? 'true' : 'false' }},
        nombreArchivo: {{ $archivoNombre ? "'" . addslashes($archivoNombre) . "'" : 'null' }},
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
    x-on:livewire-upload-finish="subiendo = false; progreso = 100; cargado = true"
    x-on:livewire-upload-error="subiendo = false; progreso = 0; errorSubida = true"
    x-on:click="if (!cargado) $refs.fileInput.click()"
    x-on:dragover.prevent="if (!cargado) arrastrando = true"
    x-on:dragleave.prevent="arrastrando = false"
    x-on:drop.prevent="soltar($event)"
    class="flex items-center gap-4 rounded-lg border-2 p-4 transition-all
        {{ $cargado
            ? 'border-success/40 bg-success/5 cursor-default'
            : 'border-dashed cursor-pointer group ' . ($requerido ? 'border-warning/60 bg-warning/5' : 'border-border bg-bg-main') . ' hover:border-science-blue hover:bg-science-blue/5'
        }}"
    x-bind:class="arrastrando && !cargado ? '!border-science-blue !bg-science-blue/10' : ''"
>
    {{-- Icono --}}
    <div class="shrink-0 size-11 rounded-lg border border-border bg-surface flex items-center justify-center shadow-sm">
        @if($cargado)
            <flux:icon name="document-check" class="size-5 text-success" />
        @else
            <flux:icon name="arrow-up-tray" class="size-5 text-text-secondary group-hover:text-science-blue transition-colors" />
        @endif
    </div>

    {{-- Body --}}
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-text-primary leading-snug">{{ $nombre }}</p>

        <div class="flex items-center gap-2 mt-1 flex-wrap">
            @if($cargado)
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-success/15 text-success">
                    <flux:icon name="check" class="size-2.5" />
                    Cargado
                </span>
                <span class="text-xs text-text-secondary">Haz clic en eliminar para subir otro archivo.</span>
            @elseif($requerido)
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-error/15 text-error">
                    Requerido
                </span>
            @else
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-border/60 text-text-secondary">
                    Opcional
                </span>
            @endif

            <span x-show="!subiendo && !nombreArchivo" class="text-xs text-text-secondary">
                Solo PDF
            </span>
        </div>

        {{-- Instrucción de acción --}}
        @if(!$cargado)
            <p x-show="!subiendo && !nombreArchivo" class="text-xs text-text-secondary mt-1.5">
                Haz clic o arrastra tu archivo aquí
            </p>
        @endif

        {{-- Enlace a plantilla de ejemplo --}}
        @if($plantilla && !$cargado)
            <a
                href="{{ $plantilla }}"
                download
                x-on:click.stop
                class="inline-flex items-center gap-1 text-xs text-science-blue hover:text-blue-navy transition-colors mt-1"
            >
                <flux:icon name="arrow-down-tray" class="size-3" />
                Descargar ejemplo de referencia
            </a>
        @endif

        <div x-show="nombreArchivo" class="flex items-center gap-1 mt-1">
            <flux:icon name="document-text" class="size-3 text-text-secondary shrink-0" />
            <span x-text="nombreArchivo" class="text-xs font-medium text-text-primary truncate max-w-sm"></span>
        </div>

        {{-- Progress bar (solo si no está cargado aún) --}}
        @if(!$cargado)
            <div x-show="subiendo || progreso > 0" class="mt-2 h-1.5 w-full rounded-full bg-border overflow-hidden">
                <div
                    class="h-full rounded-full transition-all duration-300"
                    :class="progreso === 100 ? 'bg-success' : 'bg-science-blue'"
                    :style="'width: ' + progreso + '%'"
                ></div>
            </div>
            <span x-show="subiendo || progreso > 0" x-text="progreso + '%'" class="text-xs text-text-secondary mt-0.5 block"></span>
        @endif

        <p x-show="errorSubida" class="text-xs text-error mt-1">Error al subir. Inténtalo de nuevo.</p>
        <flux:error :name="$propiedad" />
    </div>

    {{-- Botón eliminar (solo cuando está cargado) --}}
    @if($cargado)
        <button
            type="button"
            wire:click.stop="eliminarDocumento('{{ $nombre }}')"
            wire:loading.attr="disabled"
            wire:target="eliminarDocumento('{{ $nombre }}')"
            class="shrink-0 flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-error border border-error/30 bg-error/5 hover:bg-error/15 transition-colors"
            x-on:click.stop
        >
            <flux:icon wire:loading wire:target="eliminarDocumento('{{ $nombre }}')" name="arrow-path" class="size-3.5 animate-spin" />
            <flux:icon wire:loading.remove wire:target="eliminarDocumento('{{ $nombre }}')" name="trash" class="size-3.5" />
            Eliminar
        </button>
    @else
        {{-- Hidden file input --}}
        <input
            type="file"
            wire:model="{{ $propiedad }}"
            class="hidden"
            x-ref="fileInput"
            accept=".pdf"
            x-on:click.stop
            x-on:change="nombreArchivo = $event.target.files[0]?.name ?? null"
        />
    @endif

</div>
