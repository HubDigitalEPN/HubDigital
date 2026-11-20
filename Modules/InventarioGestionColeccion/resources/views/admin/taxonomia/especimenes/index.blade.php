@php
    /**
     * Valor plano de una celda, para el atributo `title` y para las tarjetas
     * móviles. Devuelve '' cuando no hay dato.
     */
    $textoCelda = function (array $e, string $clave): string {
        $v = $e[$clave] ?? null;

        if ($clave === 'taxonNombre' && $v === null) {
            $original = trim($e['taxonVerbatim'] ?? '');
            return in_array($original, ['', '_', '-', '?'], true) ? '' : $original.' (verbatim)';
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
            $original = trim($e['taxonVerbatim'] ?? '');
            return $v !== null
                ? '<span class="font-serif italic">'.e($v).'</span>'
                : (! in_array($original, ['', '_', '-', '?'], true)
                    ? '<span class="text-text-secondary italic">'.e($original).'</span>'
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

    $clavesNumericas = ['decimalLatitude', 'decimalLongitude', 'elevationMinM', 'elevationMaxM',
                        'individualCount', 'filaOrigenExcel', 'latLonMaxError'];
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
@php
    $puedeBorrar = \App\Support\BotonesSistema::visible('inventario.eliminar');
@endphp
<div class="space-y-3 px-4 py-2 sm:px-6" x-on:especimenes-eliminados.window="limpiar(); cerrarCelda()"
     x-data="{
         sel: {},
         get n() { return Object.keys(this.sel).length },
         ids() { return Object.keys(this.sel) },
         marcado(id) { return this.sel[id] === true },
         alternar(id, valor) { if (valor) { this.sel[id] = true } else { delete this.sel[id] } },
         marcarFilas(lista) { lista.forEach(id => { this.sel[id] = true }) },
         limpiar() { this.sel = {} },
         altaLimpia: true,
         abrirAlta() {
             const dialogo = this.$refs.altaEspecimen.querySelector('dialog');
             const fecha = new Intl.DateTimeFormat('en-CA', {timeZone: 'America/Guayaquil', year: 'numeric', month: '2-digit', day: '2-digit'}).format(new Date());
             for (const campo of dialogo.querySelectorAll('[data-catalogo-campo]')) {
                 const modelo = campo.dataset.catalogoCampo;
                 $wire.$set(modelo, modelo === 'fechaColecta' ? fecha : '', false);
             }
             $wire.$set('ubicacionDetectada', null, false);
             $wire.$set('geoMensaje', null, false);
             $wire.$set('showModal', true, false);
             this.altaLimpia = true;
             dialogo.querySelectorAll('[aria-invalid=true]').forEach(control => control.removeAttribute('aria-invalid'));
             dialogo.showModal();
         },
         menuFila: {},
         abrirMenuFila(invocador, id) {
             const menu = this.$refs.accionesFila;
             if (this.menuFila.invocador === invocador) { this.cerrarMenuFila(); return; }
             this.menuFila = {invocador, id};
             if (!menu.matches(':popover-open')) menu.showPopover();
             const caja = invocador.getBoundingClientRect();
             menu.style.left = Math.max(8, Math.min(caja.left, innerWidth - menu.offsetWidth - 8)) + 'px';
             menu.style.top = Math.max(8, Math.min(caja.bottom + 4, innerHeight - menu.offsetHeight - 8)) + 'px';
             menu.querySelector('button').focus({preventScroll: true});
         },
         cerrarMenuFila(enfocar = true) {
             const menu = this.$refs.accionesFila;
             if (!menu?.matches(':popover-open')) return;
             const invocador = this.menuFila.invocador;
             menu.hidePopover();
             this.menuFila = {};
             if (enfocar) invocador?.focus({preventScroll: true});
         },
         teclaMenuFila(evento) {
             if (evento.key === 'Escape') { evento.preventDefault(); this.cerrarMenuFila(); return; }
             if (evento.key === 'Tab') { this.cerrarMenuFila(); return; }
             const botones = Array.from(this.$refs.accionesFila.querySelectorAll('button'));
             const i = botones.indexOf(document.activeElement);
             const destino = {ArrowDown: (i + 1) % botones.length, ArrowUp: (i + botones.length - 1) % botones.length, Home: 0, End: botones.length - 1}[evento.key];
             if (destino !== undefined) { evento.preventDefault(); botones[destino].focus(); }
         },
         ejecutarAccionFila(accion) {
             const id = this.menuFila.id;
             this.cerrarMenuFila();
             if (id) $wire[accion](accion === 'abrirBorrado' ? [id] : id);
         },

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
     @seleccion-aplicada.window="limpiar()" x-on:ver-ultimo-especimen="$wire.abrirFicha($event.detail.id)">
    {{-- ── Encabezado ──────────────────────────────────────────────────────── --}}
    <flux:heading size="xl" level="1" class="font-display text-blue-navy font-bold">Especímenes</flux:heading>

    @island(name: 'alta-especimen', always: true)
    @if($successMessage)
        <flux:callout data-hub-feedback="success" variant="success">
            {{ $successMessage }}
            @if($ultimoCreadoId)<flux:button size="sm" variant="ghost" x-on:click="$dispatch('ver-ultimo-especimen', {id: $wire.ultimoCreadoId})">Ver última ficha creada</flux:button>@endif
        </flux:callout>
    @endif
    @endisland
    @if($errorMessage && !$showModal && !$showEditModal)<flux:callout data-hub-feedback="danger" variant="danger">{{ $errorMessage }}</flux:callout>@endif

    <flux:modal wire:model="busquedaAbierta" aria-labelledby="inventario-busqueda-titulo" class="w-full max-w-lg">
        <form wire:submit="buscarRapido" class="space-y-4">
            <h2 id="inventario-busqueda-titulo" class="hub-section-title">Buscar especímenes</h2>
            <flux:input type="search" wire:model="consultaRapida" label="Código, occurrenceID, taxón, colector o localidad" autofocus />
            <flux:error name="consultaRapida" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button>Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Buscar</flux:button>
            </div>
        </form>
    </flux:modal>

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
        <flux:modal wire:model="filtrosAbiertos" aria-labelledby="inventario-filtros-titulo" class="w-full max-w-4xl">
            @if($filtrosAbiertos)
            <div class="space-y-4">
                <h2 id="inventario-filtros-titulo" class="hub-section-title">Filtros de especímenes</h2>
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




                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <flux:button variant="primary" icon="magnifying-glass" wire:click="buscar"
                                 wire:loading.attr="disabled" class="w-full sm:w-auto">
                        <span wire:loading.remove wire:target="buscar">Aplicar filtros</span>
                        <span wire:loading wire:target="buscar">Buscando…</span>
                    </flux:button>
                </div>
            </div>
            @endif
        </flux:modal>

        @if(auth()->user()?->esAdministrador())
        <flux:modal wire:model="gestionarColumnas" aria-labelledby="inventario-gestionar-columnas-titulo" class="w-full max-w-4xl">
            @if($gestionarColumnas)
            <section class="space-y-4">
            <h2 id="inventario-gestionar-columnas-titulo" class="hub-section-title">Columnas del catálogo</h2>
            <form wire:submit="crearColumna" class="grid gap-3 sm:grid-cols-3"><flux:input wire:model="nuevaColumna" label="Título de la columna"/><flux:input wire:model="descripcionColumna" label="Instrucción de la guía"/><flux:button type="submit" variant="primary">Crear columna</flux:button></form>

            <div class="max-h-64 overflow-auto"><ul>@foreach($definicionColumnas as $c)<li class="flex items-center justify-between gap-3 border-b p-2"><span data-copy-preserve>{{ $c['cabecera'] }}</span><flux:button wire:click="eliminarColumna('{{ $c['campo'] }}')" wire:confirm="Se eliminará la columna únicamente si nunca se ha usado. ¿Continuar?">Eliminar si está sin uso</flux:button></li>@endforeach</ul></div>
            </section>
            @endif
        </flux:modal>
        @endif
        {{-- Panel de configuración de columnas --}}
        <flux:modal wire:model="panelColumnasAbierto" aria-labelledby="inventario-columnas-titulo" class="w-full max-w-4xl">
            @if($panelColumnasAbierto)
            <div class="space-y-3">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <flux:heading id="inventario-columnas-titulo" size="md" level="2" class="text-text-primary">Configurar columnas</flux:heading>

                    </div>
                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" variant="ghost" wire:click="restaurarColumnas">Por defecto</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="mostrarTodasColumnas">Mostrar todas</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="ocultarTodasColumnas">Ocultar todas</flux:button>
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
        </flux:modal>

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
                    if (! w) { window.hubNotify({message: 'Habilita las ventanas emergentes para imprimir la etiqueta.', variant: 'warning'}); return; }
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

        <div class="relative" x-ref="hoja" x-on:scroll.capture="cerrarCelda(); cerrarMenuFila(false)">
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

            <x-grid-frame label="Especímenes" page-size-label="Filas por carga"
                          :page-size="$perPage" page-size-action="cambiarFilasPorCarga" :page-sizes="$tamanosPagina"
                          :allow-download="false" column-action="$set('panelColumnasAbierto', true)" :continuous-window="$continuidad">
                <x-slot:actions>
                    <button type="button" role="menuitem" tabindex="-1" @click="marcarFilas(@js(array_column($filas, 'id'))); closeMenu()" @disabled($filas === [])>
                        <flux:icon name="check" /><span>Marcar filas cargadas ({{ count($filas) }})</span>
                    </button>
                    <button type="button" role="menuitem" tabindex="-1" @click="limpiar(); closeMenu()" :disabled="n === 0">
                        <flux:icon name="x-mark" /><span>Quitar selección</span>
                    </button>
                    <button type="button" role="menuitem" tabindex="-1" @click="closeMenu(); $wire.abrirPanelMasivo(ids())" :disabled="n === 0">
                        <flux:icon name="pencil-square" /><span>Editar en bloque</span><span class="hub-ig-menu-hint" x-text="n || ''"></span>
                    </button>
                    @if($puedeBorrar)
                        <button type="button" role="menuitem" tabindex="-1" @click="closeMenu(); $wire.abrirBorrado(ids())" :disabled="n === 0">
                            <flux:icon name="trash" /><span>Borrar selección</span>
                        </button>
                    @endif
                    <div class="hub-ig-menu-separator" role="separator"></div>
                    <button type="button" role="menuitem" tabindex="-1" wire:click="abrirBusqueda" x-on:click="closeMenu()">
                        <flux:icon name="magnifying-glass" /><span>Buscar</span><span class="hub-ig-menu-trailing">…</span>
                    </button>
                    <button type="button" role="menuitem" tabindex="-1" wire:click="$set('filtrosAbiertos', true)" x-on:click="closeMenu()">
                        <flux:icon name="funnel" /><span>Filtros</span>
                        @if(count($filtrosActivos) > 0)<span class="hub-ig-menu-hint">{{ count($filtrosActivos) }} activos</span>@endif
                    </button>
                    @if(auth()->user()?->esAdministrador())
                        <button type="button" role="menuitem" tabindex="-1" wire:click="$set('gestionarColumnas', true)" x-on:click="closeMenu()">
                            <flux:icon name="cog-6-tooth" /><span>Crear o eliminar columnas</span>
                        </button>
                    @endif
                    <x-catalogo-excel-menu accion="exportarExcel" :en-grilla="true" />
                    <div class="hub-ig-menu-separator" role="separator"></div>
                    <button type="button" role="menuitem" tabindex="-1" x-on:click="closeMenu(); abrirAlta()">
                        <flux:icon name="plus" /><span>Nuevo espécimen</span>
                    </button>
                    <a href="{{ route('divulgacion.index') }}" wire:navigate role="menuitem" tabindex="-1">
                        <flux:icon name="eye" /><span>Administrar publicación</span>
                    </a>
                </x-slot:actions>
            <div class="hidden md:block">
            <table class="w-full border-separate border-spacing-0 font-mono text-xs">
                <thead>
                    <tr>
                        <th scope="col"
                            class="sticky left-0 top-0 z-30 w-16 border-b border-r border-blue-navy/40 bg-blue-navy px-2 py-2 font-sans font-medium text-white">
                            <span class="sr-only">Acciones y selección</span>
                        </th>
                        <th scope="col"
                            class="sticky left-16 top-0 z-30 w-12 border-b border-r border-blue-navy/40 bg-blue-navy px-2 py-2 text-right font-sans font-medium text-white">
                            #
                        </th>
                        @foreach($columnasTabla as $col)
                            @php $ordenable = in_array($col['clave'], $clavesOrdenables, true); @endphp
                            <th scope="col" data-copy-preserve
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
                    </tr>
                </thead>
                <tbody>
                    @forelse($filas as $i => $especimen)
                        @php

                            $fondo = $i % 2 === 0 ? 'bg-surface' : 'bg-bg-main';
                        @endphp
                        <tr wire:key="fila-{{ $especimen['id'] }}" class="group">
                            <td class="sticky left-0 z-10 w-16 border-b border-r border-border px-1 py-1 {{ $fondo }} group-hover:bg-science-blue/10">
                                <div class="flex items-center gap-1">
                                    @include('inventariogestioncoleccion::admin.taxonomia.especimenes.boton-acciones-fila')
                                    <input type="checkbox" class="size-4 rounded border-border"
                                           :checked="marcado('{{ $especimen['id'] }}')"
                                           @change="alternar('{{ $especimen['id'] }}', $event.target.checked)"
                                           aria-label="Seleccionar {{ $especimen['codigoCatalogo'] }}" />
                                </div>
                            </td>
                            <td class="sticky left-16 z-10 border-b border-r border-border px-2 py-1 text-right text-text-secondary
                                       {{ $fondo }} group-hover:bg-science-blue/10">
                                {{ $offsetFila + $i + 1 }}
                            </td>
                            @foreach($columnasTabla as $col)
                                @php
                                    $texto = $textoCelda($especimen, $col['clave']);
                                    $editable = in_array($col['clave'], $clavesEditables, true);
                                    $esCodigo = $col['clave'] === 'codigoCatalogo';
                                    $titulo = $texto.($editable ? ' · clic o Enter para editar' : '');
                                    $clasesCelda = trim(
                                        ($editable ? 'cursor-cell ' : '').
                                        (in_array($col['clave'], $clavesNumericas, true) ? 'text-right' : '')
                                    );
                                @endphp
                                <td class="border-b border-r border-border px-2 py-1 align-top text-text-primary
                                           {{ $fondo }} group-hover:bg-science-blue/10 {{ $clasesCelda }}"
                                    @if($editable)
                                        tabindex="0" aria-label="Editar {{ $col['etiqueta'] }}"
                                        x-on:click="if (!$event.target.closest('button,a,input')) abrirCelda($event, '{{ $especimen['id'] }}', '{{ $col['clave'] }}', {{ Js::from($texto) }})"
                                        x-on:keydown.enter.prevent="abrirCelda($event, '{{ $especimen['id'] }}', '{{ $col['clave'] }}', {{ Js::from($texto) }})"
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
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columnasTabla) + 2 }}" class="px-4 py-10 text-center font-sans text-sm text-text-secondary">
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
        <div class="divide-y divide-border md:hidden" data-grid-mobile>
            @forelse($filas as $i => $especimen)
                <div wire:key="movil-{{ $especimen['id'] }}" data-grid-scroll-row
                     class="flex flex-col gap-2 p-4">
                    <div class="flex items-start gap-2">
                        @include('inventariogestioncoleccion::admin.taxonomia.especimenes.boton-acciones-fila')
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
                    </div>

                    @foreach($columnasTabla as $col)
                        @continue($col['clave'] === 'codigoCatalogo' || $col['clave'] === 'estadoRevision')
                        <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil :etiqueta="$col['etiqueta']">
                            {!! $renderCelda($especimen, $col['clave']) !!}
                        </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                    @endforeach

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

            <div popover="auto" class="hub-ig-menu" x-ref="accionesFila" role="menu" aria-label="Acciones del espécimen"
                 data-especimen-acciones-fila @toggle="if ($event.newState === 'closed') menuFila = {}"
                 @keydown="teclaMenuFila($event)">
                <button type="button" role="menuitem" @click="ejecutarAccionFila('abrirEditModal')"><flux:icon name="pencil" /> Editar</button>
                <button type="button" role="menuitem" @click="ejecutarAccionFila('abrirFicha')"><flux:icon name="eye" /> Ver ficha</button>
                @if($puedeBorrar)
                    <button type="button" role="menuitem" @click="ejecutarAccionFila('abrirBorrado')"><flux:icon name="trash" /> Borrar definitivamente</button>
                @endif
                <button type="button" role="menuitem" @click="ejecutarAccionFila('mostrarQr')"><flux:icon name="qr-code" /> Código QR</button>
            </div>
            </x-grid-frame>
        </div>
    </div>

    {{-- ── Modal: Ficha completa ───────────────────────────────────────────── --}}
    <flux:modal wire:model="borradoAbierto" aria-labelledby="borrado-especimen-titulo" class="w-full max-w-lg">
        <h2 id="borrado-especimen-titulo" class="hub-section-title">Borrar definitivamente {{ count($borradoIds) }} espécimen(es)</h2>
        <p class="mt-3 text-sm">Esta acción es irreversible. Los especímenes y sus vínculos de catálogo se eliminarán. Los expedientes, documentos firmados y su historial se conservarán.</p>
        <ul class="my-4 max-h-48 overflow-auto rounded border border-border p-3 text-sm" aria-label="Especímenes que se borrarán">@foreach($borradoCodigos as $codigo)<li class="break-all">{{ $codigo }}</li>@endforeach</ul>
        @if($errorMessage)<p role="alert" class="mb-3 text-error">{{ $errorMessage }}</p>@endif
        <div class="flex flex-wrap justify-end gap-3"><flux:button wire:click="$set('borradoAbierto', false)">Cancelar</flux:button><flux:button variant="danger" wire:click="confirmarBorrado" wire:loading.attr="disabled" wire:target="confirmarBorrado">Eliminar definitivamente</flux:button></div>
    </flux:modal>
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
                    'localidad' => 'Ubicación geográfica',
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

                    </div>
                </div>

                @if($errorMessage)<flux:callout data-hub-feedback="danger" variant="danger">{{ $errorMessage }}</flux:callout>@endif


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
                                        <label data-copy-preserve class="flex items-center gap-1.5 text-xs text-text-secondary"
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

                @if($errorMessage)<flux:callout data-hub-feedback="danger" variant="danger">{{ $errorMessage }}</flux:callout>@endif

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
                                <x-grid-frame><table class="w-full border-separate border-spacing-0 font-mono text-xs">
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
                                </table></x-grid-frame>
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
    @island(name: 'alta-especimen', always: true)
    <flux:modal name="alta-especimen" x-ref="altaEspecimen" x-on:close="$wire.$set('showModal', false, false)"
                aria-labelledby="alta-especimen-titulo" class="w-full max-w-7xl">
        <div class="space-y-4 p-1" data-hub-inline-feedback
             x-bind:class="altaLimpia && '[&_[data-flux-error]]:hidden! [&_[data-catalogo-resumen-errores]]:hidden!'">
            <flux:heading id="alta-especimen-titulo" size="lg" class="text-text-primary">Nuevo espécimen</flux:heading>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <x-catalogo-campo campo="fechaColecta" />
                <x-catalogo-campo campo="occurrenceId" />
                <x-catalogo-campo campo="catalogNumber" />
                <x-catalogo-campo campo="oldCode" />
                <x-catalogo-campo campo="cardexLiquidCollectionCode" />
                <x-catalogo-campo campo="taxonId" :valor="$taxonId" placeholder="Selecciona un taxón" lista />
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <x-catalogo-campo campo="localidad" :valor="$localidad" lista admite-texto />
                <x-catalogo-campo campo="localidad2" :valor="$localidad2" lista admite-texto />
                <x-catalogo-campo campo="localidad3" :valor="$localidad3" lista admite-texto />
                <x-catalogo-campo campo="country" :valor="$country" lista admite-texto />
                <x-catalogo-campo campo="stateProvince" :valor="$stateProvince" lista admite-texto />
                <x-catalogo-campo campo="municipality" :valor="$municipality" lista admite-texto />
                <x-catalogo-campo campo="localityName" :valor="$localityName" lista admite-texto />
                <x-catalogo-campo campo="decimalLatitude" />
                <x-catalogo-campo campo="decimalLongitude" />
                <x-catalogo-campo campo="geodeticDatum" />
                <x-catalogo-campo campo="elevationMinM" />
            </div>
            {{-- Detección de ubicación por coordenadas --}}
            <div class="space-y-2 rounded-lg border border-border bg-bg-main p-3">
                <flux:button type="button" variant="filled" icon="map-pin"
                             wire:click="detectarUbicacion" wire:loading.attr="disabled" wire:target="detectarUbicacion">
                    <span wire:loading.remove wire:target="detectarUbicacion">Detectar por coordenadas</span>
                    <span wire:loading wire:target="detectarUbicacion">Detectando…</span>
                </flux:button>
                <p class="text-xs text-warning" x-cloak x-show="$wire.geoMensaje" x-text="$wire.geoMensaje"></p>
                <p class="text-xs text-text-secondary" x-cloak x-show="$wire.ubicacionDetectada">
                    <flux:icon name="map-pin" class="inline size-3.5 -mt-0.5 text-science-blue" />
                    Ubicación detectada: <span class="text-text-primary" x-text="$wire.ubicacionDetectada"></span>
                </p>
            </div>
            <x-catalogo-campo campo="colector" />
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <x-catalogo-campo campo="individualCount" />
                <x-catalogo-campo campo="preparations" />
                <x-catalogo-campo campo="disposition" />
                <x-catalogo-campo campo="occurrenceStatus" />
                <x-catalogo-campo campo="biome" />
                <x-catalogo-campo campo="habitat" />
            </div>
            <x-catalogo-campo campo="specimenNotes" />
            <x-catalogo-campo campo="entidadDepositanteId" :valor="$entidadDepositanteId" placeholder="Sin entidad depositante" lista />
            <x-catalogo-errores :mensaje="$errorMessage" />
            <div class="flex justify-end gap-3 pt-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button variant="primary" wire:click="registrarEspecimen" x-on:click="altaLimpia = false"
                             wire:loading.attr="disabled" wire:target="registrarEspecimen">
                    <span wire:loading.remove wire:target="registrarEspecimen">Registrar</span>
                    <span wire:loading wire:target="registrarEspecimen">Registrando…</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
    @endisland

    {{-- ── Modal: Editar ───────────────────────────────────────────────────── --}}
    <flux:modal wire:model="showEditModal" aria-labelledby="edicion-especimen-titulo" class="w-full max-w-4xl">
        @if($showEditModal)
        <div class="space-y-4 p-1" data-hub-inline-feedback>
            <flux:heading id="edicion-especimen-titulo" size="lg" class="text-text-primary">Editar espécimen</flux:heading>
            <p class="text-sm text-text-secondary">Conserva los datos de la etiqueta. Una ficha histórica puede mantener vacíos los datos que se desconocen.</p>

            <x-catalogo-campo campo="editLocalidad" :valor="$editLocalidad" lista admite-texto />
            <x-catalogo-campo campo="editLocalidad2" :valor="$editLocalidad2" lista admite-texto />
            <x-catalogo-campo campo="editLocalidad3" :valor="$editLocalidad3" lista admite-texto />
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-catalogo-campo campo="editCountry" :valor="$editCountry" lista admite-texto />
                <x-catalogo-campo campo="editStateProvince" :valor="$editStateProvince" lista admite-texto />
                <x-catalogo-campo campo="editMunicipality" :valor="$editMunicipality" lista admite-texto />
                <x-catalogo-campo campo="editLocalityName" :valor="$editLocalityName" lista admite-texto />
                <x-catalogo-campo campo="editDecimalLatitude" />
                <x-catalogo-campo campo="editDecimalLongitude" />
                <x-catalogo-campo campo="editGeodeticDatum" />
                <x-catalogo-campo campo="editElevationMinM" />
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
            <x-catalogo-campo campo="editFechaColecta" />
            <x-catalogo-campo campo="editColector" />
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-catalogo-campo campo="editPreparations" />
                <x-catalogo-campo campo="editDisposition" />
                <x-catalogo-campo campo="editOccurrenceStatus" />
                <x-catalogo-campo campo="editBiome" />
                <x-catalogo-campo campo="editHabitat" />
            </div>
            <x-catalogo-campo campo="editSpecimenNotes" />
            <x-catalogo-campo campo="editEntidadDepositanteId" :valor="$editEntidadDepositanteId" :opciones="$this->opcionCapturaSeleccionada('editEntidadDepositanteId')" placeholder="Sin entidad depositante" lista />
            <x-catalogo-errores :mensaje="$errorMessage" />
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
    const CLAVE = 'inventario.especimenes.columnas.v3';
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
