@php
    /**
     * Renderer in-line de cada celda según la clave de columna.
     * Devuelve el HTML a mostrar para la celda.
     */
    $renderCelda = function (array $e, string $clave): string {
        $v = $e[$clave] ?? null;

        if ($clave === 'taxonNombre') {
            return $v !== null
                ? '<span class="font-serif italic">'.e($v).'</span>'
                : ($e['taxonVerbatim'] !== null
                    ? '<span class="text-text-secondary text-xs italic">'.e($e['taxonVerbatim']).' (verbatim)</span>'
                    : '<span class="text-text-secondary">—</span>');
        }
        if ($clave === 'estadoRevision') {
            $estado = (string) ($v ?? 'pendiente');
            $clases = match ($estado) {
                'pendiente' => 'bg-warning/10 text-warning border-warning',
                'confirmada' => 'bg-success/10 text-success border-success',
                'descartada' => 'bg-error/10 text-error border-error',
                default => 'bg-border/30 text-text-secondary border-border',
            };

            return '<span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold whitespace-nowrap '.$clases.'">'.e(ucfirst($estado)).'</span>';
        }
        if ($clave === 'motivoRevision') {
            return $v !== null
                ? '<span class="text-text-primary text-xs">'.e((string) $v).'</span>'
                : '<span class="text-text-secondary">—</span>';
        }
        if ($clave === 'estado') {
            $estado = (string) ($v ?? '');
            $clases = match ($estado) {
                'disponible' => 'bg-success text-white',
                'en_prestamo' => 'bg-warning text-white',
                default => 'bg-border text-text-primary',
            };

            return '<span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold '.$clases.'">'.e(ucfirst(str_replace('_', ' ', $estado))).'</span>';
        }
        if ($clave === 'endemic') {
            if ($v === null) {
                return '<span class="text-text-secondary">—</span>';
            }

            return $v
                ? '<span class="inline-flex items-center rounded-full bg-bio-green/10 text-bio-green border border-bio-green px-2 py-0.5 text-xs">Sí</span>'
                : '<span class="text-text-secondary text-xs">No</span>';
        }
        if (in_array($clave, ['decimalLatitude', 'decimalLongitude', 'elevationMinM', 'elevationMaxM', 'individualCount', 'filaOrigenExcel'], true)) {
            return $v !== null && $v !== ''
                ? '<span class="font-mono text-xs">'.e((string) $v).'</span>'
                : '<span class="text-text-secondary">—</span>';
        }
        if (in_array($clave, ['taxonVerbatim', 'localidadVerbatim', 'fechaVerbatim', 'coordVerbatim', 'individualCountVerbatim'], true)) {
            return $v !== null && $v !== ''
                ? '<span class="text-text-secondary text-xs italic">'.e((string) $v).'</span>'
                : '<span class="text-text-secondary">—</span>';
        }

        if ($v === null || $v === '') {
            return '<span class="text-text-secondary">—</span>';
        }

        return e((string) $v);
    };
@endphp

