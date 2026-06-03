@props([
    'propiedad',
    'requerido' => true,
    'cargado' => false,
    'archivoNombre' => null,
    'error' => null,
])

<div
    wire:ignore.self
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
            const ext = archivo.name.split('.').pop().toLowerCase();
            if (!['xlsx'].includes(ext)) {
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
    {{ $attributes->merge(['class' => 'flex flex-col items-center gap-3 text-center sm:flex-row sm:items-center sm:gap-4 sm:text-left rounded-lg border-2 p-4 transition-all']) }}
    x-bind:class="cargado
        ? '{{ $error ? 'border-error/40 bg-error/5 cursor-default' : 'border-success/40 bg-success/5 cursor-default' }}'
        : arrastrando
            ? '!border-science-blue !bg-science-blue/10 border-dashed cursor-pointer group'
            : 'border-dashed cursor-pointer group {{ $requerido ? 'border-warning/60 bg-warning/5' : 'border-border bg-bg-main' }} hover:border-science-blue hover:bg-science-blue/5'"
>
    {{-- Icono --}}
    <div class="shrink-0 size-11 rounded-lg border border-border bg-surface flex items-center justify-center shadow-sm">
        @if($error)
            <flux:icon name="exclamation-triangle" class="size-5 text-error" />
        @else
            <flux:icon x-show="cargado" x-cloak name="document-check" class="size-5 text-success" />
            <flux:icon x-show="!cargado" name="arrow-up-tray" class="size-5 text-text-secondary group-hover:text-science-blue transition-colors" />
        @endif
    </div>

    {{-- Body --}}
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-text-primary leading-snug">
            Matriz de especímenes · Darwin Core
        </p>

        <div class="flex items-center gap-2 mt-1 flex-wrap">
            @if($error)
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-error/15 text-error">
                    <flux:icon name="x-mark" class="size-2.5" />
                    Rechazada
                </span>
            @else
                <template x-if="cargado">
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-success/15 text-success">
                        <flux:icon name="check" class="size-2.5" />
                        Cargado
                    </span>
                </template>
            @endif

            <span x-show="cargado && !{{ $error ? 'true' : 'false' }}" x-cloak class="text-xs text-text-secondary">Haz clic en eliminar para subir otro archivo.</span>

            @if(!$cargado)
                @if($requerido)
                    <span x-show="!cargado" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-error/15 text-error">
                        Requerido
                    </span>
                @endif
            @endif

            <span x-show="!cargado && !subiendo && !nombreArchivo" class="text-xs text-text-secondary">
                Excel (.xlsx) · Hasta 10 MB
            </span>
        </div>

        <p x-show="!cargado && !subiendo && !nombreArchivo" class="text-xs text-text-secondary mt-1.5">
            Archivo .xlsx con una fila por espécimen y columnas Darwin Core.
        </p>

        <div x-show="!cargado && !subiendo && !nombreArchivo" class="flex items-center gap-3 flex-wrap mt-1" x-on:click.stop>
            <a href="{{ asset('templates/formato_matriz_invertebrados.xlsx') }}"
               download
               class="inline-flex items-center gap-1 text-xs font-medium text-science-blue hover:underline"
            >
                <flux:icon name="arrow-down-tray" class="size-3.5" />
                Descargar plantilla XLSX
            </a>
            <span class="text-text-secondary/40 text-xs">·</span>
            <a href="https://dwc.tdwg.org/terms/"
               target="_blank"
               rel="noopener"
               class="inline-flex items-center gap-1 text-xs text-text-secondary hover:underline"
            >
                <flux:icon name="question-mark-circle" class="size-3" />
                Estándar Darwin Core ↗
            </a>
        </div>

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

        <p x-show="errorSubida" class="text-xs text-error mt-1">Solo se admiten archivos .xlsx</p>
        <flux:error :name="$propiedad" />
    </div>

    {{-- Boton eliminar --}}
    <button
        x-show="cargado"
        x-cloak
        type="button"
        wire:click.stop="eliminarMatriz"
        wire:loading.attr="disabled"
        wire:target="eliminarMatriz"
        class="shrink-0 flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium text-error border border-error/30 bg-error/5 hover:bg-error/15 transition-colors"
        x-on:click.stop="cargado = false; nombreArchivo = null; progreso = 0"
    >
        <flux:icon wire:loading wire:target="eliminarMatriz" name="arrow-path" class="size-3.5 animate-spin" />
        <flux:icon wire:loading.remove wire:target="eliminarMatriz" name="trash" class="size-3.5" />
        Eliminar
    </button>

    {{-- Hidden file input --}}
    <input
        type="file"
        wire:model="{{ $propiedad }}"
        class="hidden"
        x-ref="fileInput"
        accept=".xlsx"
        x-on:click.stop
        x-on:change="nombreArchivo = $event.target.files[0]?.name ?? null"
    />
</div>
