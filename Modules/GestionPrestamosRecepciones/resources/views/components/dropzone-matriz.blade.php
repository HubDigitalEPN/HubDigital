@props([
    'propiedad',
    'cargado' => false,
    'archivoNombre' => null,
    'error' => null,
    'soloEdicionDeTextos' => false,
])

@if($soloEdicionDeTextos)
    <div class="rounded-lg border border-dashed border-border p-4 text-sm text-text-secondary">
        Arrastra un Excel (.xlsx) aquí o elige un archivo.
    </div>
@else
    <div class="space-y-2" data-hub-inline-feedback x-data="{
        fase: '', progreso: 0, demora: false, arrastrando: false, aviso: '', temporizador: null,
        comenzar() {
            this.fase = 'subiendo';
            this.progreso = 0;
            this.aviso = '';
            this.demora = false;
            clearTimeout(this.temporizador);
            this.temporizador = setTimeout(() => { this.demora = true; }, 25000);
        },
        terminar(aviso = '') {
            clearTimeout(this.temporizador);
            this.fase = '';
            this.demora = false;
            this.aviso = aviso;
            this.$refs.excel.value = '';
        },
        soltar(evento) {
            this.arrastrando = false;
            if (this.fase || !evento.dataTransfer.files.length) return;
            if (evento.dataTransfer.files.length !== 1) {
                this.aviso = 'Selecciona un solo archivo Excel.';
                return;
            }
            this.$refs.excel.files = evento.dataTransfer.files;
            this.$refs.excel.dispatchEvent(new Event('change', {bubbles: true}));
        },
        destroy() { clearTimeout(this.temporizador); }
    }"
        x-on:livewire-upload-start="comenzar()"
        x-on:livewire-upload-progress="progreso = $event.detail.progress; if (progreso >= 100) fase = 'validando'"
        x-on:livewire-upload-finish="terminar()"
        x-on:livewire-upload-cancel="terminar('Carga cancelada.')"
        x-on:livewire-upload-error="terminar(navigator.onLine
            ? 'No se pudo cargar el Excel. Comprueba que sea .xlsx y que ocupe hasta 10 MB, e inténtalo nuevamente.'
            : 'Se perdió la conexión. Vuelve a cargar el Excel cuando se restablezca.')"
    >
        <div class="rounded-lg border border-dashed border-border bg-bg-main focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-science-blue"
            x-bind:class="{ '!border-science-blue bg-science-blue/10': arrastrando }"
            x-on:dragover.prevent="if (!fase) arrastrando = true"
            x-on:dragleave="if (!$el.contains($event.relatedTarget)) arrastrando = false"
            x-on:drop.prevent="soltar($event)">
            <input id="matriz-dwc" x-ref="excel" type="file" class="sr-only" wire:model="{{ $propiedad }}"
                accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                aria-labelledby="matriz-dwc-etiqueta matriz-dwc-accion" aria-describedby="matriz-dwc-seleccion"
                x-bind:disabled="Boolean(fase)" />
            <label id="matriz-dwc-accion" for="matriz-dwc" class="flex cursor-pointer flex-wrap items-center gap-3 p-4"
                x-bind:class="{ 'cursor-wait opacity-70': fase }">
                <flux:icon name="arrow-up-tray" class="size-5 shrink-0" aria-hidden="true" />
                <span class="text-sm font-semibold">Elegir archivo Excel o arrastrarlo aquí</span>
                <span class="text-sm text-text-secondary">.xlsx · Hasta 10 MB</span>
            </label>
        </div>
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            <span id="matriz-dwc-seleccion" class="min-w-0 break-all text-sm text-text-secondary">{{ $cargado ? ($archivoNombre ?: 'Matriz guardada') : 'Ningún archivo seleccionado' }}</span>
            @if($cargado)
                <flux:button size="sm" icon="trash" wire:click="eliminarMatriz" wire:confirm="¿Eliminar el Excel cargado y sus registros de esta solicitud?"
                    wire:loading.attr="disabled" wire:target="eliminarMatriz,archivoMatriz" x-bind:disabled="Boolean(fase)">Eliminar Excel</flux:button>
            @endif
        </div>
        <p role="status" x-show="fase" x-cloak class="text-sm text-science-blue"
            x-text="fase === 'subiendo' ? 'Subiendo archivo… ' + progreso + '%' : 'Validando el contenido del Excel…'"></p>
        <p role="status" x-show="demora" x-cloak class="text-sm text-text-secondary">El Excel está tardando más de lo habitual. Espera la confirmación antes de continuar.</p>
        <p role="alert" x-show="aviso" x-text="aviso" x-cloak class="text-sm text-error"></p>
        @if($error)<p role="alert" class="text-sm text-error">{{ $error }}</p>@endif
        @if(isset($errors))<flux:error :name="$propiedad" />@endif
    </div>
@endif
