<div
    class="min-h-screen bg-bg-main pb-24"
    x-data="{
        alcance: @entangle('alcancePrestamo'),
        debounceTimer: null,
        scheduleAutoSave() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => { $wire.autoGuardar() }, 2000);
        },
        init() {
            this.$el.addEventListener('input', () => this.scheduleAutoSave());
            this.$el.addEventListener('change', () => this.scheduleAutoSave());
        }
    }"
>

<div class="max-w-3xl mx-auto px-4 py-6 space-y-5">

        {{-- Título de página --}}
        <div>
            <flux:heading size="xl" level="1" class="font-display">
                {{ $this->solicitudId ? 'Editar solicitud de préstamo' : 'Nueva solicitud de préstamo' }}
            </flux:heading>
            <flux:text class="mt-1 text-text-secondary text-sm">
                Completa las tres secciones para poder enviar tu solicitud al curador.
            </flux:text>
        </div>

        {{-- Alerta de éxito --}}
        @if($successMessage)
            <flux:callout variant="success" icon="check-circle">{{ $successMessage }}</flux:callout>
        @endif

        {{-- Observación del curador --}}
        @if($comentarioCurador)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:heading size="sm">Observación del curador</flux:heading>
                <flux:text class="mt-1 text-sm">{{ $comentarioCurador }}</flux:text>
            </flux:callout>
        @endif

        {{-- ────────────────────────────────────────────
             SECCIÓN 1 — Tipo de préstamo
        ──────────────────────────────────────────── --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white text-sm font-bold shrink-0">
                    1
                </div>
                <flux:heading size="base" level="2" class="font-display flex-1">Tipo de préstamo</flux:heading>
                <div x-show="alcance" x-transition>
                    <flux:icon name="check-circle" class="size-5 text-bio-green" />
                </div>
            </div>

            <div class="p-5">
                <flux:label class="mb-3 block">¿A dónde irán los especímenes?</flux:label>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 {{ $solicitudId ? 'opacity-60 pointer-events-none' : '' }}">
                    {{-- Nacional --}}
                    <button
                        type="button"
                        x-on:click="alcance = 'nacional'"
                        x-bind:class="alcance === 'nacional'
                            ? 'border-science-blue bg-science-blue/5 ring-1 ring-science-blue/30'
                            : 'border-border bg-surface hover:border-science-blue/40'"
                        class="relative flex items-start gap-4 rounded-lg border-2 p-4 text-left transition-all duration-150 cursor-pointer"
                        {{ $solicitudId ? 'disabled' : '' }}
                    >
                        <div
                            x-bind:class="alcance === 'nacional' ? 'bg-science-blue/15' : 'bg-bg-main'"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition-colors"
                        >
                            <flux:icon name="map-pin" variant="outline"
                                x-bind:class="alcance === 'nacional' ? 'text-science-blue' : 'text-text-secondary'"
                                class="size-5" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold"
                               x-bind:class="alcance === 'nacional' ? 'text-science-blue' : 'text-text-primary'">
                                Nacional
                            </p>
                            <p class="mt-0.5 text-xs text-text-secondary leading-snug">
                                Dentro de Ecuador — proceso estándar
                            </p>
                        </div>
                        <div
                            x-show="alcance === 'nacional'"
                            x-transition
                            class="absolute top-3 right-3"
                        >
                            <flux:icon name="check-circle" class="size-4 text-science-blue" />
                        </div>
                    </button>

                    {{-- Internacional --}}
                    <button
                        type="button"
                        x-on:click="alcance = 'internacional'"
                        x-bind:class="alcance === 'internacional'
                            ? 'border-science-blue bg-science-blue/5 ring-1 ring-science-blue/30'
                            : 'border-border bg-surface hover:border-science-blue/40'"
                        class="relative flex items-start gap-4 rounded-lg border-2 p-4 text-left transition-all duration-150 cursor-pointer"
                        {{ $solicitudId ? 'disabled' : '' }}
                    >
                        <div
                            x-bind:class="alcance === 'internacional' ? 'bg-science-blue/15' : 'bg-bg-main'"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition-colors"
                        >
                            <flux:icon name="globe-americas" variant="outline"
                                x-bind:class="alcance === 'internacional' ? 'text-science-blue' : 'text-text-secondary'"
                                class="size-5" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold"
                               x-bind:class="alcance === 'internacional' ? 'text-science-blue' : 'text-text-primary'">
                                Internacional
                            </p>
                            <p class="mt-0.5 text-xs text-text-secondary leading-snug">
                                Fuera de Ecuador — requiere trámite de exportación
                            </p>
                        </div>
                        <div
                            x-show="alcance === 'internacional'"
                            x-transition
                            class="absolute top-3 right-3"
                        >
                            <flux:icon name="check-circle" class="size-4 text-science-blue" />
                        </div>
                    </button>
                </div>

                <flux:error name="alcancePrestamo" class="mt-2" />
            </div>
        </div>

        {{-- ────────────────────────────────────────────
             SECCIÓN 2 — Datos del estudio
        ──────────────────────────────────────────── --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white text-sm font-bold shrink-0">
                    2
                </div>
                <flux:heading size="base" level="2" class="font-display flex-1">Datos del estudio</flux:heading>
                @if($tituloEstudio && $institucionAdscripcion && $lineaInvestigacion && $propositoPrestamo)
                    <flux:icon name="check-circle" class="size-5 text-bio-green" />
                @endif
            </div>

            <div class="p-5 space-y-5">
                {{-- Título del estudio --}}
                <flux:field>
                    <flux:label>Título del estudio <span class="text-error">*</span></flux:label>
                    <div
                        x-data="{ len: {{ strlen($tituloEstudio ?? '') }} }"
                        x-init="$watch(() => $wire.tituloEstudio, v => len = (v ?? '').length)"
                    >
                        <flux:input
                            wire:model="tituloEstudio"
                            maxlength="200"
                            placeholder="Ej. Análisis taxonómico de lepidópteros andinos"
                        />
                        <p class="text-xs mt-1 tabular-nums"
                            :class="len > 180 ? 'text-warning' : 'text-text-secondary'"
                            x-text="`${200 - len} caracteres restantes`">
                        </p>
                    </div>
                    <flux:error name="tituloEstudio" />
                </flux:field>

                {{-- Institución y línea en dos columnas --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Institución de adscripción <span class="text-error">*</span></flux:label>
                        <flux:input wire:model="institucionAdscripcion" placeholder="Ej. Escuela Politécnica Nacional" />
                        <flux:error name="institucionAdscripcion" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Línea de investigación <span class="text-error">*</span></flux:label>
                        <flux:input wire:model="lineaInvestigacion" placeholder="Ej. Entomología sistemática" />
                        <flux:error name="lineaInvestigacion" />
                    </flux:field>
                </div>

                {{-- Propósito --}}
                <flux:field>
                    <flux:label>Propósito del préstamo <span class="text-error">*</span></flux:label>
                    <flux:textarea
                        wire:model="propositoPrestamo"
                        rows="4"
                        placeholder="Describe el objetivo científico del préstamo: qué se va a estudiar, por qué son necesarios estos especímenes y cuáles serán los resultados esperados..."
                    />
                    <flux:error name="propositoPrestamo" />
                </flux:field>

                {{-- Duración --}}
                <flux:field>
                    <flux:label>Duración propuesta <span class="text-error">*</span></flux:label>

                    <div
                        x-data="{ val: $wire.duracionPropuestaMeses }"
                        x-init="$watch('val', v => { $wire.duracionPropuestaMeses = parseInt(v) })"
                        class="space-y-3 pt-1"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-baseline gap-1.5">
                                <span
                                    class="text-3xl font-bold tabular-nums transition-colors duration-200"
                                    :class="val > 12 ? 'text-warning' : 'text-science-blue'"
                                    x-text="val"
                                ></span>
                                <span class="text-sm text-text-secondary">
                                    <span x-text="val == 1 ? 'mes' : 'meses'"></span>
                                </span>
                            </div>
                            <span
                                class="text-xs font-medium px-2.5 py-1 rounded-full transition-all duration-200"
                                :class="val <= 6 ? 'bg-bio-green/10 text-bio-green' : val <= 12 ? 'bg-science-blue/10 text-science-blue' : 'bg-warning/10 text-warning'"
                                x-text="val <= 6 ? 'Corto plazo' : val <= 12 ? 'Estándar' : 'Extendido'"
                            ></span>
                        </div>

                        <div class="relative">
                            <input
                                type="range"
                                x-model="val"
                                @change="$wire.duracionPropuestaMeses = parseInt(val)"
                                min="1" max="24" step="1"
                                class="w-full h-2 rounded-full appearance-none cursor-pointer bg-border focus:outline-none
                                       [&::-webkit-slider-thumb]:appearance-none
                                       [&::-webkit-slider-thumb]:h-5 [&::-webkit-slider-thumb]:w-5
                                       [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:shadow
                                       [&::-webkit-slider-thumb]:cursor-pointer [&::-webkit-slider-thumb]:transition-colors
                                       [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-white"
                                :class="val > 12
                                    ? '[&::-webkit-slider-thumb]:bg-warning'
                                    : '[&::-webkit-slider-thumb]:bg-science-blue'"
                                :style="`background: linear-gradient(to right,
                                    ${val > 12 ? '#FF9800' : '#1976D2'} ${(val - 1) / 23 * 100}%,
                                    #E0E0E0 ${(val - 1) / 23 * 100}%)`"
                            />
                            <div
                                class="absolute top-0 flex flex-col items-center pointer-events-none"
                                style="left: calc({{ (12 - 1) / 23 * 100 }}% - 1px)"
                            >
                                <div class="w-0.5 h-2 bg-text-secondary/40 mt-0.5"></div>
                            </div>
                        </div>

                        <div class="flex justify-between text-[10px] text-text-secondary select-none">
                            <span>1 mes</span>
                            <span>6</span>
                            <span class="font-semibold text-text-primary">12 ← máx. estándar</span>
                            <span>18</span>
                            <span>24</span>
                        </div>

                        <div
                            x-show="val > 12"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-1"
                            class="flex items-start gap-2 rounded-lg border border-warning/30 bg-warning/10 p-3"
                        >
                            <flux:icon name="exclamation-triangle" class="mt-0.5 size-4 shrink-0 text-warning" />
                            <p class="text-xs text-warning">
                                Las solicitudes de más de 12 meses requieren una justificación adicional que será evaluada por el curador.
                            </p>
                        </div>

                        <div
                            x-show="val > 12"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 translate-y-1"
                        >
                            <flux:field class="mt-1">
                                <flux:label>
                                    Justificación para duración extendida
                                    <span class="text-error">*</span>
                                </flux:label>
                                <flux:textarea
                                    wire:model="justificacionExtendida"
                                    rows="3"
                                    placeholder="Explica por qué la investigación requiere más de 12 meses de préstamo..."
                                />
                                <flux:error name="justificacionExtendida" />
                            </flux:field>
                        </div>
                    </div>

                    <flux:error name="duracionPropuestaMeses" />
                </flux:field>
            </div>
        </div>

        {{-- ────────────────────────────────────────────
             SECCIÓN 3 — Especímenes
        ──────────────────────────────────────────── --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white text-sm font-bold shrink-0">
                    3
                </div>
                <flux:heading size="base" level="2" class="font-display flex-1">Especímenes solicitados</flux:heading>
                @if(count($items) > 0)
                    <span class="text-xs bg-science-blue/10 text-science-blue px-2.5 py-1 rounded-full font-medium tabular-nums">
                        {{ count($items) }} {{ count($items) === 1 ? 'espécimen' : 'especímenes' }}
                    </span>
                @endif
            </div>

            <div class="p-5 space-y-4">
                <flux:error name="items" />

                {{-- Lista de especímenes ya agregados --}}
                @if(!empty($items))
                    <div class="space-y-2">
                        @foreach($items as $index => $item)
                            <div
                                class="group flex items-center gap-3 rounded-lg border border-border bg-bg-main px-4 py-3 transition-colors hover:border-science-blue/30"
                                wire:key="item-{{ $index }}"
                            >
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-science-blue/10">
                                    <flux:icon name="beaker" class="size-4 text-science-blue" />
                                </div>

                                <div class="flex-1 min-w-0">
                                    <flux:field class="mb-0">
                                        <flux:input
                                            wire:model="items.{{ $index }}.especimen_codigo_externo"
                                            placeholder="Código (Ej. MEPN-0001)"
                                            class="h-8 text-sm"
                                        />
                                        <flux:error name="items.{{ $index }}.especimen_codigo_externo" />
                                    </flux:field>
                                </div>

                                <div class="w-24 shrink-0">
                                    <flux:field class="mb-0">
                                        <flux:input
                                            type="number"
                                            wire:model="items.{{ $index }}.cantidad_solicitada"
                                            min="1"
                                            class="h-8 text-sm text-center"
                                        />
                                        <flux:error name="items.{{ $index }}.cantidad_solicitada" />
                                    </flux:field>
                                </div>

                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="removeItem({{ $index }})"
                                    class="shrink-0 text-error opacity-0 group-hover:opacity-100 transition-opacity"
                                />
                            </div>
                        @endforeach
                    </div>

                    <flux:separator />
                @endif

                {{-- Estado vacío --}}
                @if(empty($items))
                    <div class="flex flex-col items-center justify-center py-8 text-center">
                        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-bg-main border border-border mb-3">
                            <flux:icon name="beaker" class="size-7 text-text-secondary/50" />
                        </div>
                        <p class="text-sm font-medium text-text-primary">Sin especímenes aún</p>
                        <p class="text-xs text-text-secondary mt-1">
                            Agrega al menos uno para guardar la solicitud.
                        </p>
                    </div>
                @endif

                {{-- Botón para agregar --}}
                <flux:button
                    variant="ghost"
                    icon="plus"
                    wire:click="addItem"
                    class="w-full border border-dashed border-border hover:border-science-blue/50 hover:text-science-blue"
                >
                    Agregar espécimen
                </flux:button>
            </div>
        </div>

    </div>

    {{-- ────────────────────────────────────────────
         BARRA DE ACCIONES STICKY (inferior)
    ──────────────────────────────────────────── --}}
    <div class="fixed bottom-0 inset-x-0 z-20 bg-surface border-t border-border shadow-lg">
        <div class="max-w-3xl mx-auto px-4 py-3 flex items-center gap-3">
            <p class="hidden sm:block text-xs text-text-secondary flex-1">
                @if($lastAutoSavedAt)
                    Guardado automáticamente a las {{ $lastAutoSavedAt }}
                @else
                    Los cambios no se guardan automáticamente.
                @endif
            </p>

            <div class="flex gap-2 ml-auto">
                <flux:button
                    variant="ghost"
                    wire:click="guardarBorrador"
                    wire:loading.attr="disabled"
                    wire:target="guardarBorrador"
                >
                    <flux:icon wire:loading wire:target="guardarBorrador" name="arrow-path" class="animate-spin" />
                    {{ $this->solicitudId ? 'Guardar cambios' : 'Guardar borrador' }}
                </flux:button>

                @if($this->solicitudId && in_array($this->estadoSolicitud, ['borrador', 'observada']))
                    <flux:button
                        variant="primary"
                        icon="paper-airplane"
                        wire:click="enviarSolicitud"
                        wire:loading.attr="disabled"
                        wire:target="enviarSolicitud"
                    >
                        Enviar para revisión
                    </flux:button>
                @endif
            </div>
        </div>
    </div>
</div>
