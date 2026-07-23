<div class="space-y-6 p-4 sm:p-6 max-w-5xl">
    {{-- Encabezado --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Importar catálogo</flux:heading>
            <p class="text-sm text-text-secondary mt-1 max-w-2xl">
                Sube el archivo <strong>Excel</strong> (.xlsx / .xls) o <strong>CSV</strong> del catálogo de
                invertebrados. El sistema lee cada fila, arma la jerarquía taxonómica y guarda los especímenes.
                Ninguna fila se pierde: las que tengan datos dudosos quedan marcadas para revisión.
            </p>
        </div>
        <a href="{{ route('inventario.taxonomia.importar.plantilla') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-science-blue px-4 py-2 text-sm font-medium text-white hover:opacity-90 transition-opacity shrink-0 whitespace-nowrap">
            <flux:icon name="arrow-down-tray" class="size-4 shrink-0 text-white" />
            <span class="text-white">Descargar plantilla Excel</span>
        </a>
    </div>

    @if($successMessage)
        <flux:callout variant="success" icon="check-circle" dismissible>{{ $successMessage }}</flux:callout>
    @endif
    @if($errorMessage)
        <flux:callout variant="danger" icon="exclamation-triangle" dismissible>{{ $errorMessage }}</flux:callout>
    @endif

    {{-- Guía rápida --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm p-4">
        <div class="text-xs font-semibold text-text-secondary uppercase tracking-wide mb-2">Antes de importar</div>
        <ul class="grid gap-2 text-sm text-text-secondary sm:grid-cols-2">
            <li class="flex items-start gap-2">
                <flux:icon name="table-cells" class="size-4 mt-0.5 shrink-0 text-science-blue" />
                <span>La <strong>primera fila</strong> debe contener los encabezados (ej. <code class="text-xs">occurrenceID</code>, <code class="text-xs">scientificName</code>, <code class="text-xs">recordedBy</code>, <code class="text-xs">eventDate</code>).</span>
            </li>
            <li class="flex items-start gap-2">
                <flux:icon name="beaker" class="size-4 mt-0.5 shrink-0 text-science-blue" />
                <span><strong>Validar</strong> hace una simulación: cuenta y reporta sin escribir nada en la base.</span>
            </li>
            <li class="flex items-start gap-2">
                <flux:icon name="arrow-path" class="size-4 mt-0.5 shrink-0 text-science-blue" />
                <span>La importación es <strong>idempotente</strong>: si repites el archivo, las filas ya cargadas se saltan (no se duplican).</span>
            </li>
            <li class="flex items-start gap-2">
                <flux:icon name="clock" class="size-4 mt-0.5 shrink-0 text-science-blue" />
                <span>Archivos grandes pueden tardar. No cierres la pestaña mientras se procesa.</span>
            </li>
        </ul>
    </div>

    {{-- Zona de carga --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm p-4 sm:p-6 space-y-4">
        <div
            wire:ignore.self
            x-data="{
                progreso: 0,
                subiendo: false,
                errorSubida: false,
                arrastrando: false,
                nombre: @js($archivoNombre),
                extensionesValidas: ['xlsx', 'xls', 'csv', 'txt'],
                esValido(archivo) {
                    const ext = archivo.name.split('.').pop().toLowerCase();
                    return this.extensionesValidas.includes(ext);
                },
                soltar(e) {
                    this.arrastrando = false;
                    const archivo = e.dataTransfer.files[0];
                    if (!archivo) return;
                    if (!this.esValido(archivo)) { this.errorSubida = true; return; }
                    const input = this.$refs.fileInput;
                    const dt = new DataTransfer();
                    dt.items.add(archivo);
                    input.files = dt.files;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    this.nombre = archivo.name;
                }
            }"
            x-on:livewire-upload-start="subiendo = true; progreso = 0; errorSubida = false"
            x-on:livewire-upload-progress="progreso = $event.detail.progress"
            x-on:livewire-upload-finish="subiendo = false; progreso = 100"
            x-on:livewire-upload-error="subiendo = false; progreso = 0; errorSubida = true"
            x-on:import-limpiado.window="nombre = null; progreso = 0; errorSubida = false; $refs.fileInput.value = ''"
            x-on:click="$refs.fileInput.click()"
            x-on:dragover.prevent="arrastrando = true"
            x-on:dragleave.prevent="arrastrando = false"
            x-on:drop.prevent="soltar($event)"
            class="flex flex-col items-center gap-3 rounded-lg border-2 border-dashed p-6 text-center transition-all cursor-pointer group"
            x-bind:class="arrastrando
                ? '!border-science-blue !bg-science-blue/10'
                : (nombre ? 'border-success/40 bg-success/5' : 'border-border bg-bg-main hover:border-science-blue hover:bg-science-blue/5')"
        >
            <div class="size-12 rounded-lg border border-border bg-surface flex items-center justify-center shadow-sm">
                <flux:icon x-show="nombre" x-cloak name="document-check" class="size-6 text-success" />
                <flux:icon x-show="!nombre" name="arrow-up-tray" class="size-6 text-text-secondary group-hover:text-science-blue transition-colors" />
            </div>

            <div class="min-w-0">
                <p x-show="!nombre" class="text-sm font-semibold text-text-primary">Haz clic o arrastra el archivo aquí</p>
                <div x-show="nombre" x-cloak class="flex items-center justify-center gap-1.5">
                    <flux:icon name="document-text" class="size-4 text-text-secondary shrink-0" />
                    <span x-text="nombre" class="text-sm font-medium text-text-primary truncate max-w-xs"></span>
                </div>
                <p class="text-xs text-text-secondary mt-1">Formatos: .xlsx, .xls, .csv · Máx. 50 MB</p>
            </div>

            {{-- Barra de progreso --}}
            <div x-show="subiendo || (progreso > 0 && progreso < 100)" x-cloak class="w-full max-w-xs h-1.5 rounded-full bg-border overflow-hidden">
                <div class="h-full rounded-full bg-science-blue transition-all duration-300" :style="'width: ' + progreso + '%'"></div>
            </div>
            <p x-show="errorSubida" x-cloak class="text-xs text-error">Ese archivo no es válido. Usa un .xlsx, .xls o .csv.</p>

            {{-- Input real (siempre en el DOM) --}}
            <input
                type="file"
                wire:model="archivo"
                class="hidden"
                x-ref="fileInput"
                accept=".xlsx,.xls,.csv"
                x-on:click.stop
                x-on:change="nombre = $event.target.files[0]?.name ?? null; errorSubida = false"
            />
        </div>

        <div wire:loading wire:target="archivo" class="flex items-center gap-2 text-sm text-text-secondary">
            <flux:icon name="arrow-path" class="size-4 animate-spin" />
            Subiendo archivo…
        </div>

        <flux:error name="archivo" />

        {{-- Separador CSV (solo relevante para CSV) --}}
        <flux:field class="sm:max-w-xs">
            <flux:label>Separador del CSV</flux:label>
            <flux:select wire:model="delimitador">
                <flux:select.option value=",">Coma ( , )</flux:select.option>
                <flux:select.option value=";">Punto y coma ( ; )</flux:select.option>
                <flux:select.option value="&#9;">Tabulación</flux:select.option>
            </flux:select>
            <flux:description>Se usa solo si subes un archivo .csv. Los Excel lo ignoran.</flux:description>
        </flux:field>

        {{-- Autocompletar ubicación por coordenadas --}}
        <div class="rounded-lg border border-border bg-bg-main p-3 space-y-1.5">
            <flux:switch wire:model="geocodificar" label="Completar ubicación por coordenadas" />
            <p class="text-xs text-text-secondary">
                Cuando una fila trae coordenadas pero le falta país, provincia, cantón o localidad, se
                deducen con OpenStreetMap. Se aplica solo al <strong>importar</strong> (no al validar) y
                puede ser <strong>lento</strong> (~1 consulta por segundo). En esta pantalla se consultan
                hasta 250 coordenadas nuevas por corrida; para el catálogo completo usa el comando
                <code class="text-xs">php artisan inventario:importar-catalogo --geocodificar</code>.
            </p>
        </div>

        {{-- Acciones --}}
        <div class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center">
            <flux:button
                variant="filled"
                icon="beaker"
                wire:click="validar"
                wire:loading.attr="disabled"
                wire:target="validar,importar"
                class="w-full sm:w-auto"
                x-bind:disabled="!nombre"
            >
                <span wire:loading.remove wire:target="validar">Validar (simulación)</span>
                <span wire:loading wire:target="validar">Validando…</span>
            </flux:button>

            <flux:button
                variant="primary"
                icon="arrow-down-on-square"
                wire:click="importar"
                wire:loading.attr="disabled"
                wire:target="validar,importar"
                wire:confirm="Vas a guardar los especímenes de este archivo en el catálogo. ¿Continuar?"
                class="w-full sm:w-auto"
                x-bind:disabled="!nombre"
            >
                <span wire:loading.remove wire:target="importar">Importar al catálogo</span>
                <span wire:loading wire:target="importar">Importando…</span>
            </flux:button>

            <flux:button
                variant="ghost"
                icon="x-mark"
                wire:click="limpiar"
                wire:loading.attr="disabled"
                wire:target="validar,importar"
                class="w-full sm:w-auto sm:ml-auto"
            >
                Limpiar
            </flux:button>
        </div>

        {{-- Indicador de proceso en curso --}}
        <div
            wire:loading.flex
            wire:target="validar,importar"
            class="items-center gap-2 rounded-lg border border-science-blue/30 bg-science-blue/5 px-4 py-3 text-sm text-science-blue"
        >
            <flux:icon name="arrow-path" class="size-4 animate-spin shrink-0" />
            Procesando el archivo. Esto puede tardar en archivos grandes…
        </div>
    </div>

    {{-- Resultados --}}
    @if($resultado)
        <div class="rounded-lg border border-border bg-surface shadow-sm p-4 sm:p-6 space-y-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <flux:heading size="lg" class="text-blue-navy font-semibold">Resultado</flux:heading>
                @if($resultado['dryRun'])
                    <flux:badge color="blue" icon="beaker">Simulación — no se guardó nada</flux:badge>
                @else
                    <flux:badge color="green" icon="check-circle">Guardado en el catálogo</flux:badge>
                @endif
            </div>

            {{-- Tarjetas de conteo --}}
            <div class="grid gap-3 grid-cols-2 lg:grid-cols-3">
                @php
                    $tarjetas = [
                        ['label' => 'Filas leídas', 'valor' => $resultado['filasLeidas'], 'color' => 'text-text-primary'],
                        ['label' => 'Especímenes', 'valor' => $resultado['especimenesPersistidos'], 'color' => 'text-bio-green'],
                        ['label' => 'Muestras creadas', 'valor' => $resultado['muestrasCreadas'], 'color' => 'text-science-blue'],
                        ['label' => 'Duplicados saltados', 'valor' => $resultado['duplicadosSaltados'], 'color' => 'text-text-secondary'],
                        ['label' => 'Para revisión', 'valor' => $resultado['marcadosParaRevision'], 'color' => 'text-warning'],
                        ['label' => 'Errores fatales', 'valor' => count($resultado['erroresFatales']), 'color' => count($resultado['erroresFatales']) > 0 ? 'text-error' : 'text-text-secondary'],
                    ];
                @endphp
                @foreach($tarjetas as $t)
                    <div class="rounded-lg border border-border bg-bg-main p-3">
                        <div class="text-2xl font-bold {{ $t['color'] }}">{{ number_format((int) $t['valor']) }}</div>
                        <div class="text-xs text-text-secondary mt-0.5">{{ $t['label'] }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Motivos de revisión --}}
            @if(!empty($resultado['motivosRevision']))
                <div>
                    <div class="text-xs font-semibold text-text-secondary uppercase tracking-wide mb-2">
                        Motivos de revisión más frecuentes
                    </div>
                    <div class="space-y-1.5">
                        @foreach($resultado['motivosRevision'] as $motivo => $conteo)
                            <div class="flex items-center justify-between gap-3 rounded-md border border-warning/30 bg-warning/5 px-3 py-2 text-sm">
                                <span class="text-text-primary">{{ $motivo }}</span>
                                <flux:badge color="amber" size="sm">{{ number_format((int) $conteo) }}×</flux:badge>
                            </div>
                        @endforeach
                    </div>
                    <p class="text-xs text-text-secondary mt-2">
                        Estos especímenes se guardaron igual, pero quedan pendientes en el
                        <a href="{{ route('inventario.taxonomia.revision') }}" wire:navigate class="text-science-blue hover:text-blue-navy underline">Centro de revisión</a>.
                    </p>
                </div>
            @endif

            {{-- Errores fatales --}}
            @if(!empty($resultado['erroresFatales']))
                <div>
                    <div class="text-xs font-semibold text-error uppercase tracking-wide mb-2">
                        Filas que no se pudieron procesar
                    </div>
                    <div class="space-y-1.5">
                        @foreach(array_slice($resultado['erroresFatales'], 0, 15) as $err)
                            <div class="flex items-start gap-2 rounded-md border border-error/30 bg-error/5 px-3 py-2 text-sm">
                                <flux:badge color="red" size="sm">Fila {{ $err['fila'] }}</flux:badge>
                                <span class="text-text-primary break-words">{{ $err['error'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    @if(count($resultado['erroresFatales']) > 15)
                        <p class="text-xs text-text-secondary mt-2">… y {{ count($resultado['erroresFatales']) - 15 }} errores más.</p>
                    @endif
                </div>
            @endif

            {{-- Siguiente paso tras un dry-run limpio --}}
            @if($resultado['dryRun'] && empty($resultado['erroresFatales']))
                <div class="rounded-lg border border-bio-green/30 bg-bio-green/5 px-4 py-3 text-sm text-text-primary">
                    La simulación no encontró errores fatales. Si el resumen luce bien, presiona
                    <strong>«Importar al catálogo»</strong> para guardar.
                </div>
            @endif
        </div>
    @endif
</div>
