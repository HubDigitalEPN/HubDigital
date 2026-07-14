@props([
    'preparaciones'            => [],
    'biomas'                   => [],
    'metodosRecoleccion'       => [],
    'colectores'               => [],
    'filtrosActivos'           => [],
])

@php
    $fa = $filtrosActivos;
@endphp

<div
    x-data="{
        ampliada: false,

        filtroCatalogo:      '{{ addslashes($fa['filtroCatalogo'] ?? '') }}',
        filtroPreparaciones: {{ Js::from($fa['filtroPreparaciones'] ?? []) }},
        filtroTaxon:         '{{ addslashes($fa['filtroTaxon'] ?? '') }}',
        filtroGeografias:    {{ Js::from($fa['filtroGeografias'] ?? []) }},
        filtroColector:      '{{ addslashes($fa['filtroColector'] ?? '') }}',
        filtroFechaDesde:    '{{ $fa['filtroFechaDesde'] ?? '' }}',
        filtroFechaHasta:    '{{ $fa['filtroFechaHasta'] ?? '' }}',
        filtroMetodos:       {{ Js::from($fa['filtroMetodos'] ?? []) }},
        filtroLatMin:        '{{ $fa['filtroLatMin'] ?? '' }}',
        filtroLatMax:        '{{ $fa['filtroLatMax'] ?? '' }}',
        filtroLonMin:        '{{ $fa['filtroLonMin'] ?? '' }}',
        filtroLonMax:        '{{ $fa['filtroLonMax'] ?? '' }}',
        filtroElevDesde:     '{{ $fa['filtroElevDesde'] ?? '' }}',
        filtroElevHasta:     '{{ $fa['filtroElevHasta'] ?? '' }}',
        filtroBiomas:        {{ Js::from($fa['filtroBiomas'] ?? []) }},

        toggleArray(arr, val) {
            const i = arr.indexOf(val);
            i >= 0 ? arr.splice(i, 1) : arr.push(val);
        },

        buscar() {
            $wire.aplicarFiltros({
                filtroCatalogo:      this.filtroCatalogo,
                filtroPreparaciones: this.filtroPreparaciones,
                filtroTaxon:         this.filtroTaxon,
                filtroGeografias:    this.filtroGeografias,
                filtroColector:      this.filtroColector,
                filtroFechaDesde:    this.filtroFechaDesde,
                filtroFechaHasta:    this.filtroFechaHasta,
                filtroMetodos:       this.filtroMetodos,
                filtroLatMin:        this.filtroLatMin,
                filtroLatMax:        this.filtroLatMax,
                filtroLonMin:        this.filtroLonMin,
                filtroLonMax:        this.filtroLonMax,
                filtroElevDesde:     this.filtroElevDesde,
                filtroElevHasta:     this.filtroElevHasta,
                filtroBiomas:        this.filtroBiomas,
            });
        },

        limpiar() {
            this.filtroCatalogo      = '';
            this.filtroPreparaciones = [];
            this.filtroTaxon         = '';
            this.filtroGeografias    = [];
            this.filtroColector      = '';
            this.filtroFechaDesde    = '';
            this.filtroFechaHasta    = '';
            this.filtroMetodos       = [];
            this.filtroLatMin        = '';
            this.filtroLatMax        = '';
            this.filtroLonMin        = '';
            this.filtroLonMax        = '';
            this.filtroElevDesde     = '';
            this.filtroElevHasta     = '';
            this.filtroBiomas        = [];
            $wire.limpiarFiltros();
        },
    }"
    class="border-b border-white/10 bg-blue-navy"
