@php
    /**
     * Valor plano de una celda, para el atributo `title` y para las tarjetas
     * móviles. Devuelve '' cuando no hay dato.
     */
    $textoCelda = function (array $e, string $clave): string {
        $v = $e[$clave] ?? null;

        if ($clave === 'taxonNombre' && $v === null) {
            return $e['taxonVerbatim'] !== null ? $e['taxonVerbatim'].' (verbatim)' : '';
        }
        if ($clave === 'endemic') {
            return $v === null ? '' : ($v ? 'Sí' : 'No');
        }
        if ($clave === 'estado') {
            return ucfirst(str_replace('_', ' ', (string) ($v ?? '')));
        }

        return $v === null || $v === '' ? '' : (string) $v;
    };

    /** HTML de la celda: badges donde aportan, texto plano en el resto. */
    $renderCelda = function (array $e, string $clave) use ($textoCelda): string {
        $v = $e[$clave] ?? null;
        $vacio = '<span class="text-text-secondary">·</span>';

        if ($clave === 'taxonNombre') {
            return $v !== null
                ? '<span class="font-serif italic">'.e($v).'</span>'
                : ($e['taxonVerbatim'] !== null
                    ? '<span class="text-text-secondary italic">'.e($e['taxonVerbatim']).'</span>'
                    : $vacio);
        }
        if ($clave === 'estadoRevision') {
            $estado = (string) ($v ?? 'pendiente');
            $clases = match ($estado) {
                'pendiente' => 'bg-warning/10 text-warning border-warning',
                'confirmada' => 'bg-success/10 text-success border-success',
                'descartada' => 'bg-error/10 text-error border-error',
                default => 'bg-border/30 text-text-secondary border-border',
            };

            return '<span class="inline-flex items-center rounded-full border px-1.5 font-sans font-semibold whitespace-nowrap '.$clases.'">'.e(ucfirst($estado)).'</span>';
        }
        if ($clave === 'estado') {
            $estado = (string) ($v ?? '');
            $clases = match ($estado) {
                'disponible' => 'bg-success text-white',
                'en_prestamo' => 'bg-warning text-white',
                default => 'bg-border text-text-primary',
            };

            return $estado === ''
                ? $vacio
                : '<span class="inline-flex items-center rounded px-1.5 font-sans font-semibold '.$clases.'">'.e(ucfirst(str_replace('_', ' ', $estado))).'</span>';
        }
        if ($clave === 'endemic') {
            if ($v === null) {
                return $vacio;
            }

            return $v
                ? '<span class="text-bio-green font-semibold">Sí</span>'
                : '<span class="text-text-secondary">No</span>';
        }
        if (in_array($clave, ['taxonVerbatim', 'localidadVerbatim', 'fechaVerbatim', 'coordVerbatim', 'individualCountVerbatim'], true)) {
            $t = $textoCelda($e, $clave);

            return $t === '' ? $vacio : '<span class="text-text-secondary italic">'.e($t).'</span>';
        }

        $t = $textoCelda($e, $clave);

        return $t === '' ? $vacio : e($t);
    };

    /** Alineación a la derecha para columnas numéricas, como en una hoja de cálculo. */
    $clavesNumericas = ['decimalLatitude', 'decimalLongitude', 'elevationMinM', 'elevationMaxM',
                        'individualCount', 'filaOrigenExcel', 'latLonMaxError'];

    /**
     * Botones de fila de la tabla densa. Se usan botones nativos con iconos de un
     * sprite en lugar de <flux:button>: cada uno de esos arrastra ~1,2 KB de
     * clases, y con cientos de filas eso dominaba el peso de la página. Las
     * tarjetas móviles sí conservan <flux:button> (área táctil ≥44 px).
     */
    $btnFila = 'inline-flex size-7 items-center justify-center rounded border border-border '
             . 'text-text-secondary transition-colors hover:bg-science-blue/10 hover:text-science-blue';

    /** Triángulo de aviso: mismo tamaño que el resto, en color de advertencia. */
    $btnAviso = 'inline-flex size-7 items-center justify-center rounded border border-warning '
              . 'bg-warning/10 text-warning transition-colors hover:bg-warning/20';
@endphp

{{-- La selección vive ÍNTEGRAMENTE en el navegador y solo se envía al servidor
     como argumento cuando hace falta.

     No se sincroniza a una propiedad de Livewire, ni con `wire:model`, ni con
     `$set`: en ambos casos Livewire descompone el cambio en rutas por clave
     (`seleccionados.<uuid>`) y decide cómo aplicarlas según si `parseInt()`
     considera numérica la clave. Un UUID que empieza por dígito («019bc718…»)
     se interpreta como índice de array. Medido en el navegador: de 5 casillas
     marcadas llegaban 2 — justo las dos cuyo UUID empezaba por letra. Una
     edición masiva habría escrito en el conjunto equivocado sin avisar de nada.

     Pasar los ids como ARGUMENTO de método los serializa como un array JSON
     plano, sin diffing por clave. Y marcar deja de costar peticiones. --}}
