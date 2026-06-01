<div class="space-y-6">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.mis-solicitudes') }}">
            Mis solicitudes
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>
            {{ $this->solicitudId ? 'Editar solicitud' : 'Nueva solicitud' }}
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <flux:heading size="xl" level="1" class="font-display">
        {{ $this->solicitudId ? 'Editar solicitud de préstamo' : 'Nueva solicitud de préstamo' }}
    </flux:heading>

    @if($successMessage)
        <flux:callout variant="success" icon="check-circle">{{ $successMessage }}</flux:callout>
    @endif

    @if($comentarioCurador)
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:heading size="sm">Observación del curador</flux:heading>
            <flux:text class="mt-1 text-sm">{{ $comentarioCurador }}</flux:text>
        </flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- COLUMNA DERECHA (Aparece arriba en móvil) --}}
        <div class="space-y-6 lg:col-start-3 lg:col-span-1 lg:row-start-1">
            <div class="sticky top-6">
                {{-- Resumen lateral --}}
                <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4">
                    <flux:heading size="lg" level="2" class="font-display">Acciones</flux:heading>
                    <flux:separator />
                    <flux:text class="text-text-secondary text-sm">
                        {{ $this->solicitudId ? 'Guarda los cambios o envía la solicitud para revisión del curador.' : 'Completa el formulario y guarda el borrador para continuar.' }}
                    </flux:text>
                    <div class="flex flex-col gap-2">
                        <flux:button variant="primary" wire:click="guardarBorrador"
                            wire:loading.attr="disabled" wire:target="guardarBorrador">
                            <flux:icon wire:loading wire:target="guardarBorrador" name="arrow-path" class="animate-spin" />
                            {{ $this->solicitudId ? 'Guardar' : 'Guardar borrador' }}
                        </flux:button>
                        @if($this->solicitudId && in_array($this->estadoSolicitud, ['borrador', 'observada']))
                            <flux:button variant="primary" wire:click="enviarSolicitud"
                                wire:loading.attr="disabled" wire:target="enviarSolicitud">
                                <flux:icon wire:loading wire:target="enviarSolicitud" name="arrow-path" class="animate-spin" />
                                Enviar para revisión
                            </flux:button>
                        @endif
                    </div>
                    <flux:error name="solicitudId" />
                </div>
            </div>
        </div>

        {{-- COLUMNA IZQUIERDA (Aparece después en móvil, a la izquierda en desktop) --}}
        <div class="lg:col-span-2 lg:col-start-1 lg:row-start-1 space-y-6">
            {{-- Selector de alcance --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-3" x-data="{ alcance: @entangle('alcancePrestamo') }">
                <flux:heading size="lg" level="2" class="font-display">Tipo de préstamo</flux:heading>
                <flux:separator />
                
                <div>
                    <flux:label class="mb-3">Destino de los especímenes</flux:label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 {{ $solicitudId ? 'opacity-60 pointer-events-none' : '' }}">
                        
                        {{-- Nacional --}}
                        <button
                            type="button"
                            x-on:click="alcance = 'nacional'"
                            x-bind:class="alcance === 'nacional'
                                ? 'border-science-blue bg-science-blue/5'
                                : 'border-border bg-surface hover:border-science-blue/40'"
                            class="flex cursor-pointer flex-col items-center gap-2.5 rounded-lg border-2 p-4 transition-all duration-150"
                            {{ $solicitudId ? 'disabled' : '' }}
                        >
                            <div
                                x-bind:class="alcance === 'nacional' ? 'bg-science-blue/15' : 'bg-bg-main'"
                                class="flex h-10 w-10 items-center justify-center rounded-lg"
                            >
                                <flux:icon name="map-pin" variant="outline"
                                    x-bind:class="alcance === 'nacional' ? 'text-science-blue' : 'text-text-secondary'"
                                    class="size-5" />
                            </div>
                            <div class="text-center">
                                <p class="text-sm font-semibold leading-tight"
                                   x-bind:class="alcance === 'nacional' ? 'text-science-blue' : 'text-text-primary'">
                                    Nacional
                                </p>
                                <p class="mt-0.5 text-xs leading-snug text-text-secondary">
                                    Dentro de Ecuador
                                </p>
                            </div>
                        </button>

                        {{-- Internacional --}}
                        <button
                            type="button"
                            x-on:click="alcance = 'internacional'"
                            x-bind:class="alcance === 'internacional'
                                ? 'border-science-blue bg-science-blue/5'
                                : 'border-border bg-surface hover:border-science-blue/40'"
                            class="flex cursor-pointer flex-col items-center gap-2.5 rounded-lg border-2 p-4 transition-all duration-150"
                            {{ $solicitudId ? 'disabled' : '' }}
                        >
                            <div
                                x-bind:class="alcance === 'internacional' ? 'bg-science-blue/15' : 'bg-bg-main'"
                                class="flex h-10 w-10 items-center justify-center rounded-lg"
                            >
                                <flux:icon name="globe-americas" variant="outline"
                                    x-bind:class="alcance === 'internacional' ? 'text-science-blue' : 'text-text-secondary'"
                                    class="size-5" />
                            </div>
                            <div class="text-center">
                                <p class="text-sm font-semibold leading-tight"
                                   x-bind:class="alcance === 'internacional' ? 'text-science-blue' : 'text-text-primary'">
                                    Internacional
                                </p>
                                <p class="mt-0.5 text-xs leading-snug text-text-secondary">
                                    Fuera de Ecuador — requiere trámite de exportación
                                </p>
                            </div>
                        </button>

                    </div>
                    <div class="mt-2">
                        <flux:error name="alcancePrestamo" />
                    </div>
                </div>
            </div>

            {{-- Datos Generales --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4">
                <flux:heading size="lg" level="2" class="font-display">Datos generales</flux:heading>
                <flux:separator />

                <flux:field>
                    <flux:label>Título del estudio</flux:label>
                    <div
                        x-data="{ len: {{ strlen($tituloEstudio ?? '') }} }"
                        x-init="$watch(() => $wire.tituloEstudio, v => len = (v ?? '').length)">
                        <flux:input wire:model="tituloEstudio" maxlength="200"
                            placeholder="Ej. Análisis taxonómico de lepidópteros andinos" />
                        <p class="text-xs mt-1 tabular-nums"
                            :class="len > 180 ? 'text-warning' : 'text-text-secondary'"
                            x-text="`${200 - len} caracteres restantes`"></p>
                    </div>
                    <flux:error name="tituloEstudio" />
                </flux:field>

                <flux:field>
                    <flux:label>Institución de adscripción</flux:label>
                    <flux:input wire:model="institucionAdscripcion" placeholder="Ej. Escuela Politécnica Nacional" />
                    <flux:error name="institucionAdscripcion" />
                </flux:field>

                <flux:field>
                    <flux:label>Línea de investigación</flux:label>
                    <flux:input wire:model="lineaInvestigacion" placeholder="Ej. Entomología sistemática" />
                    <flux:error name="lineaInvestigacion" />
                </flux:field>

                <flux:field>
                    <flux:label>Propósito del préstamo</flux:label>
                    <flux:textarea wire:model="propositoPrestamo" rows="4"
                        placeholder="Describe el objetivo científico del préstamo..." />
                    <flux:error name="propositoPrestamo" />
                </flux:field>

                <flux:field>
                    <flux:label>Duración propuesta</flux:label>

                    <div
                        x-data="{ val: $wire.duracionPropuestaMeses }"
                        x-init="$watch('val', v => { $wire.duracionPropuestaMeses = parseInt(v) })"
                        class="space-y-3 pt-1"
                    >
                        {{-- Valor actual --}}
                        <div class="flex items-end justify-between">
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
                                class="text-xs font-medium px-2 py-0.5 rounded-full transition-all duration-200"
                                :class="val <= 6 ? 'bg-bio-green/10 text-bio-green' : val <= 12 ? 'bg-science-blue/10 text-science-blue' : 'bg-warning/10 text-warning'"
                                x-text="val <= 6 ? 'Corto plazo' : val <= 12 ? 'Estándar' : 'Extendido'"
                            ></span>
                        </div>

                        {{-- Barra deslizante --}}
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
                            {{-- Marca de 12 meses --}}
                            <div
                                class="absolute top-0 flex flex-col items-center pointer-events-none"
                                style="left: calc({{ (12 - 1) / 23 * 100 }}% - 1px)"
                            >
                                <div class="w-0.5 h-2 bg-text-secondary/40 mt-0.5"></div>
                            </div>
                        </div>

                        {{-- Etiquetas de referencia --}}
                        <div class="flex justify-between text-[10px] text-text-secondary select-none">
                            <span>1 mes</span>
                            <span>6</span>
                            <span class="font-semibold text-text-primary">12 ← máx. estándar</span>
                            <span>18</span>
                            <span>24</span>
                        </div>

                        {{-- Aviso cuando excede 12 meses --}}
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

                        {{-- Justificación extendida (aparece cuando > 12) --}}
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

            {{-- Especimenes solicitados --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg" level="2" class="font-display">Especímenes solicitados</flux:heading>
                    <flux:button size="sm" variant="ghost" icon="plus" wire:click="addItem">
                        Agregar espécimen
                    </flux:button>
                </div>
                <flux:separator />

                <flux:error name="items" />

                @if(empty($items))
                    <p class="text-sm text-text-secondary text-center py-4">
                        Agrega al menos un espécimen para poder guardar la solicitud.
                    </p>
                @else
                    <div class="space-y-3">
                        @foreach($items as $index => $item)
                            <div class="flex gap-3 items-end" wire:key="item-{{ $index }}">
                                <flux:field class="flex-1">
                                    <flux:label>Código de espécimen</flux:label>
                                    <flux:input wire:model="items.{{ $index }}.especimen_codigo_externo"
                                        placeholder="Ej. MEPN-0001" />
                                </flux:field>
                                <flux:field class="w-32">
                                    <flux:label>Cantidad</flux:label>
                                    <flux:input type="number" wire:model="items.{{ $index }}.cantidad_solicitada" min="1" />
                                </flux:field>
                                <flux:button size="sm" variant="ghost" icon="trash"
                                    wire:click="removeItem({{ $index }})" class="text-error mb-1" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
