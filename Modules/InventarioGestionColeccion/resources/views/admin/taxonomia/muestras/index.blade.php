@php
    /** Renderer in-line de celda por clave de columna. */
    $renderCelda = function (array $m, string $clave): string {
        $v = $m[$clave] ?? null;

        if ($clave === 'codigoMuestra') {
            return $v !== null ? '<span class="font-mono text-text-primary">'.e((string) $v).'</span>' : '<span class="text-text-secondary">—</span>';
        }
        if ($clave === 'conteoEspecimenes') {
            $n = (int) ($v ?? 0);

            return '<span class="inline-flex items-center rounded-full bg-blue-navy/10 text-blue-navy border border-blue-navy/30 px-2 py-0.5 text-xs font-semibold">'.$n.' espécimen'.($n === 1 ? '' : 'es').'</span>';
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
        if (in_array($clave, ['fechaVerbatim', 'localidadVerbatim'], true)) {
            return $v !== null && $v !== ''
                ? '<span class="text-text-secondary text-xs italic">'.e((string) $v).'</span>'
                : '<span class="text-text-secondary">—</span>';
        }
        if ($clave === 'motivoRevision') {
            return $v !== null ? '<span class="text-text-primary text-xs">'.e((string) $v).'</span>' : '<span class="text-text-secondary">—</span>';
        }

        if ($v === null || $v === '') {
            return '<span class="text-text-secondary">—</span>';
        }

        return e((string) $v);
    };
@endphp

<div class="space-y-6 p-4 sm:p-6"
     x-data="muestrasIndex({
         claves: {{ json_encode(array_column($columnasRegistro, 'clave')) }},
         visiblesPorDefecto: {{ json_encode($columnasVisiblesPorDefecto) }},
     })"
     x-init="init()">

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Muestras de colecta</flux:heading>
            <p class="text-sm text-text-secondary mt-1">
                Muestras que el importador agrupó por su código de colecta original del Excel, pendientes de que el curador las revise.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button icon="adjustments-horizontal" variant="ghost" @click="panelColumnasAbierto = !panelColumnasAbierto">
                Columnas (<span x-text="visibles.length"></span>)
            </flux:button>
        </div>
    </div>

    @if($successMessage)<flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>@endif
    @if($errorMessage)<flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>@endif

    <x-inventariogestioncoleccion::bandeja-ayuda titulo="¿Cómo uso esta pantalla?" storage-key="ayuda-muestras">
        <p>
            El importador agrupó especímenes que compartían el mismo <code>oldCode</code> en una misma "muestra
            de colecta" (una salida de campo). Las creó como <strong>pendientes</strong> esperando tu confirmación.
        </p>
        <ol class="list-decimal pl-6 space-y-1 text-xs text-text-secondary">
            <li>Mira cada fila: código de colecta + colector + fecha + localidad. ¿Hacen sentido juntos como una sola muestra real?</li>
            <li>Si sí → <strong>Confirmar</strong>. Las muestras confirmadas desaparecen de esta bandeja.</li>
            <li>Si parece basura o duplicado → <strong>Descartar</strong>. Queda en BD marcada con motivo.</li>
        </ol>
    </x-inventariogestioncoleccion::bandeja-ayuda>

    {{-- Leyenda de prioridad --}}
    <div class="flex flex-wrap items-center gap-4 text-xs text-text-secondary">
        <span class="font-medium">Prioridad de columnas:</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-full bg-error"></span> Crítica</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-full bg-warning"></span> Recomendada</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-full bg-text-secondary"></span> Opcional</span>
    </div>

    {{-- Panel de configuración de columnas --}}
    <div x-show="panelColumnasAbierto" x-cloak x-transition
         class="rounded-lg border border-border bg-surface shadow-sm p-4 space-y-3">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading size="md" level="2" class="text-text-primary">Configurar columnas</flux:heading>
            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" variant="ghost" @click="restaurarPorDefecto()">Restaurar</flux:button>
                <flux:button size="sm" variant="ghost" @click="mostrarTodas()">Mostrar todas</flux:button>
                <flux:button size="sm" variant="ghost" @click="soloCriticas()">Solo críticas</flux:button>
                <flux:button size="sm" icon="x-mark" variant="ghost" @click="panelColumnasAbierto = false">Cerrar</flux:button>
            </div>
        </div>
        <p class="text-xs text-text-secondary">Marca las columnas visibles y usa ↑↓ para reordenarlas. Se guarda por navegador.</p>
        <div class="grid gap-1 max-h-96 overflow-y-auto">
            <template x-for="(clave, idx) in ordenColumnas" :key="clave">
                <div class="flex items-center gap-3 py-1.5 px-2 rounded hover:bg-bg-main">
                    <div class="flex flex-col leading-none">
                        <button type="button" @click="moverArriba(idx)" :disabled="idx === 0"
                                class="text-text-secondary hover:text-text-primary disabled:opacity-30" aria-label="Subir">▲</button>
                        <button type="button" @click="moverAbajo(idx)" :disabled="idx === ordenColumnas.length - 1"
                                class="text-text-secondary hover:text-text-primary disabled:opacity-30" aria-label="Bajar">▼</button>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer flex-1 min-w-0">
                        <input type="checkbox" :checked="visibles.includes(clave)" @change="toggleVisible(clave)" class="size-4 rounded border-border" />
                        <span class="inline-block size-2.5 rounded-full shrink-0" :class="colorPrioridad(clave)" :title="prioridadOf(clave)"></span>
                        <span class="text-sm text-text-primary truncate" x-text="etiquetaOf(clave)"></span>
                        <span class="text-xs text-text-secondary ml-auto shrink-0" x-text="grupoOf(clave)"></span>
                    </label>
                </div>
            </template>
        </div>
    </div>

    {{-- Resumen --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm">
        <div class="px-4 py-3 bg-bg-main border-b border-border flex flex-wrap items-center justify-between gap-2">
            <span class="text-sm font-medium text-text-primary">
                {{ $total }} muestra(s) pendiente(s) de revisión
            </span>
            <span class="text-xs text-text-secondary">
                Mostrando {{ $inicio }}–{{ $fin }}
            </span>
        </div>

        {{-- Escritorio: grid layout --}}
        <div class="hidden md:block overflow-x-auto" role="table" aria-label="Muestras de colecta">
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
                <div role="columnheader" class="px-4 py-3 text-left font-medium whitespace-nowrap" style="order: 99999;">Acciones</div>
            </div>

            @forelse($muestras as $muestra)
                <div role="row"
                     wire:key="muestra-fila-{{ $muestra['id'] }}"
                     class="grid border-t border-border hover:bg-bg-main transition-colors text-sm border-l-4 border-l-warning"
                     :style="`grid-template-columns: repeat(${visibles.length + 1}, minmax(140px, auto));`">
                    @foreach($columnasRegistro as $col)
                        <div role="cell"
                             class="px-4 py-3 text-text-primary align-top"
                             x-show="visibles.includes('{{ $col['clave'] }}')" x-cloak
                             :style="`order: ${ordenColumnas.indexOf('{{ $col['clave'] }}')}`">
                            {!! $renderCelda($muestra, $col['clave']) !!}
                        </div>
                    @endforeach
                    <div role="cell" class="px-4 py-3 whitespace-nowrap" style="order: 99999;">
                        <div class="flex items-center gap-2">
                            <flux:button size="sm" variant="primary" icon="check"
                                         wire:click="confirmar('{{ $muestra['id'] }}')"
                                         wire:loading.attr="disabled"
                                         wire:target="confirmar('{{ $muestra['id'] }}')">
                                Confirmar
                            </flux:button>
                            <flux:button size="sm" variant="ghost" icon="x-mark"
                                         wire:click="descartar('{{ $muestra['id'] }}')"
                                         wire:confirm="¿Descartar esta muestra? Quedará marcada con motivo de descarte para revisión."
                                         wire:loading.attr="disabled"
                                         wire:target="descartar('{{ $muestra['id'] }}')">
                                Descartar
                            </flux:button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center text-text-secondary border-t border-border">
                    No hay muestras pendientes de revisión.
                </div>
            @endforelse
        </div>

        {{-- Móvil --}}
        <div class="md:hidden divide-y divide-border">
            @forelse($muestras as $muestra)
                <div wire:key="muestra-tarjeta-{{ $muestra['id'] }}" class="p-4 flex flex-col gap-2 border-l-4 border-l-warning">
                    <div class="flex items-start justify-between gap-2" style="order: -1;">
                        <div class="font-mono text-sm text-text-primary break-all">
                            {{ $muestra['codigoMuestra'] ?? '—' }}
                        </div>
                        {!! $renderCelda($muestra, 'estadoRevision') !!}
                    </div>
                    @foreach($columnasRegistro as $col)
                        @if(in_array($col['clave'], ['codigoMuestra', 'estadoRevision'], true)) @continue @endif
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
                            <dd class="text-right text-text-primary text-sm">{!! $renderCelda($muestra, $col['clave']) !!}</dd>
                        </div>
                    @endforeach
                    <div class="flex flex-wrap gap-2 pt-2" style="order: 99999;">
                        <flux:button variant="primary" icon="check"
                                     wire:click="confirmar('{{ $muestra['id'] }}')"
                                     wire:loading.attr="disabled"
                                     wire:target="confirmar('{{ $muestra['id'] }}')">
                            Confirmar
                        </flux:button>
                        <flux:button variant="ghost" icon="x-mark"
                                     wire:click="descartar('{{ $muestra['id'] }}')"
                                     wire:confirm="¿Descartar esta muestra? Quedará marcada con motivo de descarte para revisión."
                                     wire:loading.attr="disabled"
                                     wire:target="descartar('{{ $muestra['id'] }}')">
                            Descartar
                        </flux:button>
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-text-secondary text-sm">No hay muestras pendientes de revisión.</div>
            @endforelse
        </div>

        {{-- Paginación --}}
        @if($total > 0)
            <div class="flex items-center justify-between px-4 py-3 border-t border-border bg-bg-main">
                <span class="text-xs text-text-secondary">Página {{ $pagina }} de {{ $totalPaginas }}</span>
                <div class="flex gap-2">
                    <flux:button size="sm" variant="ghost" icon="chevron-left"
                                 :disabled="$pagina <= 1"
                                 wire:click="paginaAnterior">Anterior</flux:button>
                    <flux:button size="sm" variant="ghost" icon="chevron-right"
                                 :disabled="$pagina >= $totalPaginas"
                                 wire:click="siguientePagina">Siguiente</flux:button>
                </div>
            </div>
        @endif
    </div>

    <script>
        function muestrasIndex(cfg) {
            const STORAGE_KEY = 'inventario.muestras.columnas.v1';
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
                mostrarTodas() { this.visibles = [...this.ordenColumnas]; this.persist(); },
                soloCriticas() { this.visibles = this.ordenColumnas.filter(k => byKey[k]?.prioridad === 'critica'); this.persist(); },
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