<div class="space-y-6 p-4 sm:p-6"
     x-data="especimenesIndex({
         claves: {{ json_encode(array_column($columnasRegistro, 'clave')) }},
         visiblesPorDefecto: {{ json_encode($columnasVisiblesPorDefecto) }},
     })"
     x-init="init()">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Especímenes</flux:heading>
        <div class="flex flex-wrap gap-2">
            <flux:button icon="adjustments-horizontal" variant="ghost" @click="panelColumnasAbierto = !panelColumnasAbierto">
                Columnas (<span x-text="visibles.length"></span>)
            </flux:button>
            <flux:button icon="plus" variant="primary" wire:click="abrirModal" class="w-full sm:w-auto">
                Nuevo especímen
            </flux:button>
        </div>
    </div>

    @if($successMessage)<flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>@endif
    @if($errorMessage && !$showModal)<flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>@endif

    {{-- Leyenda de prioridad --}}
    <div class="flex flex-wrap items-center gap-4 text-xs text-text-secondary">
        <span class="font-medium">Prioridad de columnas:</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-full bg-error"></span> Crítica (requerida GBIF)</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-full bg-warning"></span> Recomendada</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-full bg-text-secondary"></span> Opcional</span>
    </div>

    {{-- Panel: Configuración de columnas --}}
    <div x-show="panelColumnasAbierto" x-cloak x-transition
         class="rounded-lg border border-border bg-surface shadow-sm p-4 space-y-3">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="md" level="2" class="text-text-primary">Configurar columnas</flux:heading>
            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" variant="ghost" @click="restaurarPorDefecto()">Restaurar por defecto</flux:button>
                <flux:button size="sm" variant="ghost" @click="mostrarTodas()">Mostrar todas</flux:button>
                <flux:button size="sm" variant="ghost" @click="soloCriticas()">Solo críticas</flux:button>
                <flux:button size="sm" icon="x-mark" variant="ghost" @click="panelColumnasAbierto = false">Cerrar</flux:button>
            </div>
        </div>
        <p class="text-xs text-text-secondary">
            Marca las columnas visibles. Usa ↑↓ para reordenarlas en este panel. Las preferencias se guardan en este navegador.
        </p>

        <div class="grid gap-1 max-h-96 overflow-y-auto">
            <template x-for="(clave, idx) in ordenColumnas" :key="clave">
                <div class="flex items-center gap-3 py-1.5 px-2 rounded hover:bg-bg-main">
                    <div class="flex flex-col leading-none">
                        <button type="button" @click="moverArriba(idx)" :disabled="idx === 0"
                                class="text-text-secondary hover:text-text-primary disabled:opacity-30"
                                aria-label="Subir">▲</button>
                        <button type="button" @click="moverAbajo(idx)" :disabled="idx === ordenColumnas.length - 1"
                                class="text-text-secondary hover:text-text-primary disabled:opacity-30"
                                aria-label="Bajar">▼</button>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer flex-1 min-w-0">
                        <input type="checkbox" :checked="visibles.includes(clave)" @change="toggleVisible(clave)"
                               class="size-4 rounded border-border" />
                        <span class="inline-block size-2.5 rounded-full shrink-0" :class="colorPrioridad(clave)" :title="prioridadOf(clave)"></span>
                        <span class="text-sm text-text-primary truncate" x-text="etiquetaOf(clave)"></span>
                        <span class="text-xs text-text-secondary ml-auto shrink-0" x-text="grupoOf(clave)"></span>
                    </label>
                </div>
            </template>
        </div>
    </div>

    {{-- Filtros rápidos --}}
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-medium text-text-secondary">Filtros rápidos:</span>
        @php
            $chips = [
                ['label' => 'Para revisión (todos)', 'criterio' => 'para_revision', 'valor' => ''],
                ['label' => 'Sin coordenadas válidas', 'criterio' => 'para_revision', 'valor' => 'coordenadas'],
                ['label' => 'Fechas no parseables', 'criterio' => 'para_revision', 'valor' => 'fecha'],
                ['label' => 'Sin occurrence_id', 'criterio' => 'para_revision', 'valor' => 'occurrence_id ausente'],
            ];
        @endphp
        @foreach($chips as $chip)
            @php $activo = $criterio === $chip['criterio'] && $valor === $chip['valor']; @endphp
            <button type="button"
                    wire:click="aplicarFiltroRapido('{{ $chip['criterio'] }}', '{{ $chip['valor'] }}')"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs transition-colors',
                        'bg-error text-white border-error' => $activo,
                        'border-border text-text-primary hover:bg-bg-main' => !$activo,
                    ])>
                {{ $chip['label'] }}
            </button>
        @endforeach
    </div>

    {{-- Formulario de búsqueda --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4">
        <flux:heading size="md" level="2" class="text-text-primary">Búsqueda</flux:heading>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <flux:field>
                <flux:label>Buscar por</flux:label>
                <flux:select wire:model.live="criterio">
                    <option value="para_revision">Para revisión (motivo)</option>
                    <option value="taxon">Nombre de Taxón</option>
                    <option value="codigo">Código</option>
                    <option value="occurrence_id">Occurrence ID</option>
                    <option value="catalog_number">Catalog Number</option>
                    <option value="localidad">Localidad</option>
                    <option value="estado">Estado</option>
                </flux:select>
                <flux:error name="criterio" />
            </flux:field>

            <flux:field class="md:col-span-2">
                <flux:label>
                    Valor
                    @if($criterio === 'para_revision')
                        <span class="text-xs text-text-secondary font-normal">(vacío = todos los marcados; o palabra clave del motivo)</span>
                    @endif
                </flux:label>
                <flux:input
                    wire:model="valor"
                    wire:keydown.enter="buscar"
                    placeholder="{{ $criterio === 'para_revision' ? 'Ej. coordenadas, fecha, occurrence_id' : 'Ingrese el valor de búsqueda...' }}"
                />
                <flux:error name="valor" />
            </flux:field>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <flux:button variant="primary" icon="magnifying-glass" wire:click="buscar" wire:loading.attr="disabled" class="w-full sm:w-auto">
                <span wire:loading.remove wire:target="buscar">Buscar</span>
                <span wire:loading wire:target="buscar">Buscando...</span>
            </flux:button>
            @if($buscado)<flux:button variant="ghost" icon="x-mark" wire:click="limpiar">Limpiar</flux:button>@endif
        </div>
    </div>

    {{-- Resultados --}}
    @if($buscado)
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-bg-main border-b border-border flex flex-wrap items-center justify-between gap-2">
                <span class="text-sm font-medium text-text-primary">{{ $totalItems }} resultado(s)</span>
                @if($criterio === 'para_revision')
                    <span class="text-xs text-text-secondary">Mostrando especímenes marcados por el importador</span>
                @endif
            </div>

            {{-- Escritorio --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-blue-navy">
                        <tr>
                            @foreach($columnasRegistro as $col)
                                <th class="px-4 py-3 text-left font-medium text-white whitespace-nowrap"
                                    x-show="visibles.includes('{{ $col['clave'] }}')" x-cloak>
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="inline-block size-2 rounded-full
                                            @if($col['prioridad']==='critica') bg-error
                                            @elseif($col['prioridad']==='recomendada') bg-warning
                                            @else bg-text-secondary
                                            @endif"></span>
                                        {{ $col['etiqueta'] }}
                                    </span>
                                </th>
                            @endforeach
                            <th class="px-4 py-3 text-left font-medium text-white whitespace-nowrap">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($especimenesPaginados as $especimen)
                            <tr class="hover:bg-bg-main transition-colors align-top
                                @if(($especimen['estadoRevision'] ?? '') === 'pendiente' && !empty($especimen['motivoRevision']))
                                    border-l-4 border-l-error
                                @endif">
                                @foreach($columnasRegistro as $col)
                                    <td class="px-4 py-3 text-text-primary"
                                        x-show="visibles.includes('{{ $col['clave'] }}')" x-cloak>
                                        {!! $renderCelda($especimen, $col['clave']) !!}
                                    </td>
                                @endforeach
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        @if(($especimen['estadoRevision'] ?? '') === 'pendiente' && !empty($especimen['motivoRevision']))
                                            <flux:button size="sm" variant="primary" icon="check"
                                                         wire:click="confirmarRevision('{{ $especimen['id'] }}')">
                                                Confirmar
                                            </flux:button>
                                        @endif
                                        @if(($especimen['estado'] ?? '') === 'disponible')
                                            <flux:button size="sm" variant="ghost" icon="pencil"
                                                         wire:click="abrirEditModal('{{ $especimen['id'] }}')">
                                                Editar
                                            </flux:button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="99" class="px-4 py-8 text-center text-text-secondary">No se encontraron especímenes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Móvil --}}
            <div class="md:hidden divide-y divide-border">
                @forelse($especimenesPaginados as $especimen)
                    <div class="p-4 space-y-2 @if(($especimen['estadoRevision'] ?? '') === 'pendiente' && !empty($especimen['motivoRevision'])) border-l-4 border-l-error @endif">
                        <div class="flex items-start justify-between gap-2">
                            <div class="font-medium text-text-primary text-sm break-all">{{ $especimen['codigoCatalogo'] }}</div>
                            {!! $renderCelda($especimen, 'estadoRevision') !!}
                        </div>
                        @foreach($columnasRegistro as $col)
                            @if($col['clave'] === 'codigoCatalogo' || $col['clave'] === 'estadoRevision')
                                @continue
                            @endif
                            <div class="flex justify-between gap-3" x-show="visibles.includes('{{ $col['clave'] }}')" x-cloak>
                                <dt class="shrink-0 text-text-secondary text-xs">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="inline-block size-2 rounded-full
                                            @if($col['prioridad']==='critica') bg-error
                                            @elseif($col['prioridad']==='recomendada') bg-warning
                                            @else bg-text-secondary
                                            @endif"></span>
                                        {{ $col['etiqueta'] }}
                                    </span>
                                </dt>
                                <dd class="text-right text-text-primary text-sm">{!! $renderCelda($especimen, $col['clave']) !!}</dd>
                            </div>
                        @endforeach
                        <div class="flex flex-wrap gap-2 pt-2">
                            @if(($especimen['estadoRevision'] ?? '') === 'pendiente' && !empty($especimen['motivoRevision']))
                                <flux:button variant="primary" icon="check"
                                             wire:click="confirmarRevision('{{ $especimen['id'] }}')">
                                    Confirmar revisión
                                </flux:button>
                            @endif
                            @if(($especimen['estado'] ?? '') === 'disponible')
                                <flux:button variant="ghost" icon="pencil"
                                             wire:click="abrirEditModal('{{ $especimen['id'] }}')">
                                    Editar
                                </flux:button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-text-secondary text-sm">No se encontraron especímenes.</div>
                @endforelse
            </div>

            <x-inventariogestioncoleccion::paginacion-tabla
                :pagina="$page" :total-paginas="$totalPaginas" :total-items="$totalItems"
                :inicio="$inicio" :fin="$fin" />
        </div>
    @endif

    {{-- Modal: Registrar --}}
    <flux:modal wire:model="showModal" class="w-full max-w-4xl">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Nuevo especímen</flux:heading>
            @if($errorMessage)<flux:callout variant="danger">{{ $errorMessage }}</flux:callout>@endif
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field><flux:label>Código de Catálogo</flux:label><flux:input wire:model="codigoCatalogo" /><flux:error name="codigoCatalogo" /></flux:field>
                <flux:field><flux:label>Fecha de Colecta</flux:label><flux:input type="date" wire:model="fechaColecta" /><flux:error name="fechaColecta" /></flux:field>
                <flux:field><flux:label>Occurrence ID</flux:label><flux:input wire:model="occurrenceId" /></flux:field>
                <flux:field><flux:label>Catalog Number</flux:label><flux:input wire:model="catalogNumber" /></flux:field>
                <flux:field><flux:label>Old code</flux:label><flux:input wire:model="oldCode" /></flux:field>
                <flux:field><flux:label>Cardex líquido</flux:label><flux:input wire:model="cardexLiquidCollectionCode" /></flux:field>
            </div>
            <flux:field>
                <flux:label>Taxón</flux:label>
                <flux:select wire:model="taxonId">
                    <option value="">Seleccione un taxón...</option>
                    @foreach($taxones as $taxon)<option value="{{ $taxon['id'] }}">{{ $taxon['label'] }}</option>@endforeach
                </flux:select>
                <flux:error name="taxonId" />
            </flux:field>
            <flux:field><flux:label>Localidad</flux:label><flux:input wire:model="localidad" /><flux:error name="localidad" /></flux:field>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field><flux:label>País</flux:label><flux:input wire:model="country" /></flux:field>
                <flux:field><flux:label>Provincia</flux:label><flux:input wire:model="stateProvince" /></flux:field>
                <flux:field><flux:label>Municipio</flux:label><flux:input wire:model="municipality" /></flux:field>
                <flux:field><flux:label>Locality name (DwC)</flux:label><flux:input wire:model="localityName" /></flux:field>
                <flux:field><flux:label>Latitud</flux:label><flux:input wire:model="decimalLatitude" /></flux:field>
                <flux:field><flux:label>Longitud</flux:label><flux:input wire:model="decimalLongitude" /></flux:field>
                <flux:field><flux:label>Datum</flux:label><flux:input wire:model="geodeticDatum" /></flux:field>
                <flux:field><flux:label>Elevación min (m)</flux:label><flux:input wire:model="elevationMinM" /></flux:field>
            </div>
            <flux:field><flux:label>Colector</flux:label><flux:input wire:model="colector" /><flux:error name="colector" /></flux:field>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field><flux:label>Individuos</flux:label><flux:input wire:model="individualCount" /></flux:field>
                <flux:field><flux:label>Preparación</flux:label><flux:input wire:model="preparations" /></flux:field>
                <flux:field><flux:label>Disposición</flux:label><flux:input wire:model="disposition" /></flux:field>
                <flux:field><flux:label>Occurrence status</flux:label><flux:input wire:model="occurrenceStatus" /></flux:field>
                <flux:field><flux:label>Bioma</flux:label><flux:input wire:model="biome" /></flux:field>
                <flux:field><flux:label>Hábitat</flux:label><flux:input wire:model="habitat" /></flux:field>
            </div>
            <flux:field><flux:label>Notas</flux:label><flux:textarea wire:model="specimenNotes" /></flux:field>
            <flux:field>
                <flux:label>Entidad depositante (opcional)</flux:label>
                <flux:select wire:model="entidadDepositanteId">
                    <option value="">Sin entidad depositante</option>
                    @foreach($entidades as $entidad)<option value="{{ $entidad['id'] }}">{{ $entidad['label'] }}</option>@endforeach
                </flux:select>
            </flux:field>
            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="registrarEspecimen">Registrar</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: Editar --}}
    <flux:modal wire:model="showEditModal" class="w-full max-w-4xl">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Editar especímen</flux:heading>
            @if($errorMessage)<flux:callout variant="danger">{{ $errorMessage }}</flux:callout>@endif
            <flux:field><flux:label>Localidad</flux:label><flux:input wire:model="editLocalidad" /><flux:error name="editLocalidad" /></flux:field>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field><flux:label>País</flux:label><flux:input wire:model="editCountry" /></flux:field>
                <flux:field><flux:label>Provincia</flux:label><flux:input wire:model="editStateProvince" /></flux:field>
                <flux:field><flux:label>Municipio</flux:label><flux:input wire:model="editMunicipality" /></flux:field>
                <flux:field><flux:label>Locality name (DwC)</flux:label><flux:input wire:model="editLocalityName" /></flux:field>
                <flux:field><flux:label>Latitud</flux:label><flux:input wire:model="editDecimalLatitude" /></flux:field>
                <flux:field><flux:label>Longitud</flux:label><flux:input wire:model="editDecimalLongitude" /></flux:field>
                <flux:field><flux:label>Datum</flux:label><flux:input wire:model="editGeodeticDatum" /></flux:field>
                <flux:field><flux:label>Elevación min (m)</flux:label><flux:input wire:model="editElevationMinM" /></flux:field>
            </div>
            <flux:field><flux:label>Fecha de Colecta</flux:label><flux:input type="date" wire:model="editFechaColecta" /><flux:error name="editFechaColecta" /></flux:field>
            <flux:field><flux:label>Colector</flux:label><flux:input wire:model="editColector" /><flux:error name="editColector" /></flux:field>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field><flux:label>Preparación</flux:label><flux:input wire:model="editPreparations" /></flux:field>
                <flux:field><flux:label>Disposición</flux:label><flux:input wire:model="editDisposition" /></flux:field>
                <flux:field><flux:label>Occurrence status</flux:label><flux:input wire:model="editOccurrenceStatus" /></flux:field>
                <flux:field><flux:label>Bioma</flux:label><flux:input wire:model="editBiome" /></flux:field>
                <flux:field class="md:col-span-2"><flux:label>Hábitat</flux:label><flux:input wire:model="editHabitat" /></flux:field>
            </div>
            <flux:field><flux:label>Notas</flux:label><flux:textarea wire:model="editSpecimenNotes" rows="3" /></flux:field>
            <flux:field>
                <flux:label>Entidad depositante (opcional)</flux:label>
                <flux:select wire:model="editEntidadDepositanteId">
                    <option value="">Sin entidad depositante</option>
                    @foreach($entidades as $entidad)<option value="{{ $entidad['id'] }}">{{ $entidad['label'] }}</option>@endforeach
                </flux:select>
            </flux:field>
            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showEditModal', false)">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="actualizarEspecimen">Guardar</flux:button>
            </div>
        </div>
    </flux:modal>

    <script>
        function especimenesIndex(cfg) {
            const STORAGE_KEY = 'inventario.especimenes.columnas.v1';
            const meta = {!! json_encode(array_map(fn($c) => [
                'clave' => $c['clave'],
                'etiqueta' => $c['etiqueta'],
                'grupo' => $c['grupo'],
                'prioridad' => $c['prioridad'],
            ], $columnasRegistro)) !!};
            const byKey = Object.fromEntries(meta.map(m => [m.clave, m]));

            return {
                panelColumnasAbierto: false,
                ordenColumnas: [],
                visibles: [],

                init() {
                    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
                    if (saved && Array.isArray(saved.orden) && Array.isArray(saved.visibles)) {
                        this.ordenColumnas = saved.orden.filter(k => cfg.claves.includes(k));
                        cfg.claves.forEach(k => { if (!this.ordenColumnas.includes(k)) this.ordenColumnas.push(k); });
                        this.visibles = saved.visibles.filter(k => cfg.claves.includes(k));
                    } else {
                        this.ordenColumnas = [...cfg.claves];
                        this.visibles = [...cfg.visiblesPorDefecto];
                    }
                    this.persist();
                },
                persist() {
                    localStorage.setItem(STORAGE_KEY, JSON.stringify({ orden: this.ordenColumnas, visibles: this.visibles }));
                },
                toggleVisible(clave) {
                    const idx = this.visibles.indexOf(clave);
                    if (idx >= 0) this.visibles.splice(idx, 1);
                    else this.visibles.push(clave);
                    this.persist();
                },
                moverArriba(idx) {
                    if (idx === 0) return;
                    [this.ordenColumnas[idx - 1], this.ordenColumnas[idx]] = [this.ordenColumnas[idx], this.ordenColumnas[idx - 1]];
                    this.persist();
                },
                moverAbajo(idx) {
                    if (idx === this.ordenColumnas.length - 1) return;
                    [this.ordenColumnas[idx + 1], this.ordenColumnas[idx]] = [this.ordenColumnas[idx], this.ordenColumnas[idx + 1]];
                    this.persist();
                },
                restaurarPorDefecto() {
                    this.ordenColumnas = [...cfg.claves];
                    this.visibles = [...cfg.visiblesPorDefecto];
                    this.persist();
                },
                mostrarTodas() {
                    this.visibles = [...this.ordenColumnas];
                    this.persist();
                },
                soloCriticas() {
                    this.visibles = this.ordenColumnas.filter(k => byKey[k]?.prioridad === 'critica');
                    this.persist();
                },
                etiquetaOf(clave) { return byKey[clave]?.etiqueta ?? clave; },
                grupoOf(clave) { return byKey[clave]?.grupo ?? ''; },
                prioridadOf(clave) { return byKey[clave]?.prioridad ?? ''; },
                colorPrioridad(clave) {
                    const p = byKey[clave]?.prioridad;
                    if (p === 'critica') return 'bg-error';
                    if (p === 'recomendada') return 'bg-warning';
                    return 'bg-text-secondary';
                },
            };
        }
    </script>
</div>