>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-5">

        {{-- Encabezado --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <flux:icon name="funnel" class="size-4 text-white/60" />
                <span class="text-xs font-semibold uppercase tracking-wider text-white/60">Búsqueda</span>
            </div>
            <p class="text-xs italic text-white/50">
                Sin distinción de mayúsculas, acentos ni artículos
            </p>
        </div>

        {{-- ═══════════════════════════════════════════════════════
             BÚSQUEDA RÁPIDA
             ═══════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">

            {{-- N.° de catálogo --}}
            <div>
                <div class="mb-1 flex items-center gap-1">
                    <span class="text-xs font-medium text-white">
                        N.° de catálogo
                        <span class="font-normal text-white/50">(código)</span>
                    </span>
                    <flux:tooltip content="Ingrese uno o más códigos separados por coma, ejemplo: EPN-001, EPN-002">
                        <button type="button" tabindex="-1" aria-label="Información sobre número de catálogo">
                            <flux:icon name="information-circle" class="size-3.5 cursor-help text-white/50 transition-colors hover:text-white/80" />
                        </button>
                    </flux:tooltip>
                </div>
                <input
                    x-model="filtroCatalogo"
                    type="text"
                    placeholder="Ej. EPN-001, EPN-002"
                    x-on:keydown.enter="buscar()"
                    class="w-full rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-white/30"
                />
            </div>

            {{-- Tipo de colección (preparations — dinámico) --}}
            <div>
                <div class="mb-1 flex items-center gap-1">
                    <span class="text-xs font-medium text-white">
                        Tipo de recoleccion
                        <span class="font-normal text-white/50">(preparations)</span>
                    </span>
                    <flux:tooltip content="Forma en que los especímenes están preservados, por ejemplo húmedo o seco">
                        <button type="button" tabindex="-1" aria-label="Información sobre tipo de colección">
                            <flux:icon name="information-circle" class="size-3.5 cursor-help text-white/50 transition-colors hover:text-white/80" />
                        </button>
                    </flux:tooltip>
                </div>
                @if(count($preparaciones) > 0)
                    <div x-data="{ open: false }" class="relative">
                        <button
                            type="button"
                            x-on:click="open = !open"
                            class="flex w-full items-center justify-between gap-2 rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-left text-sm transition-colors hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/30"
                        >
                            <span
                                class="truncate"
                                :class="filtroPreparaciones.length ? 'text-white' : 'text-white/60'"
                                x-text="filtroPreparaciones.length === 0 ? 'Todos los tipos' : (filtroPreparaciones.length === 1 ? filtroPreparaciones[0] : filtroPreparaciones.length + ' seleccionados')"
                            ></span>
                            <span :class="open ? 'rotate-180' : ''" class="inline-flex shrink-0 transition-transform duration-150">
                                <flux:icon name="chevron-down" class="size-4 text-white/60" />
                            </span>
                        </button>
                        <div
                            x-show="open"
                            x-transition
                            x-on:click.outside="open = false"
                            class="absolute left-0 right-0 z-30 mt-1 overflow-hidden rounded-lg border border-border bg-surface shadow-sm"
                        >
                            @foreach($preparaciones as $prep)
                                <label class="flex cursor-pointer items-center gap-2.5 px-3 py-2 hover:bg-bg-main">
                                    <input
                                        type="checkbox"
                                        value="{{ $prep }}"
                                        x-on:change="toggleArray(filtroPreparaciones, '{{ $prep }}')"
                                        :checked="filtroPreparaciones.includes('{{ $prep }}')"
                                        class="rounded border-border text-science-blue focus:ring-2 focus:ring-science-blue/20"
                                    />
                                    <span class="text-sm text-text-primary">{{ $prep }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="flex w-full items-center rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/40">
                        Sin datos registrados
                    </div>
                @endif
            </div>

            {{-- Taxonomía --}}
            <div>
                <div class="mb-1 flex items-center gap-1">
                    <span class="text-xs font-medium text-white">Taxonomía</span>
                    <flux:tooltip content="Indique el elemento taxonómico que desea buscar, ejemplo: Coleoptera. El sistema buscará en todos los niveles">
                        <button type="button" tabindex="-1" aria-label="Información sobre Taxonomía">
                            <flux:icon name="information-circle" class="size-3.5 cursor-help text-white/50 transition-colors hover:text-white/80" />
                        </button>
                    </flux:tooltip>
                </div>
                <input
                    x-model="filtroTaxon"
                    type="text"
                    placeholder="Ej. Coleoptera, Insecta…"
                    list="taxonomia-suggestions"
                    x-on:keydown.enter="buscar()"
                    class="w-full rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-white/30"
                />
                <datalist id="taxonomia-suggestions">
                    {{-- Poblado dinámicamente por el servidor si se requiere autocompletado --}}
                </datalist>
            </div>

            {{-- Geografía --}}
            <div>
                <div class="mb-1 flex items-center gap-1">
                    <span class="text-xs font-medium text-white">Geografía</span>
                    <flux:tooltip content="Indique la localidad que desea buscar, ejemplo: Pichincha. Separe múltiples valores con coma">
                        <button type="button" tabindex="-1" aria-label="Información sobre Geografía">
                            <flux:icon name="information-circle" class="size-3.5 cursor-help text-white/50 transition-colors hover:text-white/80" />
                        </button>
                    </flux:tooltip>
                </div>
                <input
                    x-model.lazy="filtroGeografias"
                    x-on:change="filtroGeografias = $event.target.value.split(',').map(v => v.trim()).filter(v => v !== '')"
                    :value="filtroGeografias.join(', ')"
                    type="text"
                    placeholder="Ej. Pichincha, Napo…"
                    x-on:keydown.enter="buscar()"
                    class="w-full rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-white/30"
                />
            </div>

        </div>

        {{-- Acciones + toggle --}}
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <flux:button x-on:click="buscar()" variant="primary">
                    <flux:icon name="magnifying-glass" class="size-4" />
                    Buscar
                </flux:button>
                <button
                    type="button"
                    x-on:click="limpiar()"
                    class="rounded-lg px-3 py-2 text-sm font-medium text-white/80 transition-colors hover:text-white"
                >
                    Limpiar
                </button>
            </div>
            <button
                type="button"
                x-on:click="ampliada = !ampliada"
                class="flex items-center gap-1.5 text-sm font-medium text-white/80 transition-colors hover:text-white"
            >
                <span x-text="ampliada ? 'Ocultar búsqueda avanzada' : 'Búsqueda avanzada'">Búsqueda avanzada</span>
                <span :class="ampliada ? 'rotate-180' : ''" class="inline-flex transition-transform duration-200">
                    <flux:icon name="chevron-down" class="size-4" />
                </span>
            </button>
        </div>

        {{-- ═══════════════════════════════════════════════════════
             BÚSQUEDA AVANZADA
             ═══════════════════════════════════════════════════════ --}}
        <div
            x-show="ampliada"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-1"
            class="mt-5 border-t border-white/10 pt-5"
        >
            <p class="mb-4 text-xs font-semibold uppercase tracking-wider text-white/60">
                Búsqueda avanzada
            </p>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">

                {{-- Colector --}}
                <div>
                    <div class="mb-1 flex items-center gap-1">
                        <span class="text-xs font-medium text-white">
                            Colector
                            <span class="font-normal text-white/50">(recordedBy)</span>
                        </span>
                        <flux:tooltip content="Indique el colector que desea buscar. El sistema buscará coincidencias parciales">
                            <button type="button" tabindex="-1">
                                <flux:icon name="information-circle" class="size-3.5 cursor-help text-white/50 transition-colors hover:text-white/80" />
                            </button>
                        </flux:tooltip>
                    </div>
                    <input
                        x-model="filtroColector"
                        type="text"
                        placeholder="Ej. Pérez"
                        list="colector-suggestions"
                        x-on:keydown.enter="buscar()"
                        class="w-full rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-white/30"
                    />
                    <datalist id="colector-suggestions">
                        @foreach($colectores as $colector)
                            <option value="{{ $colector }}"></option>
                        @endforeach
                    </datalist>
                </div>

                {{-- Fecha de recolección --}}
                <div>
                    <div class="mb-1 flex items-center gap-1">
                        <span class="text-xs font-medium text-white">
                            Fecha de recolección
                            <span class="font-normal text-white/50">(eventDate)</span>
                        </span>
                        <flux:tooltip content="Rango de fechas: desde y hasta. Formato yyyy-mm-dd">
                            <button type="button" tabindex="-1">
                                <flux:icon name="information-circle" class="size-3.5 cursor-help text-white/50 transition-colors hover:text-white/80" />
                            </button>
                        </flux:tooltip>
                    </div>
                    <div class="flex gap-2">
                        <input
                            x-model="filtroFechaDesde"
                            type="date"
                            title="Desde"
                            class="w-full rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-white/30"
                        />
                        <input
                            x-model="filtroFechaHasta"
                            type="date"
                            title="Hasta"
                            class="w-full rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-white/30"
                        />
                    </div>
                </div>

                {{-- Método de recolección (dinámico) --}}
                <div>
                    <div class="mb-1 flex items-center gap-1">
                        <span class="text-xs font-medium text-white">
                            Método de recolección
                            <span class="font-normal text-white/50">(samplingProtocol)</span>
                        </span>
                    </div>
                    @if(count($metodosRecoleccion) > 0)
                        <div x-data="{ open: false }" class="relative">
                            <button
                                type="button"
                                x-on:click="open = !open"
                                class="flex w-full items-center justify-between gap-2 rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-left text-sm transition-colors hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/30"
                            >
                                <span
                                    class="truncate"
                                    :class="filtroMetodos.length ? 'text-white' : 'text-white/60'"
                                    x-text="filtroMetodos.length === 0 ? 'Todos los métodos' : (filtroMetodos.length === 1 ? filtroMetodos[0] : filtroMetodos.length + ' seleccionados')"
                                ></span>
                                <span :class="open ? 'rotate-180' : ''" class="inline-flex shrink-0 transition-transform duration-150">
                                    <flux:icon name="chevron-down" class="size-4 text-white/60" />
                                </span>
                            </button>
                            <div
                                x-show="open"
                                x-transition
                                x-on:click.outside="open = false"
                                class="absolute left-0 right-0 z-30 mt-1 max-h-48 overflow-y-auto rounded-lg border border-border bg-surface shadow-sm"
                            >
                                @foreach($metodosRecoleccion as $metodo)
                                    <label class="flex cursor-pointer items-center gap-2.5 px-3 py-2 hover:bg-bg-main">
                                        <input
                                            type="checkbox"
                                            value="{{ $metodo }}"
                                            x-on:change="toggleArray(filtroMetodos, '{{ $metodo }}')"
                                            :checked="filtroMetodos.includes('{{ $metodo }}')"
                                            class="rounded border-border text-science-blue focus:ring-2 focus:ring-science-blue/20"
                                        />
                                        <span class="text-sm text-text-primary">{{ $metodo }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="flex w-full items-center rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/40">
                            Sin datos registrados
                        </div>
                    @endif
                </div>

                {{-- Coordenadas (lat/lon) --}}
                <div>
                    <div class="mb-1 flex items-center gap-1">
                        <span class="text-xs font-medium text-white">
                            Coordenadas
                            <span class="font-normal text-white/50">(decimalLatitude / decimalLongitude)</span>
                        </span>
                        <flux:tooltip content="Grados decimales. Ingrese latitud y longitud del centro del área. El sistema buscará especímenes en un radio de ±0.5°">
                            <button type="button" tabindex="-1">
                                <flux:icon name="information-circle" class="size-3.5 cursor-help text-white/50 transition-colors hover:text-white/80" />
                            </button>
                        </flux:tooltip>
                    </div>
                    <div class="flex gap-2">
                        <input
                            type="number"
                            step="0.0001"
                            min="-90"
                            max="90"
                            placeholder="Lat. Ej. -0.2345"
                            x-on:change="
                                const v = parseFloat($event.target.value);
                                if (!isNaN(v)) { filtroLatMin = String(v - 0.5); filtroLatMax = String(v + 0.5); }
                                else { filtroLatMin = ''; filtroLatMax = ''; }
                            "
                            :value="filtroLatMin !== '' ? ((parseFloat(filtroLatMin) + 0.5).toFixed(4)) : ''"
                            class="w-full rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-white/30"
                        />
                        <input
                            type="number"
                            step="0.0001"
                            min="-180"
                            max="180"
                            placeholder="Lon. Ej. -76.9023"
                            x-on:change="
                                const v = parseFloat($event.target.value);
                                if (!isNaN(v)) { filtroLonMin = String(v - 0.5); filtroLonMax = String(v + 0.5); }
                                else { filtroLonMin = ''; filtroLonMax = ''; }
                            "
                            :value="filtroLonMin !== '' ? ((parseFloat(filtroLonMin) + 0.5).toFixed(4)) : ''"
                            class="w-full rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-white/30"
                        />
                    </div>
                </div>

                {{-- Elevación --}}
                <div>
                    <div class="mb-1 flex items-center gap-1">
                        <span class="text-xs font-medium text-white">
                            Elevación
                            <span class="font-normal text-white/50">(m.s.n.m.)</span>
                        </span>
                        <flux:tooltip content="Rango de elevación en metros sobre el nivel del mar">
                            <button type="button" tabindex="-1">
                                <flux:icon name="information-circle" class="size-3.5 cursor-help text-white/50 transition-colors hover:text-white/80" />
                            </button>
                        </flux:tooltip>
                    </div>
                    <div class="flex gap-2">
                        <input
                            x-model="filtroElevDesde"
                            type="number"
                            inputmode="decimal"
                            placeholder="Desde"
                            class="w-full rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-white/30"
                        />
                        <input
                            x-model="filtroElevHasta"
                            type="number"
                            inputmode="decimal"
                            placeholder="Hasta"
                            class="w-full rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-white/30"
                        />
                    </div>
                </div>

                {{-- Bioma (dinámico) --}}
                <div>
                    <div class="mb-1 flex items-center gap-1">
                        <span class="text-xs font-medium text-white">
                            Bioma
                            <span class="font-normal text-white/50">(biome)</span>
                        </span>
                    </div>
                    @if(count($biomas) > 0)
                        <div x-data="{ open: false }" class="relative">
                            <button
                                type="button"
                                x-on:click="open = !open"
                                class="flex w-full items-center justify-between gap-2 rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-left text-sm transition-colors hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/30"
                            >
                                <span
                                    class="truncate"
                                    :class="filtroBiomas.length ? 'text-white' : 'text-white/60'"
                                    x-text="filtroBiomas.length === 0 ? 'Todos los biomas' : (filtroBiomas.length === 1 ? filtroBiomas[0] : filtroBiomas.length + ' seleccionados')"
                                ></span>
                                <span :class="open ? 'rotate-180' : ''" class="inline-flex shrink-0 transition-transform duration-150">
                                    <flux:icon name="chevron-down" class="size-4 text-white/60" />
                                </span>
                            </button>
                            <div
                                x-show="open"
                                x-transition
                                x-on:click.outside="open = false"
                                class="absolute left-0 right-0 z-30 mt-1 max-h-48 overflow-y-auto rounded-lg border border-border bg-surface shadow-sm"
                            >
                                @foreach($biomas as $bioma)
                                    <label class="flex cursor-pointer items-center gap-2.5 px-3 py-2 hover:bg-bg-main">
                                        <input
                                            type="checkbox"
                                            value="{{ $bioma }}"
                                            x-on:change="toggleArray(filtroBiomas, '{{ $bioma }}')"
                                            :checked="filtroBiomas.includes('{{ $bioma }}')"
                                            class="rounded border-border text-science-blue focus:ring-2 focus:ring-science-blue/20"
                                        />
                                        <span class="text-sm text-text-primary">{{ $bioma }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="flex w-full items-center rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/40">
                            Sin datos registrados
                        </div>
                    @endif
                </div>

            </div>

            {{-- Acciones avanzadas --}}
            <div class="mt-4 flex items-center gap-2">
                <flux:button x-on:click="buscar()" variant="primary">
                    <flux:icon name="magnifying-glass" class="size-4" />
                    Buscar
                </flux:button>
                <button
                    type="button"
                    x-on:click="limpiar()"
                    class="rounded-lg px-3 py-2 text-sm font-medium text-white/80 transition-colors hover:text-white"
                >
                    Limpiar filtros
                </button>
            </div>

        </div>

    </div>
</div>