<div class="space-y-4 p-4 sm:p-6"
     x-data="{
         sel: {},
         get n() { return Object.keys(this.sel).length },
         ids() { return Object.keys(this.sel) },
         marcado(id) { return this.sel[id] === true },
         alternar(id, valor) { if (valor) { this.sel[id] = true } else { delete this.sel[id] } },
         marcarPagina(lista) { lista.forEach(id => { this.sel[id] = true }) },
         limpiar() { this.sel = {} },

         /* Editor de celda flotante.

            Se mantiene UN solo input que se coloca encima de la celda pinchada,
            en vez de renderizar un campo por celda editable (serían ~450 por
            página) o de pedirle el editor al servidor. Abrir es instantáneo:
            antes el doble clic disparaba una acción de Livewire y el curador
            esperaba ~2 s, con la sensación de que la tabla se trababa. Solo el
            guardado viaja, porque es una escritura. */
         celda: { id: null, clave: null, valor: '', x: 0, y: 0, w: 0, h: 0 },
         editando(id, clave) { return this.celda.id === id && this.celda.clave === clave },
         abrirCelda(evento, id, clave, valor) {
             const td = evento.currentTarget;
             const caja = td.getBoundingClientRect();
             const cont = this.$refs.hoja.getBoundingClientRect();
             this.celda = {
                 id, clave, valor: valor ?? '',
                 x: caja.left - cont.left + this.$refs.hoja.scrollLeft,
                 y: caja.top - cont.top + this.$refs.hoja.scrollTop,
                 w: Math.max(caja.width, 160), h: caja.height,
             };
             this.$nextTick(() => { this.$refs.editor?.focus(); this.$refs.editor?.select() });
         },
         cerrarCelda() { this.celda = { id: null, clave: null, valor: '', x: 0, y: 0, w: 0, h: 0 } },
         guardarCelda() {
             const { id, clave, valor } = this.celda;
             this.cerrarCelda();
             if (id) { $wire.guardarCelda(id, clave, valor) }
         },
     }"
     @seleccion-aplicada.window="limpiar()">
{{-- Sprite de iconos: se define una vez y cada fila lo referencia con <use>. --}}
<svg class="hidden" aria-hidden="true" focusable="false">
    <symbol id="ico-ficha" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
        <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
    </symbol>
    <symbol id="ico-aviso" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
    </symbol>
    <symbol id="ico-pencil" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
    </symbol>
    <symbol id="ico-qr" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-3.75A1.125 1.125 0 013.75 8.625v-3.75zm10.5 0c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-3.75a1.125 1.125 0 01-1.125-1.125v-3.75zm-10.5 10.5c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-3.75a1.125 1.125 0 01-1.125-1.125v-3.75zM13.5 14.25h2.25v2.25H13.5zm4.5 0h2.25v2.25H18zm-4.5 4.5h2.25V21H13.5zm4.5 0h2.25V21H18z" />
    </symbol>
