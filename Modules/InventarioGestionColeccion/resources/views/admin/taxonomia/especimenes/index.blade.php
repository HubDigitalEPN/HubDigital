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
        <flux:heading size="xl" level="1" class="font-display text-blue-navy font-bold">Especímenes</flux:heading>
        <div class="flex flex-wrap gap-2">
            <flux:button icon="clipboard-document-check" variant="ghost"
                         :href="route('inventario.taxonomia.revision')" wire:navigate>
                Centro de revisión
            </flux:button>
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

    {{-- Presets rápidos --}}
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-medium text-text-secondary">Presets:</span>
        @php
            $presets = [
                ['label' => 'Para revisión', 'key' => 'para_revision'],
                ['label' => 'Sin coordenadas', 'key' => 'sin_coords'],
                ['label' => 'Fechas no parseables', 'key' => 'fechas_raras'],
                ['label' => 'Sin ID de ocurrencia', 'key' => 'sin_occurrence_id'],
                ['label' => 'Limpiar filtros', 'key' => 'todos'],
            ];
        @endphp
        @foreach($presets as $p)
            <button type="button" wire:click="preset('{{ $p['key'] }}')"
                    class="inline-flex items-center gap-1.5 rounded-full border border-border bg-surface px-3 py-1 text-xs hover:bg-bg-main transition-colors">
                {{ $p['label'] }}
            </button>
        @endforeach
    </div>

    {{-- Panel de filtros múltiples --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm">
        <div class="px-5 py-4 bg-bg-main border-b border-border flex items-center justify-between gap-2">
            <div>
                <flux:heading size="md" level="2" class="text-text-primary">Filtros de búsqueda</flux:heading>
                <p class="text-xs text-text-secondary mt-1">Todos opcionales. Se combinan con AND (todos deben cumplirse).</p>
            </div>
            <flux:button size="sm" variant="ghost"
                         icon="{{ $filtrosAbiertos ? 'chevron-up' : 'chevron-down' }}"
                         wire:click="$toggle('filtrosAbiertos')">
                {{ $filtrosAbiertos ? 'Plegar' : 'Mostrar' }}
            </flux:button>
        </div>

        @if($filtrosAbiertos)
            <div class="p-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:field>
                    <flux:label>Taxón (nombre científico)</flux:label>
                    <flux:input wire:model="fTaxon" wire:keydown.enter="buscar" placeholder="Ej. Morpho, peleides, Nymphal..." />
                </flux:field>

                <flux:field>
                    <flux:label>Familia (busca descendientes)</flux:label>
                    <flux:input wire:model="fFamilia" wire:keydown.enter="buscar" placeholder="Ej. Nymphalidae" />
                </flux:field>

                <flux:field>
                    <flux:label>Colector</flux:label>
                    <flux:input wire:model="fColector" wire:keydown.enter="buscar" placeholder="Ej. Villamarín" />
                </flux:field>

                <flux:field>
                    <flux:label>Localidad / país / provincia</flux:label>
                    <flux:input wire:model="fLocalidad" wire:keydown.enter="buscar" placeholder="Ej. Yasuní" />
                </flux:field>

                <flux:field>
                    <flux:label>Fecha desde</flux:label>
                    <flux:input type="date" wire:model="fFechaDesde" />
                </flux:field>

                <flux:field>
                    <flux:label>Fecha hasta</flux:label>
                    <flux:input type="date" wire:model="fFechaHasta" />
                </flux:field>

                <flux:field>
                    <flux:label>Código catálogo</flux:label>
                    <flux:input wire:model="fCodigoCatalogo" wire:keydown.enter="buscar" placeholder="MEPN:INV:1234" />
                </flux:field>

                <flux:field>
                    <flux:label>ID de ocurrencia <span class="text-text-secondary font-normal">(occurrenceID)</span></flux:label>
                    <flux:input wire:model="fOccurrenceId" wire:keydown.enter="buscar" placeholder="MEPN:INV:..." />
                </flux:field>

                <flux:field>
                    <flux:label>Número de catálogo <span class="text-text-secondary font-normal">(catalogNumber)</span></flux:label>
                    <flux:input wire:model="fCatalogNumber" wire:keydown.enter="buscar" placeholder="50494" />
                </flux:field>

                <flux:field>
                    <flux:label>Estado físico</flux:label>
                    <flux:select wire:model="fEstado">
                        <option value="">— Cualquiera —</option>
                        <option value="disponible">Disponible</option>
                        <option value="en_prestamo">En préstamo</option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Estado de revisión</flux:label>
                    <flux:select wire:model="fEstadoRevision">
                        <option value="">— Cualquiera —</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="confirmada">Confirmada</option>
                        <option value="descartada">Descartada</option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Motivo revisión contiene</flux:label>
                    <flux:input wire:model="fMotivoRevision" wire:keydown.enter="buscar" placeholder="Ej. coordenadas, fecha" />
                </flux:field>

                <flux:field class="sm:col-span-2 lg:col-span-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="fParaRevision" class="size-4 rounded border-border" />
                        <span class="text-sm text-text-primary">Solo los marcados para revisión (pendientes con un motivo)</span>
                    </label>
                </flux:field>
            </div>

            <div class="px-5 pb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                @if($buscado)<flux:button variant="ghost" icon="x-mark" wire:click="limpiar">Limpiar filtros</flux:button>@endif
                <flux:button variant="primary" icon="magnifying-glass" wire:click="buscar" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="buscar">Buscar</span>
                    <span wire:loading wire:target="buscar">Buscando...</span>
                </flux:button>
            </div>
        @endif
    </div>

    {{-- Panel del código QR del espécimen --}}
    @if($qrUrl)
        {{-- wire:key dinámico: al cambiar de espécimen, Alpine reinicia x-data y
             `codigo` deja de apuntar al espécimen anterior (evita imprimir una
             etiqueta con el código de uno y el QR de otro). --}}
        <div
            wire:key="panel-qr-{{ md5($qrUrl) }}"
            class="relative rounded-lg border border-border bg-surface p-4 shadow-sm sm:p-6"
            x-data="{
                codigo: @js($qrEspecimenCodigo),
                imprimir() {
                    const w = window.open('', '_blank', 'width=400,height=520');
                    if (! w) { window.alert('Habilita las ventanas emergentes para imprimir la etiqueta.'); return; }
                    const d = w.document;
                    d.title = 'QR ' + this.codigo;
                    d.body.style.cssText = 'margin:0;display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;font-family:sans-serif';
                    const caja = d.createElement('div');
                    caja.style.width = '220px';
                    caja.innerHTML = this.$refs.caja.innerHTML;
                    const label = d.createElement('p');
                    label.style.cssText = 'font-weight:600;margin-top:8px';
                    label.textContent = this.codigo;
                    d.body.appendChild(caja);
                    d.body.appendChild(label);
                    w.focus();
                    w.print();
                },
            }"
        >
            <flux:button variant="subtle" icon="x-mark" wire:click="cerrarQr"
                         class="absolute right-3 top-3" aria-label="Cerrar" />

            <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                {{-- SVG generado en el servidor: se dibuja sin JS ni CDN, funciona offline. --}}
                <div x-ref="caja" class="w-[220px] shrink-0 rounded-lg bg-white p-3 shadow-sm">
                    {!! $qrSvg !!}
                </div>

                <div class="min-w-0 flex-1 space-y-3 pr-10">
                    <flux:heading size="lg" class="text-text-primary">
                        Código QR de {{ $qrEspecimenCodigo }}
                    </flux:heading>
                    <p class="text-sm text-text-secondary">
                        Imprime este código y pégalo en el espécimen. Al escanearlo desde el móvil se abre su ficha digital.
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <flux:button variant="primary" icon="printer" @click="imprimir()">
                            Imprimir
                        </flux:button>
                        <flux:button variant="ghost" icon="arrow-down-tray"
                                     href="data:image/svg+xml;charset=utf-8,{{ rawurlencode($qrSvg) }}"
                                     download="qr-{{ $qrEspecimenCodigo }}.svg">
                            Descargar SVG
                        </flux:button>
                    </div>

                    <flux:field>
                        <flux:label>Enlace de la ficha</flux:label>
                        <flux:input readonly value="{{ $qrUrl }}" />
                    </flux:field>
                </div>
            </div>
        </div>
    @endif

    {{-- Resultados --}}
    @if($buscado)
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-bg-main border-b border-border flex flex-wrap items-center justify-between gap-2">
                <span class="text-sm font-medium text-text-primary">{{ $totalItems }} resultado(s)</span>
                @if($fParaRevision)
                    <span class="text-xs text-text-secondary">Mostrando solo los pendientes de revisión</span>
                @endif
            </div>

            {{-- Escritorio: CSS grid (cada fila = grid container) para que reorder + show/hide funcionen vía CSS `order`. --}}
            <div class="hidden md:block overflow-x-auto" role="table" aria-label="Especímenes">
                {{-- Header --}}
                <div role="row"
                     class="grid bg-blue-navy text-white text-sm"
                     :style="`grid-template-columns: repeat(${visibles.length + 1}, minmax(140px, auto));`">
                    @foreach($columnasRegistro as $col)
                        <div role="columnheader"
                             class="px-4 py-3 text-left font-medium whitespace-nowrap"
                             x-show="visibles.includes('{{ $col['clave'] }}')" x-cloak
                             :style="`order: ${ordenColumnas.indexOf('{{ $col['clave'] }}')}`">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="inline-block size-2 rounded-full
                                    @if($col['prioridad']==='critica') bg-error
                                    @elseif($col['prioridad']==='recomendada') bg-warning
                                    @else bg-text-secondary
                                    @endif"></span>
                                {{ $col['etiqueta'] }}
                            </span>
                        </div>
                    @endforeach
                    <div role="columnheader"
                         class="px-4 py-3 text-left font-medium whitespace-nowrap"
                         style="order: 99999;">
                        Acciones
                    </div>
                </div>

                {{-- Filas --}}
                @forelse($especimenesPaginados as $especimen)
                    <div role="row"
                         class="grid border-t border-border hover:bg-bg-main transition-colors text-sm
                             @if(($especimen['estadoRevision'] ?? '') === 'pendiente' && !empty($especimen['motivoRevision']))
                                 border-l-4 border-l-error
                             @endif"
                         :style="`grid-template-columns: repeat(${visibles.length + 1}, minmax(140px, auto));`">
                        @foreach($columnasRegistro as $col)
                            <div role="cell"
                                 class="px-4 py-3 text-text-primary align-top"
                                 x-show="visibles.includes('{{ $col['clave'] }}')" x-cloak
                                 :style="`order: ${ordenColumnas.indexOf('{{ $col['clave'] }}')}`">
                                {!! $renderCelda($especimen, $col['clave']) !!}
                            </div>
                        @endforeach
                        <div role="cell"
                             class="px-4 py-3 whitespace-nowrap"
                             style="order: 99999;">
                            <div class="flex items-center gap-2">
                                @if(($especimen['estadoRevision'] ?? '') === 'pendiente' && !empty($especimen['motivoRevision']))
                                    <flux:button size="sm" variant="primary" icon="check"
                                                 wire:click="confirmarRevision('{{ $especimen['id'] }}')"
                                                 wire:confirm="¿Confirmar la revisión de este espécimen? Se marcará como revisado y se limpiará su motivo.">
                                        Confirmar
                                    </flux:button>
                                @endif
                                @if(($especimen['estado'] ?? '') === 'disponible')
                                    <flux:button size="sm" variant="ghost" icon="pencil"
                                                 wire:click="abrirEditModal('{{ $especimen['id'] }}')">
                                        Editar
                                    </flux:button>
                                @endif
                                <flux:button size="sm" variant="ghost" icon="qr-code"
                                             wire:click="mostrarQr('{{ $especimen['id'] }}')"
                                             wire:loading.attr="disabled"
                                             wire:target="mostrarQr('{{ $especimen['id'] }}')">
                                    QR
                                </flux:button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-text-secondary border-t border-border">No se encontraron especímenes.</div>
                @endforelse
            </div>

            {{-- Móvil --}}
            <div class="md:hidden divide-y divide-border">
                @forelse($especimenesPaginados as $especimen)
                    <div class="p-4 flex flex-col gap-2 @if(($especimen['estadoRevision'] ?? '') === 'pendiente' && !empty($especimen['motivoRevision'])) border-l-4 border-l-error @endif">
                        <div class="flex items-start justify-between gap-2" style="order: -1;">
                            <div class="font-medium text-text-primary text-sm break-all">{{ $especimen['codigoCatalogo'] }}</div>
                            {!! $renderCelda($especimen, 'estadoRevision') !!}
                        </div>
                        @foreach($columnasRegistro as $col)
                            @if($col['clave'] === 'codigoCatalogo' || $col['clave'] === 'estadoRevision')
                                @continue
                            @endif
                            <div class="flex justify-between gap-3"
                                 x-show="visibles.includes('{{ $col['clave'] }}')" x-cloak
                                 :style="`order: ${ordenColumnas.indexOf('{{ $col['clave'] }}')}`">
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
                        <div class="flex flex-wrap gap-2 pt-2" style="order: 99999;">
                            @if(($especimen['estadoRevision'] ?? '') === 'pendiente' && !empty($especimen['motivoRevision']))
                                <flux:button variant="primary" icon="check"
                                             wire:click="confirmarRevision('{{ $especimen['id'] }}')"
                                                 wire:confirm="¿Confirmar la revisión de este espécimen? Se marcará como revisado y se limpiará su motivo.">
                                    Confirmar revisión
                                </flux:button>
                            @endif
                            @if(($especimen['estado'] ?? '') === 'disponible')
                                <flux:button variant="ghost" icon="pencil"
                                             wire:click="abrirEditModal('{{ $especimen['id'] }}')">
                                    Editar
                                </flux:button>
                            @endif
                            <flux:button variant="ghost" icon="qr-code"
                                         wire:click="mostrarQr('{{ $especimen['id'] }}')"
                                         wire:loading.attr="disabled"
                                         wire:target="mostrarQr('{{ $especimen['id'] }}')">
                                Código QR
                            </flux:button>
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
            {{-- Detección de ubicación por coordenadas --}}
            <div class="rounded-lg border border-border bg-bg-main p-3 space-y-2">
                <flux:button type="button" variant="filled" icon="map-pin"
                             wire:click="detectarUbicacion" wire:loading.attr="disabled" wire:target="detectarUbicacion">
                    <span wire:loading.remove wire:target="detectarUbicacion">Detectar por coordenadas</span>
                    <span wire:loading wire:target="detectarUbicacion">Detectando…</span>
                </flux:button>
                @if($geoMensaje)<p class="text-xs text-warning">{{ $geoMensaje }}</p>@endif
                @if($ubicacionDetectada)
                    <p class="text-xs text-text-secondary">
                        <flux:icon name="map-pin" class="inline size-3.5 -mt-0.5 text-science-blue" />
                        Ubicación detectada: <span class="text-text-primary">{{ $ubicacionDetectada }}</span>
                    </p>
                @endif
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
                <flux:button variant="primary" wire:click="registrarEspecimen"
                             wire:loading.attr="disabled" wire:target="registrarEspecimen">
                    <span wire:loading.remove wire:target="registrarEspecimen">Registrar</span>
                    <span wire:loading wire:target="registrarEspecimen">Registrando…</span>
                </flux:button>
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
            {{-- Detección de ubicación por coordenadas --}}
            <div class="rounded-lg border border-border bg-bg-main p-3 space-y-2">
                <flux:button type="button" variant="filled" icon="map-pin"
                             wire:click="detectarUbicacionEdicion" wire:loading.attr="disabled" wire:target="detectarUbicacionEdicion">
                    <span wire:loading.remove wire:target="detectarUbicacionEdicion">Detectar por coordenadas</span>
                    <span wire:loading wire:target="detectarUbicacionEdicion">Detectando…</span>
                </flux:button>
                @if($geoMensaje)<p class="text-xs text-warning">{{ $geoMensaje }}</p>@endif
                @if($ubicacionDetectada)
                    <p class="text-xs text-text-secondary">
                        <flux:icon name="map-pin" class="inline size-3.5 -mt-0.5 text-science-blue" />
                        Ubicación detectada: <span class="text-text-primary">{{ $ubicacionDetectada }}</span>
                    </p>
                @endif
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
                <flux:button variant="primary" wire:click="actualizarEspecimen"
                             wire:loading.attr="disabled" wire:target="actualizarEspecimen">
                    <span wire:loading.remove wire:target="actualizarEspecimen">Guardar</span>
                    <span wire:loading wire:target="actualizarEspecimen">Guardando…</span>
                </flux:button>
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
