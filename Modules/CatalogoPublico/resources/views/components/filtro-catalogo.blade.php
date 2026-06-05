@props([])

<div x-data="{ ampliada: false }" class="border-b border-white/10 bg-blue-navy">
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
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">

            {{-- Colección (multiselect) --}}
            <div>
                <div class="mb-1 flex items-center gap-1">
                    <span class="text-xs font-medium text-white">Colección</span>
                    <flux:tooltip content="Seleccione una o más colecciones del Departamento de Biología EPN">
                        <button type="button" tabindex="-1" aria-label="Información sobre Colección">
                            <flux:icon name="information-circle" class="size-3.5 cursor-help text-white/50 transition-colors hover:text-white/80" />
                        </button>
                    </flux:tooltip>
                </div>
                <div
                    x-data="{
                        open: false,
                        selected: [],
                        options: [
                            { value: 'invertebrados', label: 'Invertebrados' },
                            { value: 'mamiferos',     label: 'Mamíferos' },
                            { value: 'aves',          label: 'Aves' },
                            { value: 'herpetofauna',  label: 'Herpetofauna' },
                            { value: 'peces',         label: 'Peces' },
                            { value: 'paleontologia', label: 'Paleontología' }
                        ],
                        toggle(val) {
                            const i = this.selected.indexOf(val);
                            i >= 0 ? this.selected.splice(i, 1) : this.selected.push(val);
                        },
                        get displayLabel() {
                            if (!this.selected.length) return 'Todas las colecciones';
                            if (this.selected.length === 1) {
                                const found = this.options.find(o => o.value === this.selected[0]);
                                return found ? found.label : '';
                            }
                            return this.selected.length + ' seleccionadas';
                        }
                    }"
                    class="relative"
                >
                    <button
                        type="button"
                        x-on:click="open = !open"
                        class="flex w-full items-center justify-between gap-2 rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-left text-sm text-white transition-colors hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/30"
                    >
                        <span class="truncate" :class="selected.length ? 'text-white' : 'text-white/60'" x-text="displayLabel"></span>
                        <span :class="open ? 'rotate-180' : ''" class="inline-flex shrink-0 transition-transform duration-150">
                            <flux:icon name="chevron-down" class="size-4 text-white/60" />
                        </span>
                    </button>
                    <div
                        x-show="open"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 -translate-y-1"
                        x-on:click.outside="open = false"
                        class="absolute left-0 right-0 z-30 mt-1 overflow-hidden rounded-lg border border-border bg-surface shadow-sm"
                    >
                        <template x-for="opt in options" :key="opt.value">
                            <label class="flex cursor-pointer items-center gap-2.5 px-3 py-2 hover:bg-bg-main">
                                <input
                                    type="checkbox"
                                    :value="opt.value"
                                    :checked="selected.includes(opt.value)"
                                    x-on:change="toggle(opt.value)"
                                    class="rounded border-border text-science-blue focus:ring-2 focus:ring-science-blue/20"
                                />
                                <span class="text-sm text-text-primary" x-text="opt.label"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </div>

            {{-- N.° de catálogo --}}
            <div>
                <div class="mb-1 flex items-center gap-1">
                    <span class="text-xs font-medium text-white">
                        N.° de catálogo
                        <span class="font-normal text-white/50">(occurrenceID)</span>
                    </span>
                    <flux:tooltip content="Colocar únicamente caracteres numéricos, ejemplo: 2345">
                        <button type="button" tabindex="-1" aria-label="Información sobre número de catálogo">
                            <flux:icon name="information-circle" class="size-3.5 cursor-help text-white/50 transition-colors hover:text-white/80" />
                        </button>
                    </flux:tooltip>
                </div>
                <flux:input placeholder="Ej. 2345" inputmode="numeric" pattern="[0-9]*" class="border-white/20 bg-white/10 text-white placeholder:text-white/40" />
            </div>

            {{-- Tipo de colección (multiselect) --}}
            <div>
                <div class="mb-1 flex items-center gap-1">
                    <span class="text-xs font-medium text-white">
                        Tipo de colección
                        <span class="font-normal text-white/50">(preparations)</span>
                    </span>
                    <flux:tooltip content="Se indica la forma en la que los especímenes están preservados, por ejemplo, en etanol o montados en seco">
                        <button type="button" tabindex="-1" aria-label="Información sobre tipo de colección">
                            <flux:icon name="information-circle" class="size-3.5 cursor-help text-white/50 transition-colors hover:text-white/80" />
                        </button>
                    </flux:tooltip>
                </div>
                <div
                    x-data="{
                        open: false,
                        selected: [],
                        options: [
                            { value: 'wet', label: 'Wet (húmedo)' },
                            { value: 'dry', label: 'Dry (seco)' }
                        ],
                        toggle(val) {
                            const i = this.selected.indexOf(val);
                            i >= 0 ? this.selected.splice(i, 1) : this.selected.push(val);
                        },
                        get displayLabel() {
                            if (!this.selected.length) return 'Todos los tipos';
                            if (this.selected.length === 1) {
                                const found = this.options.find(o => o.value === this.selected[0]);
                                return found ? found.label : '';
                            }
                            return this.selected.length + ' seleccionados';
                        }
                    }"
                    class="relative"
                >
                    <button type="button" x-on:click="open = !open" class="flex w-full items-center justify-between gap-2 rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-left text-sm transition-colors hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/30">
                        <span class="truncate" :class="selected.length ? 'text-white' : 'text-white/60'" x-text="displayLabel"></span>
                        <span :class="open ? 'rotate-180' : ''" class="inline-flex shrink-0 transition-transform duration-150">
                            <flux:icon name="chevron-down" class="size-4 text-white/60" />
                        </span>
                    </button>
                    <div x-show="open" x-transition x-on:click.outside="open = false" class="absolute left-0 right-0 z-30 mt-1 overflow-hidden rounded-lg border border-border bg-surface shadow-sm">
                        <template x-for="opt in options" :key="opt.value">
                            <label class="flex cursor-pointer items-center gap-2.5 px-3 py-2 hover:bg-bg-main">
                                <input type="checkbox" :value="opt.value" :checked="selected.includes(opt.value)" x-on:change="toggle(opt.value)" class="rounded border-border text-science-blue focus:ring-2 focus:ring-science-blue/20" />
                                <span class="text-sm text-text-primary" x-text="opt.label"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Taxonomía (autocompletado) --}}
            <div>
                <div class="mb-1 flex items-center gap-1">
                    <span class="text-xs font-medium text-white">Taxonomía</span>
                    <flux:tooltip content="Indique el elemento taxonómico que desea buscar, ejemplo: Coleoptera. El sistema buscará en todos los niveles de los especímenes divulgados">
                        <button type="button" tabindex="-1" aria-label="Información sobre Taxonomía">
                            <flux:icon name="information-circle" class="size-3.5 cursor-help text-white/50 transition-colors hover:text-white/80" />
                        </button>
                    </flux:tooltip>
                </div>
                <flux:input placeholder="Ej. Coleoptera, Insecta…" list="taxonomia-suggestions" class="border-white/20 bg-white/10 text-white placeholder:text-white/40" />
                <datalist id="taxonomia-suggestions">
                    {{-- Se pobla con los taxones de especímenes divulgados --}}
                </datalist>
            </div>

            {{-- Geografía --}}
            <div>
                <div class="mb-1 flex items-center gap-1">
                    <span class="text-xs font-medium text-white">Geografía</span>
                    <flux:tooltip content="Indique el elemento geográfico que desea buscar, ejemplo: Pichincha. Si desea buscar más de un elemento geográfico, sepárelos con coma">
                        <button type="button" tabindex="-1" aria-label="Información sobre Geografía">
                            <flux:icon name="information-circle" class="size-3.5 cursor-help text-white/50 transition-colors hover:text-white/80" />
                        </button>
                    </flux:tooltip>
                </div>
                <flux:input placeholder="Ej. Pichincha, Napo…" class="border-white/20 bg-white/10 text-white placeholder:text-white/40" />
            </div>

        </div>

        {{-- Acciones + toggle --}}
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <div x-show="!ampliada" class="flex items-center gap-2">
                <flux:button variant="primary">
                    <flux:icon name="magnifying-glass" class="size-4" />
                    Buscar
                </flux:button>
                <button type="button" class="rounded-lg px-3 py-2 text-sm font-medium text-white/80 transition-colors hover:text-white">
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

                {{-- Colector (autocompletado) --}}
                <div>
                    <div class="mb-1 flex items-center gap-1">
                        <span class="text-xs font-medium text-white">
                            Colector
                            <span class="font-normal text-white/50">(recordedBy)</span>
                        </span>
                        <flux:tooltip content="Indique el colector que desea buscar, ejemplo: Pérez. El sistema sugerirá nombres registrados en la base de datos">
                            <button type="button" tabindex="-1">
                                <flux:icon name="information-circle" class="size-3.5 cursor-help text-white/50 transition-colors hover:text-white/80" />
                            </button>
                        </flux:tooltip>
                    </div>
                    <flux:input placeholder="Ej. Pérez" list="colector-suggestions" class="border-white/20 bg-white/10 text-white placeholder:text-white/40" />
                    <datalist id="colector-suggestions">
                        {{-- Se pobla con los colectores de la base de datos --}}
                    </datalist>
                </div>

                {{-- Fecha de recolección --}}
                <div>
                    <div class="mb-1 flex items-center gap-1">
                        <span class="text-xs font-medium text-white">
                            Fecha de recolección
                            <span class="font-normal text-white/50">(eventDate)</span>
                        </span>
                        <flux:tooltip content="Formato yyyy-mm-dd, ejemplo: 2001-02-15. Solo año: 2001. Año y mes: 2001-02. Rango separado con /: 2001-02/2005-06">
                            <button type="button" tabindex="-1">
                                <flux:icon name="information-circle" class="size-3.5 cursor-help text-white/50 transition-colors hover:text-white/80" />
                            </button>
                        </flux:tooltip>
                    </div>
                    <flux:input placeholder="Ej. 2001-02-15 o 2001/2005" class="border-white/20 bg-white/10 text-white placeholder:text-white/40" />
                </div>

                {{-- Método de recolección (multiselect dinámico) --}}
                <div>
                    <div class="mb-1 flex h-5 items-center gap-1">
                        <span class="text-xs font-medium text-white">
                            Método de recolección
                            <span class="font-normal text-white/50">(samplingProtocol)</span>
                        </span>
                    </div>
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" x-on:click="open = !open" class="flex w-full items-center justify-between gap-2 rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-left text-sm text-white/60 transition-colors hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/30">
                            <span class="truncate">Todos los métodos</span>
                            <span :class="open ? 'rotate-180' : ''" class="inline-flex shrink-0 transition-transform duration-150">
                                <flux:icon name="chevron-down" class="size-4 text-white/60" />
                            </span>
                        </button>
                        <div x-show="open" x-transition x-on:click.outside="open = false" class="absolute left-0 right-0 z-30 mt-1 rounded-lg border border-border bg-surface shadow-sm">
                            <p class="px-3 py-3 text-xs italic text-text-secondary">Opciones disponibles según los registros de la base de datos.</p>
                        </div>
                    </div>
                </div>

                {{-- Coordenadas --}}
                <div>
                    <div class="mb-1 flex items-center gap-1">
                        <span class="text-xs font-medium text-white">
                            Coordenadas
                            <span class="font-normal text-white/50">(decimalLatitude / decimalLongitude)</span>
                        </span>
                        <flux:tooltip content="Únicamente acepta grados decimales, con punto o coma. Latitud y longitud separadas con /, ejemplo: -0.2345/-76.9023. O una sola coordenada: -0,2345">
                            <button type="button" tabindex="-1">
                                <flux:icon name="information-circle" class="size-3.5 cursor-help text-white/50 transition-colors hover:text-white/80" />
                            </button>
                        </flux:tooltip>
                    </div>
                    <flux:input placeholder="Ej. -0.2345/-76.9023" class="border-white/20 bg-white/10 text-white placeholder:text-white/40" />
                </div>

                {{-- Elevación --}}
                <div>
                    <div class="mb-1 flex items-center gap-1">
                        <span class="text-xs font-medium text-white">
                            Elevación
                            <span class="font-normal text-white/50">(minimumElevationInMeters)</span>
                        </span>
                        <flux:tooltip content="Colocar valor numérico en metros, ejemplo: 2500. Con decimales: 2500.5">
                            <button type="button" tabindex="-1">
                                <flux:icon name="information-circle" class="size-3.5 cursor-help text-white/50 transition-colors hover:text-white/80" />
                            </button>
                        </flux:tooltip>
                    </div>
                    <flux:input placeholder="Ej. 2500" inputmode="decimal" class="border-white/20 bg-white/10 text-white placeholder:text-white/40" />
                </div>

                {{-- Bioma (multiselect dinámico) --}}
                <div>
                    <div class="mb-1 flex h-5 items-center gap-1">
                        <span class="text-xs font-medium text-white">
                            Bioma
                            <span class="font-normal text-white/50">(biome)</span>
                        </span>
                    </div>
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" x-on:click="open = !open" class="flex w-full items-center justify-between gap-2 rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-left text-sm text-white/60 transition-colors hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/30">
                            <span class="truncate">Todos los biomas</span>
                            <span :class="open ? 'rotate-180' : ''" class="inline-flex shrink-0 transition-transform duration-150">
                                <flux:icon name="chevron-down" class="size-4 text-white/60" />
                            </span>
                        </button>
                        <div x-show="open" x-transition x-on:click.outside="open = false" class="absolute left-0 right-0 z-30 mt-1 rounded-lg border border-border bg-surface shadow-sm">
                            <p class="px-3 py-3 text-xs italic text-text-secondary">Opciones disponibles según los registros de la base de datos.</p>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Acciones avanzadas --}}
            <div class="mt-4 flex items-center gap-2">
                <flux:button variant="primary">
                    <flux:icon name="magnifying-glass" class="size-4" />
                    Buscar
                </flux:button>
                <button type="button" class="rounded-lg px-3 py-2 text-sm font-medium text-white/80 transition-colors hover:text-white">
                    Limpiar filtros
                </button>
            </div>

        </div>

    </div>
</div>