</svg>

    {{-- ── Encabezado ──────────────────────────────────────────────────────── --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="font-display text-blue-navy font-bold">Especímenes</flux:heading>
            <p class="text-xs text-text-secondary mt-1">
                Hoja de inventario del catálogo. Ordena por cualquier columna y ajusta cuáles ver.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button icon="clipboard-document-check" variant="ghost"
                         :href="route('inventario.taxonomia.revision')" wire:navigate>
                Centro de revisión
            </flux:button>
            <flux:button icon="plus" variant="primary" wire:click="abrirModal" class="w-full sm:w-auto">
                Nuevo especímen
            </flux:button>
        </div>
    </div>

    @if($successMessage)<flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>@endif
    @if($errorMessage && !$showModal && !$showEditModal)<flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>@endif

    {{-- ── Barra de herramientas ───────────────────────────────────────────── --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm">
        <div class="flex flex-col gap-3 p-3 lg:flex-row lg:items-center">
            {{-- Búsqueda rápida: una sola caja contra códigos, taxón, colector y localidad. --}}
            <div class="relative flex-1 min-w-0">
                <flux:icon name="magnifying-glass"
                           class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-text-secondary" />
                <input type="search"
                       wire:model.live.debounce.400ms="q"
                       placeholder="Buscar por código, occurrenceID, taxón, colector o localidad…"
                       aria-label="Búsqueda rápida de especímenes"
                       class="h-11 w-full rounded-lg border border-border bg-bg-main pl-9 pr-9 text-sm text-text-primary placeholder:text-text-secondary focus:border-science-blue focus:outline-none focus:ring-1 focus:ring-science-blue" />
                <div wire:loading wire:target="q"
                     class="absolute right-3 top-1/2 size-4 -translate-y-1/2 animate-spin rounded-full border-2 border-border border-t-science-blue"></div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button icon="funnel" variant="{{ $filtrosAbiertos ? 'filled' : 'ghost' }}"
                             wire:click="$toggle('filtrosAbiertos')">
                    Filtros @if(count($filtrosActivos) > 0)<span class="ml-1 rounded-full bg-science-blue px-1.5 text-xs text-white">{{ count($filtrosActivos) }}</span>@endif
                </flux:button>

                <flux:button icon="view-columns" variant="{{ $panelColumnasAbierto ? 'filled' : 'ghost' }}"
                             wire:click="$toggle('panelColumnasAbierto')">
                    Columnas <span class="ml-1 text-text-secondary">{{ count($columnasTabla) }}</span>
                </flux:button>

                <label class="flex items-center gap-2 text-xs text-text-secondary">
                    Filas
                    <select wire:model.live="perPage"
                            class="h-11 rounded-lg border border-border bg-bg-main px-2 text-sm text-text-primary focus:border-science-blue focus:outline-none">
                        @foreach($tamanosPagina as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                    </select>
                </label>
            </div>
        </div>

        {{-- Chips de filtros activos --}}
        @if(count($filtrosActivos) > 0)
            <div class="flex flex-wrap items-center gap-2 border-t border-border px-3 py-2">
                @foreach($filtrosActivos as $f)
                    <button type="button" wire:click="quitarFiltro('{{ $f['campo'] }}')"
                            class="group inline-flex items-center gap-1.5 rounded-full border border-science-blue bg-science-blue/10 px-2.5 py-1 text-xs text-text-primary transition-colors hover:bg-science-blue/20"
                            title="Quitar este filtro">
                        <span class="text-text-secondary">{{ $f['etiqueta'] }}:</span>
                        <span class="font-medium">{{ $f['valor'] }}</span>
                        <flux:icon name="x-mark" class="size-3.5 text-text-secondary group-hover:text-error" />
                    </button>
                @endforeach
                <button type="button" wire:click="limpiar"
                        class="text-xs text-text-secondary underline underline-offset-2 hover:text-error">
                    Limpiar todo
                </button>
            </div>
        @endif

        {{-- Panel de filtros detallados --}}
        @if($filtrosAbiertos)
            <div class="border-t border-border p-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <flux:field>
                        <flux:label>Taxón (nombre científico)</flux:label>
                        <flux:input wire:model="fTaxon" wire:keydown.enter="buscar" placeholder="Ej. Morpho, peleides, Nymphal..." />
                        <flux:error name="fTaxon" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Familia (busca descendientes)</flux:label>
                        <flux:input wire:model="fFamilia" wire:keydown.enter="buscar" placeholder="Ej. Nymphalidae" />
                        <flux:error name="fFamilia" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Colector</flux:label>
                        <flux:input wire:model="fColector" wire:keydown.enter="buscar" placeholder="Ej. Villamarín" />
                        <flux:error name="fColector" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Localidad / país / provincia</flux:label>
                        <flux:input wire:model="fLocalidad" wire:keydown.enter="buscar" placeholder="Ej. Yasuní" />
                        <flux:error name="fLocalidad" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Fecha desde</flux:label>
                        <flux:input type="date" wire:model.live="fFechaDesde" />
                        <flux:error name="fFechaDesde" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Fecha hasta</flux:label>
                        <flux:input type="date" wire:model.live="fFechaHasta" />
                        <flux:error name="fFechaHasta" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Código catálogo</flux:label>
                        <flux:input wire:model="fCodigoCatalogo" wire:keydown.enter="buscar" placeholder="MEPN:INV:1234" />
                        <flux:error name="fCodigoCatalogo" />
                    </flux:field>

                    <flux:field>
                        <flux:label>ID de ocurrencia<span class="text-text-secondary font-normal">&nbsp;(occurrenceID)</span></flux:label>
                        <flux:input wire:model="fOccurrenceId" wire:keydown.enter="buscar" placeholder="MEPN:INV:..." />
                        <flux:error name="fOccurrenceId" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Número de catálogo<span class="text-text-secondary font-normal">&nbsp;(catalogNumber)</span></flux:label>
                        <flux:input wire:model="fCatalogNumber" wire:keydown.enter="buscar" placeholder="50494" />
                        <flux:error name="fCatalogNumber" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Estado físico</flux:label>
                        <flux:select wire:model.live="fEstado">
                            <option value="">— Cualquiera —</option>
                            <option value="disponible">Disponible</option>
                            <option value="en_prestamo">En préstamo</option>
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label>Estado de revisión</flux:label>
                        <flux:select wire:model.live="fEstadoRevision">
                            <option value="">— Cualquiera —</option>
                            <option value="pendiente">Pendiente</option>
                            <option value="confirmada">Confirmada</option>
                            <option value="descartada">Descartada</option>
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label>Motivo revisión contiene</flux:label>
                        <flux:input wire:model="fMotivoRevision" wire:keydown.enter="buscar" placeholder="Ej. coordenadas, fecha" />
                        <flux:error name="fMotivoRevision" />
                    </flux:field>

                    <flux:field class="sm:col-span-2 lg:col-span-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model.live="fParaRevision" class="size-4 rounded border-border" />
                            <span class="text-sm text-text-primary">Solo los marcados para revisión (pendientes con un motivo)</span>
                        </label>
                    </flux:field>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-medium text-text-secondary">Presets:</span>
                        @foreach([
                            ['label' => 'Para revisión', 'key' => 'para_revision'],
                            ['label' => 'Sin coordenadas', 'key' => 'sin_coords'],
                            ['label' => 'Fechas no parseables', 'key' => 'fechas_raras'],
                            ['label' => 'Sin ID de ocurrencia', 'key' => 'sin_occurrence_id'],
                            ['label' => 'Todo el catálogo', 'key' => 'todos'],
                        ] as $p)
                            <button type="button" wire:click="preset('{{ $p['key'] }}')"
                                    class="inline-flex items-center rounded-full border border-border bg-surface px-3 py-1 text-xs transition-colors hover:bg-bg-main">
                                {{ $p['label'] }}
                            </button>
                        @endforeach
                    </div>
                    <flux:button variant="primary" icon="magnifying-glass" wire:click="buscar"
                                 wire:loading.attr="disabled" class="w-full sm:w-auto">
                        <span wire:loading.remove wire:target="buscar">Aplicar filtros</span>
                        <span wire:loading wire:target="buscar">Buscando…</span>
                    </flux:button>
                </div>
            </div>
        @endif

        {{-- Panel de configuración de columnas --}}
        @if($panelColumnasAbierto)
            <div class="space-y-3 border-t border-border p-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <flux:heading size="md" level="2" class="text-text-primary">Configurar columnas</flux:heading>
                        <p class="mt-1 text-xs text-text-secondary">
                            Marca las columnas visibles y usa ↑↓ para reordenarlas. Se guardan en este navegador.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" variant="ghost" wire:click="restaurarColumnas">Por defecto</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="mostrarTodasColumnas">Mostrar todas</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="soloColumnasCriticas">Solo críticas</flux:button>
                        <flux:button size="sm" icon="x-mark" variant="ghost" wire:click="$set('panelColumnasAbierto', false)">Cerrar</flux:button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4 text-xs text-text-secondary">
                    <span class="font-medium">Prioridad:</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-full bg-error"></span> Crítica (requerida GBIF)</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-full bg-warning"></span> Recomendada</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-full bg-text-secondary"></span> Opcional</span>
                </div>

                @php $metaColumnas = collect($columnasRegistro)->keyBy('clave'); @endphp
                <div class="grid max-h-96 gap-1 overflow-y-auto">
                    @foreach($ordenColumnas as $i => $clave)
                        @php $col = $metaColumnas[$clave] ?? null; @endphp
                        @if($col)
                            <div wire:key="col-{{ $clave }}" class="flex items-center gap-3 rounded px-2 py-1.5 hover:bg-bg-main">
                                <div class="flex flex-col leading-none">
                                    <button type="button" wire:click="moverColumna('{{ $clave }}', -1)" @disabled($i === 0)
                                            class="text-text-secondary hover:text-text-primary disabled:opacity-30"
                                            aria-label="Subir {{ $col['etiqueta'] }}">▲</button>
                                    <button type="button" wire:click="moverColumna('{{ $clave }}', 1)" @disabled($i === count($ordenColumnas) - 1)
                                            class="text-text-secondary hover:text-text-primary disabled:opacity-30"
                                            aria-label="Bajar {{ $col['etiqueta'] }}">▼</button>
                                </div>
                                <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-2">
                                    <input type="checkbox" wire:click="toggleColumna('{{ $clave }}')"
                                           @checked(in_array($clave, $columnasVisibles, true))
                                           class="size-4 rounded border-border" />
                                    <span class="inline-block size-2.5 shrink-0 rounded-full
                                        @if($col['prioridad']==='critica') bg-error
                                        @elseif($col['prioridad']==='recomendada') bg-warning
                                        @else bg-text-secondary @endif" title="{{ $col['prioridad'] }}"></span>
                                    <span class="truncate text-sm text-text-primary">{{ $col['etiqueta'] }}</span>
                                    <span class="ml-auto shrink-0 text-xs text-text-secondary">{{ $col['grupo'] }}</span>
                                </label>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- ── Panel del código QR del espécimen ───────────────────────────────── --}}
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

    {{-- ── Hoja de inventario ──────────────────────────────────────────────── --}}
    <div class="overflow-hidden rounded-lg border border-border bg-surface shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-bg-main px-4 py-2">
            <span class="text-sm text-text-primary">
                <span class="font-semibold">{{ number_format($totalItems) }}</span>
                {{ $totalItems === 1 ? 'espécimen' : 'especímenes' }}
                @if(count($filtrosActivos) > 0)<span class="text-text-secondary">(filtrado)</span>@endif
            </span>
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs text-text-secondary" wire:loading>Cargando…</span>
                <flux:button size="sm" variant="ghost" icon="clock" wire:click="abrirHistorial">
                    Historial
                </flux:button>
            </div>
        </div>

        {{-- Barra de selección.

             Marcar casillas NO va al servidor. Las casillas usan `wire:model`
             SIN `.live`, así que Livewire actualiza su copia local y las marcas
             viajan de polizón en la siguiente petición que ocurra de todos modos
             (paginar, ordenar, abrir el panel). Antes cada clic costaba un
             round-trip completo: 5 consultas y ~2 s de espera.

             El contador vive en Alpine leyendo el mismo estado, para que la
             cifra reaccione al instante sin preguntar al servidor. --}}
        <div class="flex flex-col gap-3 border-b border-border bg-science-blue/5 px-4 py-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <span class="text-text-primary">
                    <span class="font-semibold" x-text="n">0</span> seleccionado(s)
                </span>
                <button type="button" class="text-xs text-science-blue underline underline-offset-2"
                        @click="marcarPagina(@js(array_column($filas, 'id')))">
                    Marcar los {{ count($filas) }} de esta página
                </button>
                <button type="button" x-show="n > 0" x-cloak
                        class="text-xs text-text-secondary underline underline-offset-2 hover:text-error"
                        @click="limpiar()">
                    Quitar selección
                </button>
            </div>
            <flux:button size="sm" variant="primary" icon="pencil-square"
                         @click="$wire.abrirPanelMasivo(ids())" x-bind:disabled="n === 0">
                Editar en bloque
            </flux:button>
        </div>

        {{-- Escritorio: tabla densa tipo hoja de cálculo, con encabezado y
             columna de índice fijos mientras se hace scroll. --}}
        <div class="relative hidden max-h-[70vh] overflow-auto md:block"
             x-ref="hoja" @scroll="cerrarCelda()">
            {{-- Editor flotante: uno solo para toda la tabla.

                 El `display` va dentro de este mismo `:style` y NO se usa `x-show`:
                 un binding de estilo por cadena reescribe el atributo entero en
                 cada actualización y borraba el `display:none` que x-show acababa
                 de poner, así que el editor no se cerraba nunca. --}}
            <input x-ref="editor" type="text"
                   x-model="celda.valor"
                   :style="celda.id
                       ? `display:block; left:${celda.x}px; top:${celda.y}px; width:${celda.w}px; height:${celda.h}px`
                       : 'display:none'"
                   style="display:none"
                   @keydown.enter.prevent="guardarCelda()"
                   @keydown.escape.prevent="cerrarCelda()"
                   @blur="cerrarCelda()"
                   class="absolute z-40 rounded border-2 border-science-blue bg-surface px-2 font-mono text-xs text-text-primary shadow-sm focus:outline-none" />

            <table class="w-full border-separate border-spacing-0 font-mono text-xs">
                <thead>
                    <tr>
                        <th scope="col"
                            class="sticky left-0 top-0 z-30 w-10 border-b border-r border-blue-navy/40 bg-blue-navy px-2 py-2 font-sans font-medium text-white">
                            <span class="sr-only">Seleccionar</span>
                        </th>
                        <th scope="col"
                            class="sticky left-10 top-0 z-30 w-12 border-b border-r border-blue-navy/40 bg-blue-navy px-2 py-2 text-right font-sans font-medium text-white">
                            #
                        </th>
                        @foreach($columnasTabla as $col)
                            @php $ordenable = in_array($col['clave'], $clavesOrdenables, true); @endphp
                            <th scope="col"
                                class="sticky top-0 z-20 whitespace-nowrap border-b border-r border-blue-navy/40 bg-blue-navy px-2 py-2 text-left font-sans font-medium text-white">
                                @if($ordenable)
                                    <button type="button" wire:click="ordenarPorColumna('{{ $col['clave'] }}')"
                                            class="inline-flex items-center gap-1.5 hover:underline"
                                            title="Ordenar por {{ $col['etiqueta'] }}">
                                        <span class="inline-block size-2 rounded-full
                                            @if($col['prioridad']==='critica') bg-error
                                            @elseif($col['prioridad']==='recomendada') bg-warning
                                            @else bg-white/50 @endif"></span>
                                        {{ $col['etiqueta'] }}
                                        @if($ordenarPor === $col['clave'])
                                            <span aria-hidden="true">{{ $ordenDireccion === 'asc' ? '▲' : '▼' }}</span>
                                            <span class="sr-only">({{ $ordenDireccion === 'asc' ? 'ascendente' : 'descendente' }})</span>
                                        @else
                                            <span class="text-white/30" aria-hidden="true">↕</span>
                                        @endif
                                    </button>
                                @else
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="inline-block size-2 rounded-full
                                            @if($col['prioridad']==='critica') bg-error
                                            @elseif($col['prioridad']==='recomendada') bg-warning
                                            @else bg-white/50 @endif"></span>
                                        {{ $col['etiqueta'] }}
                                    </span>
                                @endif
                            </th>
                        @endforeach
                        <th scope="col"
                            class="sticky right-0 top-0 z-30 border-b border-l border-blue-navy/40 bg-blue-navy px-2 py-2 text-left font-sans font-medium text-white">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($filas as $i => $especimen)
                        @php
                            $marcado = ($especimen['estadoRevision'] ?? '') === 'pendiente' && ! empty($especimen['motivoRevision']);
                            $fondo = $i % 2 === 0 ? 'bg-surface' : 'bg-bg-main';
                        @endphp
                        <tr wire:key="fila-{{ $especimen['id'] }}" class="group">
                            <td class="sticky left-0 z-10 border-b border-r border-border px-2 py-1
                                       {{ $fondo }} group-hover:bg-science-blue/10
                                       @if($marcado) border-l-4 border-l-error @endif">
                                <input type="checkbox" class="size-4 rounded border-border"
                                       :checked="marcado('{{ $especimen['id'] }}')"
                                       @change="alternar('{{ $especimen['id'] }}', $event.target.checked)"
                                       aria-label="Seleccionar {{ $especimen['codigoCatalogo'] }}" />
                            </td>
                            <td class="sticky left-10 z-10 border-b border-r border-border px-2 py-1 text-right text-text-secondary
                                       {{ $fondo }} group-hover:bg-science-blue/10">
                                {{ $offsetFila + $i + 1 }}
                            </td>
                            @foreach($columnasTabla as $col)
                                @php
                                    $texto = $textoCelda($especimen, $col['clave']);
                                    $editable = in_array($col['clave'], $clavesEditables, true);
                                    $esCodigo = $col['clave'] === 'codigoCatalogo';
                                    $titulo = $texto.($editable ? ' · doble clic para editar' : '');
                                    $clasesCelda = trim(
                                        ($editable ? 'cursor-cell ' : '').
                                        (in_array($col['clave'], $clavesNumericas, true) ? 'text-right' : '')
                                    );
                                @endphp
                                <td class="border-b border-r border-border px-2 py-1 align-top text-text-primary
                                           {{ $fondo }} group-hover:bg-science-blue/10 {{ $clasesCelda }}"
                                    @if($editable)
                                        x-on:dblclick="abrirCelda($event, '{{ $especimen['id'] }}', '{{ $col['clave'] }}', {{ Js::from($texto) }})"
                                        x-bind:class="editando('{{ $especimen['id'] }}', '{{ $col['clave'] }}') && 'opacity-0'"
                                    @endif
                                    title="{{ $titulo }}">
                                    @if($esCodigo)
                                        {{-- El identificador abre la ficha completa: es el gesto
                                             estándar de "abrir este registro" y no choca ni con la
                                             casilla ni con el doble clic de edición. --}}
                                        <button type="button" wire:click="abrirFicha('{{ $especimen['id'] }}')"
                                                class="block max-w-[18rem] truncate whitespace-nowrap text-left text-science-blue hover:underline">
                                            {{ $texto !== '' ? $texto : '—' }}
                                        </button>
                                    @else
                                        <span class="block max-w-[18rem] truncate whitespace-nowrap">
                                            {!! $renderCelda($especimen, $col['clave']) !!}
                                        </span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="sticky right-0 z-10 whitespace-nowrap border-b border-l border-border px-2 py-1
                                       {{ $fondo }} group-hover:bg-science-blue/10">
                                <div class="flex items-center gap-1">
                                    {{-- Un triángulo por problema detectado, cada uno hacia donde ese
                                         problema se arregla. El title lleva el motivo literal para que
                                         el curador sepa qué pasa sin abrir nada. --}}
                                    @foreach($especimen['problemas'] ?? [] as $problema)
                                        @php $aviso = $problema['etiqueta'].' — '.($especimen['motivoRevision'] ?? ''); @endphp
                                        @if($problema['abreModal'])
                                            <button type="button" class="{{ $btnAviso }}"
                                                    wire:click="abrirEditModal('{{ $especimen['id'] }}')"
                                                    title="{{ $aviso }}"
                                                    aria-label="{{ $problema['etiqueta'] }} de {{ $especimen['codigoCatalogo'] }}">
                                                <svg class="size-4"><use href="#ico-aviso" /></svg>
                                            </button>
                                        @else
                                            <a href="{{ $problema['url'] }}" wire:navigate class="{{ $btnAviso }}"
                                               title="{{ $aviso }}"
                                               aria-label="{{ $problema['etiqueta'] }} de {{ $especimen['codigoCatalogo'] }}">
                                                <svg class="size-4"><use href="#ico-aviso" /></svg>
                                            </a>
                                        @endif
                                    @endforeach
                                    {{-- Editar está disponible en cualquier estado: `actualizar()` solo
                                         toca metadatos curatoriales (localidad, colector, fecha, geo…),
                                         nunca la circulación del ejemplar. Condicionarlo a 'disponible'
                                         escondía la acción sin que ninguna regla de dominio lo pidiera y
                                         dejaba sin salida a los especímenes 'observado' que vuelven de
                                         un préstamo con novedad. --}}
                                    <button type="button" class="{{ $btnFila }}"
                                            wire:click="abrirEditModal('{{ $especimen['id'] }}')"
                                            title="Editar" aria-label="Editar {{ $especimen['codigoCatalogo'] }}">
                                        <svg class="size-4"><use href="#ico-pencil" /></svg>
                                    </button>
                                    <button type="button" class="{{ $btnFila }}"
                                            wire:click="abrirFicha('{{ $especimen['id'] }}')"
                                            title="Ver ficha completa" aria-label="Ficha de {{ $especimen['codigoCatalogo'] }}">
                                        <svg class="size-4"><use href="#ico-ficha" /></svg>
                                    </button>
                                    <button type="button" class="{{ $btnFila }}"
                                            wire:click="mostrarQr('{{ $especimen['id'] }}')"
                                            wire:loading.attr="disabled"
                                            title="Código QR" aria-label="Código QR de {{ $especimen['codigoCatalogo'] }}">
                                        <svg class="size-4"><use href="#ico-qr" /></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columnasTabla) + 3 }}" class="px-4 py-10 text-center font-sans text-sm text-text-secondary">
                                @if(count($filtrosActivos) > 0)
                                    Ningún espécimen coincide con estos filtros.
                                    <button type="button" wire:click="limpiar" class="ml-1 underline underline-offset-2 hover:text-science-blue">
                                        Limpiar filtros
                                    </button>
                                @else
                                    Todavía no hay especímenes en el catálogo.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Móvil: tarjetas apiladas con el mismo juego de columnas. --}}
        <div class="divide-y divide-border md:hidden">
            @forelse($filas as $i => $especimen)
                @php $marcado = ($especimen['estadoRevision'] ?? '') === 'pendiente' && ! empty($especimen['motivoRevision']); @endphp
                <div wire:key="movil-{{ $especimen['id'] }}"
                     class="flex flex-col gap-2 p-4 @if($marcado) border-l-4 border-l-error @endif">
                    <div class="flex items-start justify-between gap-2">
                        {{-- Toda la cabecera alterna la casilla: área táctil holgada. --}}
                        <label class="flex min-w-0 cursor-pointer items-start gap-3 py-1">
                            <input type="checkbox" class="mt-0.5 size-5 rounded border-border"
                                   :checked="marcado('{{ $especimen['id'] }}')"
                                   @change="alternar('{{ $especimen['id'] }}', $event.target.checked)"
                                   aria-label="Seleccionar {{ $especimen['codigoCatalogo'] }}" />
                            <span class="min-w-0">
                                <span class="text-xs text-text-secondary">#{{ $offsetFila + $i + 1 }}</span>
                                <span class="block break-all font-mono text-sm font-medium text-text-primary">
                                    {{ $especimen['codigoCatalogo'] }}
                                </span>
                            </span>
                        </label>
                        {!! $renderCelda($especimen, 'estadoRevision') !!}
                    </div>

                    @foreach($columnasTabla as $col)
                        @continue($col['clave'] === 'codigoCatalogo' || $col['clave'] === 'estadoRevision')
                        <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil :etiqueta="$col['etiqueta']">
                            {!! $renderCelda($especimen, $col['clave']) !!}
                        </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                    @endforeach

                    <div class="flex flex-wrap gap-2 pt-2">
                        @foreach($especimen['problemas'] ?? [] as $problema)
                            @if($problema['abreModal'])
                                <flux:button variant="filled" icon="exclamation-triangle"
                                             wire:click="abrirEditModal('{{ $especimen['id'] }}')">
                                    {{ $problema['etiqueta'] }}
                                </flux:button>
                            @else
                                <flux:button variant="filled" icon="exclamation-triangle"
                                             href="{{ $problema['url'] }}" wire:navigate>
                                    {{ $problema['etiqueta'] }}
                                </flux:button>
                            @endif
                        @endforeach
                        <flux:button variant="ghost" icon="eye" wire:click="abrirFicha('{{ $especimen['id'] }}')">
                            Ver ficha
                        </flux:button>
                        <flux:button variant="ghost" icon="pencil" wire:click="abrirEditModal('{{ $especimen['id'] }}')">
                            Editar
                        </flux:button>
                        <flux:button variant="ghost" icon="qr-code"
                                     wire:click="mostrarQr('{{ $especimen['id'] }}')"
                                     wire:loading.attr="disabled"
                                     wire:target="mostrarQr('{{ $especimen['id'] }}')">
                            Código QR
                        </flux:button>
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-sm text-text-secondary">
                    @if(count($filtrosActivos) > 0)
                        Ningún espécimen coincide con estos filtros.
                    @else
                        Todavía no hay especímenes en el catálogo.
                    @endif
                </div>
            @endforelse
        </div>

        <x-inventariogestioncoleccion::paginacion-tabla
            :pagina="$page" :total-paginas="$totalPaginas" :total-items="$totalItems"
            :inicio="$inicio" :fin="$fin" />
    </div>

    {{-- ── Modal: Ficha completa ───────────────────────────────────────────── --}}
    <flux:modal wire:model="fichaAbierta" class="w-full max-w-5xl">
        @if($fichaAbierta)
            @php
                // Se recorre el registro de columnas (no las claves del dato) para
                // que el orden y la agrupación sean los mismos de toda la pantalla
                // y ninguna columna del catálogo quede sin mostrar.
                $porGrupo = [];
                foreach ($columnasRegistro as $col) {
                    $porGrupo[$col['grupo']][] = $col;
                }
                $titulosGrupo = [
                    'identificacion' => 'Identificación',
                    'taxonomia' => 'Taxonomía',
                    'localidad' => 'Localidad',
                    'fecha' => 'Fecha',
                    'registro' => 'Registro',
                    'atributos' => 'Atributos',
                    'revision' => 'Revisión',
                ];
            @endphp
            <div class="space-y-4 p-1">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <flux:heading size="lg" class="text-text-primary break-all">
                            {{ $fichaDatos['codigoCatalogo'] ?? 'Ficha del espécimen' }}
                        </flux:heading>
                        <p class="mt-1 text-sm text-text-secondary">
                            Todas las columnas del catálogo. Los campos con fondo editable se pueden
                            corregir aquí; cada cambio queda registrado y se puede deshacer.
                        </p>
                    </div>
                    {!! $renderCelda($fichaDatos, 'estadoRevision') !!}
                </div>

                @if($errorMessage)<flux:callout variant="danger">{{ $errorMessage }}</flux:callout>@endif

                @if(! empty($fichaDatos['motivoRevision']))
                    <flux:callout variant="warning">{{ $fichaDatos['motivoRevision'] }}</flux:callout>
                @endif

                <div class="max-h-[60vh] space-y-5 overflow-y-auto pr-1">
                    @foreach($porGrupo as $grupo => $columnas)
                        <div>
                            <h3 class="mb-2 border-b border-border pb-1 text-sm font-semibold text-blue-navy">
                                {{ $titulosGrupo[$grupo] ?? ucfirst($grupo) }}
                            </h3>
                            <div class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($columnas as $col)
                                    @php
                                        $clave = $col['clave'];
                                        $editable = array_key_exists($clave, $fichaBorrador);
                                        $texto = $textoCelda($fichaDatos, $clave);
                                    @endphp
                                    <div class="min-w-0">
                                        <label class="flex items-center gap-1.5 text-xs text-text-secondary"
                                               @if($editable) for="ficha-{{ $clave }}" @endif>
                                            <span class="inline-block size-2 shrink-0 rounded-full
                                                @if($col['prioridad']==='critica') bg-error
                                                @elseif($col['prioridad']==='recomendada') bg-warning
                                                @else bg-text-secondary @endif"></span>
                                            {{ $col['etiqueta'] }}
                                        </label>
                                        @if($editable)
                                            <input id="ficha-{{ $clave }}" type="text"
                                                   wire:model="fichaBorrador.{{ $clave }}"
                                                   class="mt-1 w-full rounded border border-border bg-bg-main px-2 py-1 font-mono text-xs text-text-primary focus:border-science-blue focus:outline-none focus:ring-1 focus:ring-science-blue" />
                                        @else
                                            <p class="mt-1 break-words font-mono text-xs text-text-primary">
                                                {{ $texto !== '' ? $texto : '—' }}
                                            </p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-col justify-end gap-3 border-t border-border pt-3 sm:flex-row">
                    <flux:button variant="ghost" icon="qr-code" wire:click="mostrarQr('{{ $fichaId }}')">
                        Código QR
                    </flux:button>
                    <flux:button variant="ghost" wire:click="cerrarFicha">Cerrar</flux:button>
                    <flux:button variant="primary" icon="check" wire:click="guardarFicha"
                                 wire:loading.attr="disabled" wire:target="guardarFicha">
                        <span wire:loading.remove wire:target="guardarFicha">Guardar cambios</span>
                        <span wire:loading wire:target="guardarFicha">Guardando…</span>
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- ── Modal: Edición masiva ───────────────────────────────────────────── --}}
    <flux:modal wire:model="panelMasivoAbierto" class="w-full max-w-3xl">
        @if($panelMasivoAbierto)
            @php
                $campos = $this->camposEditables();
                $nSeleccion = $this->contarSeleccionados();
                $campoActual = $campos[$masivaCampo] ?? null;
                $etiquetaCampo = $campoActual['etiqueta'] ?? $masivaCampo;
            @endphp
            <div class="space-y-4 p-1">
                <div>
                    <flux:heading size="lg" class="text-text-primary">Editar en bloque</flux:heading>
                    <p class="mt-1 text-sm text-text-secondary">
                        Se aplicará a los <span class="font-semibold text-text-primary">{{ number_format($nSeleccion) }}</span>
                        espécimen(es) que marcaste. La selección es manual: nunca alcanza filas que no estés viendo.
                    </p>
                </div>

                @if($errorMessage)<flux:callout variant="danger">{{ $errorMessage }}</flux:callout>@endif

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Qué hacer</flux:label>
                        <flux:select wire:model.live="masivaModo">
                            <option value="fijar">Fijar un valor</option>
                            <option value="reemplazar">Buscar y reemplazar</option>
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label>Columna</flux:label>
                        <flux:select wire:model.live="masivaCampo">
                            @foreach($campos as $clave => $campo)
                                @if($masivaModo !== 'reemplazar' || $campo['tipo'] !== 'booleano')
                                    <option value="{{ $clave }}">{{ $campo['etiqueta'] }}</option>
                                @endif
                            @endforeach
                        </flux:select>
                    </flux:field>
                </div>

                @if($masivaModo === 'reemplazar')
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>Buscar</flux:label>
                            <flux:input wire:model.live.debounce.500ms="masivaBuscar" placeholder="Ej. Yasuni" />
                        </flux:field>
                        <flux:field>
                            <flux:label>Reemplazar por</flux:label>
                            <flux:input wire:model.live.debounce.500ms="masivaReemplazo" placeholder="Ej. Yasuní" />
                        </flux:field>
                    </div>
                    <div class="flex flex-col gap-2 rounded-lg border border-border bg-bg-main p-3">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-text-primary">
                            <input type="checkbox" wire:model.live="masivaDistinguirMayusculas" class="size-4 rounded border-border" />
                            Distinguir mayúsculas y minúsculas
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-text-primary">
                            <input type="checkbox" wire:model.live="masivaPalabraCompleta" class="size-4 rounded border-border" />
                            Solo palabras completas
                            <span class="text-xs text-text-secondary">(evita que «sp» toque «Aspidosperma»)</span>
                        </label>
                        <p class="text-xs text-text-secondary">
                            El texto se busca tal cual se escribe; no se interpreta como expresión regular.
                        </p>
                    </div>
                @else
                    <flux:field>
                        <flux:label>Valor para «{{ $etiquetaCampo }}»</flux:label>
                        @if(($campoActual['tipo'] ?? '') === 'booleano')
                            <flux:select wire:model.live="masivaValor" :disabled="$masivaVaciar">
                                <option value="">— Elige —</option>
                                <option value="si">Sí</option>
                                <option value="no">No</option>
                            </flux:select>
                        @elseif(($campoActual['tipo'] ?? '') === 'texto_largo')
                            <flux:textarea wire:model.live.debounce.500ms="masivaValor" rows="3" :disabled="$masivaVaciar" />
                        @else
                            <flux:input wire:model.live.debounce.500ms="masivaValor" :disabled="$masivaVaciar" />
                        @endif
                    </flux:field>

                    @if($campoActual['admiteVacio'] ?? false)
                        {{-- Explícito a propósito: un campo de texto en blanco no
                             puede significar a la vez "bórralo" y "déjalo como está". --}}
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-warning bg-warning/10 p-3 text-sm text-text-primary">
                            <input type="checkbox" wire:model.live="masivaVaciar" class="size-4 rounded border-border" />
                            Vaciar el campo (borra su contenido en las filas seleccionadas)
                        </label>
                    @endif
                @endif

                {{-- Vista previa obligatoria antes de escribir --}}
                @if($masivaPreviewListo)
                    <div class="space-y-2 rounded-lg border border-border bg-bg-main p-3">
                        <p class="text-sm font-medium text-text-primary">{{ $masivaResumen }}</p>
                        @if(count($masivaPreview) > 0)
                            <div class="max-h-56 overflow-auto rounded border border-border bg-surface">
                                <table class="w-full border-separate border-spacing-0 font-mono text-xs">
                                    <thead>
                                        <tr>
                                            <th class="sticky top-0 border-b border-border bg-bg-main px-2 py-1 text-left font-sans">Espécimen</th>
                                            <th class="sticky top-0 border-b border-border bg-bg-main px-2 py-1 text-left font-sans">Antes</th>
                                            <th class="sticky top-0 border-b border-border bg-bg-main px-2 py-1 text-left font-sans">Después</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($masivaPreview as $fila)
                                            <tr>
                                                <td class="border-b border-border px-2 py-1">{{ $fila['codigoCatalogo'] }}</td>
                                                <td class="border-b border-border px-2 py-1 text-text-secondary line-through">
                                                    {{ $fila['previo'] ?? '—' }}
                                                </td>
                                                <td class="border-b border-border px-2 py-1 text-bio-green">
                                                    {{ $fila['nuevo'] ?? '(vacío)' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-xs text-text-secondary">Se muestran las primeras {{ count($masivaPreview) }} filas.</p>
                        @endif
                    </div>
                @endif

                <div class="flex flex-col justify-end gap-3 pt-2 sm:flex-row">
                    <flux:button variant="ghost" wire:click="$set('panelMasivoAbierto', false)">Cancelar</flux:button>
                    <flux:button variant="filled" icon="eye" wire:click="previsualizarMasiva"
                                 wire:loading.attr="disabled" wire:target="previsualizarMasiva">
                        <span wire:loading.remove wire:target="previsualizarMasiva">Previsualizar</span>
                        <span wire:loading wire:target="previsualizarMasiva">Calculando…</span>
                    </flux:button>
                    @if($masivaPreviewListo)
                        @php
                            $confirmacion = $masivaVaciar
                                ? "Vas a BORRAR el contenido de «{$etiquetaCampo}» en los especímenes seleccionados. Quedará registrado y podrás deshacerlo. ¿Continuar?"
                                : "Vas a escribir en «{$etiquetaCampo}» de los especímenes seleccionados. Quedará registrado y podrás deshacerlo. ¿Continuar?";
                        @endphp
                        <flux:button variant="primary" icon="check" wire:click="aplicarMasiva"
                                     wire:confirm="{{ $confirmacion }}"
                                     wire:loading.attr="disabled" wire:target="aplicarMasiva">
                            <span wire:loading.remove wire:target="aplicarMasiva">Aplicar</span>
                            <span wire:loading wire:target="aplicarMasiva">Aplicando…</span>
                        </flux:button>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- ── Modal: Historial de ediciones ───────────────────────────────────── --}}
    <flux:modal wire:model="historialAbierto" class="w-full max-w-3xl">
        @if($historialAbierto)
            <div class="space-y-4 p-1">
                <div>
                    <flux:heading size="lg" class="text-text-primary">Historial de ediciones</flux:heading>
                    <p class="mt-1 text-sm text-text-secondary">
                        Cada edición se puede deshacer una vez. Las filas que hayan cambiado después
                        no se tocan: revertirlas borraría un cambio más reciente.
                    </p>
                </div>

                @if(count($historial) === 0)
                    <p class="py-6 text-center text-sm text-text-secondary">Todavía no se ha registrado ninguna edición.</p>
                @else
                    <div class="divide-y divide-border rounded-lg border border-border">
                        @foreach($historial as $edicion)
                            <div wire:key="hist-{{ $edicion['id'] }}" class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm text-text-primary">{{ $edicion['resumen'] }}</p>
                                    <p class="text-xs text-text-secondary">
                                        {{ $edicion['fecha'] }}
                                        · {{ $edicion['totalAfectados'] }} espécimen(es)
                                        @if($edicion['actor']) · {{ $edicion['actor'] }} @endif
                                    </p>
                                </div>
                                @if($edicion['deshecha'])
                                    <span class="shrink-0 rounded-full border border-border px-2 py-0.5 text-xs text-text-secondary">
                                        Deshecha
                                    </span>
                                @else
                                    <flux:button size="sm" variant="ghost" icon="arrow-uturn-left"
                                                 wire:click="deshacer('{{ $edicion['id'] }}')"
                                                 wire:confirm="Se devolverán los especímenes al valor que tenían antes de esta edición. Los que hayan cambiado después no se tocarán. ¿Continuar?">
                                        Deshacer
                                    </flux:button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex justify-end pt-2">
                    <flux:button variant="ghost" wire:click="$set('historialAbierto', false)">Cerrar</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- ── Modal: Registrar ────────────────────────────────────────────────── --}}
    {{-- El cuerpo solo existe con el modal abierto: el desplegable de taxones
         ronda las 4.000 opciones y montarlo siempre pesaba ~1 MB por render. --}}
    <flux:modal wire:model="showModal" class="w-full max-w-4xl">
        @if($showModal)
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
            <div class="space-y-2 rounded-lg border border-border bg-bg-main p-3">
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
        @endif
    </flux:modal>

    {{-- ── Modal: Editar ───────────────────────────────────────────────────── --}}
    <flux:modal wire:model="showEditModal" class="w-full max-w-4xl">
        @if($showEditModal)
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
            <div class="space-y-2 rounded-lg border border-border bg-bg-main p-3">
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
        @endif
    </flux:modal>
</div>

@script
<script>
    // La configuración de columnas vive en el servidor (para render la tabla ya
    // ordenada) pero se recuerda por navegador. Al montar, si este navegador
    // tiene una preferencia distinta a la que el servidor acaba de renderizar,
    // se la envía una vez; a partir de ahí la sesión ya coincide y no hay viaje.
    const CLAVE = 'inventario.especimenes.columnas.v2';
    const servidor = { orden: @json($ordenColumnas), visibles: @json($columnasVisibles) };

    let local = null;
    try { local = JSON.parse(localStorage.getItem(CLAVE)); } catch (e) { local = null; }

    if (local && Array.isArray(local.orden) && Array.isArray(local.visibles)) {
        if (JSON.stringify(local) !== JSON.stringify(servidor)) {
            $wire.aplicarColumnas(local.orden, local.visibles);
        }
    } else {
        localStorage.setItem(CLAVE, JSON.stringify(servidor));
    }

    $wire.on('columnas-actualizadas', (evento) => {
        const datos = Array.isArray(evento) ? evento[0] : evento;
        localStorage.setItem(CLAVE, JSON.stringify({ orden: datos.orden, visibles: datos.visibles }));
    });
</script>
@endscript
