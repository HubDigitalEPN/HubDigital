<div class="space-y-6 p-4 sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" level="1" class="font-display text-blue-navy font-bold">
            Asignación de unit trays
        </flux:heading>
        <flux:button
            variant="primary"
            icon="qr-code"
            wire:click="abrirReubicarEspecimenes"
            class="w-full min-h-[44px] sm:w-auto"
        >
            Reubicar especímenes
        </flux:button>
    </div>

    @if($successMessage)
        <flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>
    @endif

    @if($warningMessage)
        <flux:callout variant="warning" icon="exclamation-triangle" dismissible>{{ $warningMessage }}</flux:callout>
    @endif

    @if($errorMessage)
        <flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>
    @endif

    {{-- Contenido dependiente de la caja: se difumina mientras cambia --}}
    <div class="relative space-y-6">
        {{-- Spinner centrado: sin fondo ni backdrop-filter. El difuminado se aplica al
             contenido mismo (más abajo), no a este overlay, para que siempre cubra toda la
             tarjeta sin desajuste de altura. --}}
        <div
            wire:loading.flex
            wire:target="cajaSeleccionada"
            wire:key="overlay-cambio-caja"
            class="pointer-events-none absolute inset-0 z-20 hidden items-center justify-center"
        >
            <div class="flex items-center gap-3 rounded-lg border border-border bg-surface px-4 py-3 shadow-sm">
                <flux:icon name="arrow-path" class="size-5 animate-spin text-blue-navy" />
                <span class="text-sm font-medium text-text-primary">Cargando unit trays…</span>
            </div>
        </div>

    {{-- Contenido difuminable: filter blur sobre el elemento real garantiza cobertura
         completa (a diferencia de un overlay backdrop-filter, que puede no calzar en altura). --}}
    <div
        wire:loading.class="pointer-events-none select-none opacity-50 blur-[2px]"
        wire:target="cajaSeleccionada"
        class="space-y-6 transition duration-200"
    >

    {{-- Paso 1: Unit trays en una caja disponible --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm p-4 space-y-4">
        <flux:heading size="lg" class="text-blue-navy">1. Unit trays en una caja</flux:heading>

        <div class="grid gap-4 sm:grid-cols-2">
            {{-- Filtro por gabinete (y opción «en tránsito» si hay cajas en ese estado): acota el
                 selector de cajas cuando hay muchísimas. --}}
            <flux:field>
                <flux:label>Gabinete</flux:label>
                <flux:select wire:model.live="filtroGabinete">
                    <flux:select.option value="">Todos los gabinetes</flux:select.option>
                    @foreach($gabinetes as $gabinete)
                        <flux:select.option value="{{ $gabinete['id'] }}">
                            {{ $gabinete['codigo'] }}{{ $gabinete['nombre'] ? ' — '.$gabinete['nombre'] : '' }}
                        </flux:select.option>
                    @endforeach
                    @if($hayTransito)
                        <flux:select.option value="transito">En tránsito</flux:select.option>
                    @endif
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>Caja disponible</flux:label>
                <flux:select
                    wire:model.live="cajaSeleccionada"
                    wire:loading.attr="disabled"
                    wire:target="cajaSeleccionada"
                    placeholder="Selecciona una caja..."
                >
                    @forelse($cajasFiltradas as $caja)
                        <flux:select.option value="{{ $caja['id'] }}">{{ $caja['label'] }}</flux:select.option>
                    @empty
                        <flux:select.option value="" disabled>No hay cajas en este filtro</flux:select.option>
                    @endforelse
                </flux:select>
            </flux:field>
        </div>

        @if($cajaSeleccionada !== '')
            {{-- Contexto de la caja seleccionada + acción de crear --}}
            <div class="flex flex-col gap-3 rounded-lg bg-bg-main p-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2 text-sm text-text-primary">
                    <flux:icon name="archive-box" class="size-5 text-blue-navy" />
                    <span>Trabajando en <span class="font-medium">{{ $cajaSeleccionadaLabel }}</span></span>
                </div>
                <flux:button
                    variant="primary"
                    icon="plus"
                    wire:click="crearUnitTray"
                    class="w-full min-h-[44px] sm:w-auto"
                >
                    Nuevo unit tray
                </flux:button>
            </div>

            <p class="text-xs text-text-secondary">
                Los unit trays se numeran solos y se ordenan por su taxonomía (subfamilia → género → especie).
            </p>

            <x-inventariogestioncoleccion::seguimiento-fisico.taxonomia-leyenda />

            {{-- Tabla (desktop) --}}
            <div class="hidden md:block rounded-lg border border-border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-blue-navy">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-white">Taxonomía</th>
                                <th class="px-4 py-3 text-left font-medium text-white">N.°</th>
                                <th class="px-4 py-3 text-left font-medium text-white">Especímenes</th>
                                <th class="px-4 py-3 text-left font-medium text-white">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($unitTrays as $tray)
                                <tr class="hover:bg-bg-main transition-colors {{ $unitTraySeleccionado === $tray['unitTrayId'] ? 'bg-bg-main' : '' }}">
                                    <td class="px-4 py-3">
                                        <x-inventariogestioncoleccion::seguimiento-fisico.taxonomia-resumen
                                            :orden="$tray['orden']"
                                            :suborden="$tray['suborden']"
                                            :superfamilia="$tray['superfamilia']"
                                            :familia="$tray['familia']"
                                            :subfamilia="$tray['subfamilia']"
                                            :genero="$tray['genero']"
                                            :especie="$tray['especie']"
                                            :subfamilias="$tray['subfamilias'] ?? []"
                                            :generos="$tray['generos'] ?? []"
                                        />
                                    </td>
                                    <td class="px-4 py-3 text-text-secondary">{{ $tray['numero'] }}</td>
                                    <td class="px-4 py-3 text-text-secondary">{{ $tray['totalEspecimenes'] }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <flux:button
                                                size="sm"
                                                variant="filled"
                                                wire:click="seleccionarUnitTray('{{ $tray['unitTrayId'] }}')"
                                                class="min-h-[44px]"
                                            >
                                                Asignar especímenes
                                            </flux:button>
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="qr-code"
                                                wire:click="mostrarQrTray('{{ $tray['unitTrayId'] }}', '{{ $tray['numero'] }}')"
                                            >
                                                QR
                                            </flux:button>
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="arrows-right-left"
                                                wire:click="abrirReubicarTray('{{ $tray['unitTrayId'] }}', '{{ $tray['numero'] }}')"
                                            >
                                                Mover de caja
                                            </flux:button>
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="trash"
                                                wire:click="eliminarUnitTray('{{ $tray['unitTrayId'] }}')"
                                                wire:confirm="¿Eliminar el unit tray N.° {{ $tray['numero'] }}? Esta acción no se puede deshacer."
                                                class="text-error hover:text-error"
                                            >
                                                Eliminar
                                            </flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-text-secondary">
                                        Esta caja no tiene unit trays todavía.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Tarjetas (móvil) --}}
            <div class="md:hidden space-y-3">
                @forelse($unitTrays as $tray)
                    <div class="rounded-lg border border-border bg-surface p-4 shadow-sm space-y-3 {{ $unitTraySeleccionado === $tray['unitTrayId'] ? 'ring-1 ring-blue-navy' : '' }}">
                        <div class="flex items-start justify-between gap-2">
                            <x-inventariogestioncoleccion::seguimiento-fisico.taxonomia-resumen
                                :orden="$tray['orden']"
                                :suborden="$tray['suborden']"
                                :superfamilia="$tray['superfamilia']"
                                :familia="$tray['familia']"
                                :subfamilia="$tray['subfamilia']"
                                :genero="$tray['genero']"
                                :especie="$tray['especie']"
                                :subfamilias="$tray['subfamilias'] ?? []"
                                :generos="$tray['generos'] ?? []"
                            />
                            <span class="shrink-0 text-xs text-text-secondary">N.° {{ $tray['numero'] }}</span>
                        </div>
                        <dl class="space-y-1.5 text-sm">
                            <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Especímenes">
                                {{ $tray['totalEspecimenes'] }}
                            </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                        </dl>
                        <div class="flex flex-col gap-2 pt-1">
                            <flux:button
                                variant="filled"
                                wire:click="seleccionarUnitTray('{{ $tray['unitTrayId'] }}')"
                                class="w-full min-h-[44px]"
                            >
                                Asignar especímenes
                            </flux:button>
                            <div class="flex flex-wrap gap-2">
                                <flux:button
                                    variant="ghost"
                                    icon="qr-code"
                                    wire:click="mostrarQrTray('{{ $tray['unitTrayId'] }}', '{{ $tray['numero'] }}')"
                                    class="min-h-[44px]"
                                >
                                    QR
                                </flux:button>
                                <flux:button
                                    variant="ghost"
                                    icon="arrows-right-left"
                                    wire:click="abrirReubicarTray('{{ $tray['unitTrayId'] }}', '{{ $tray['numero'] }}')"
                                    class="min-h-[44px]"
                                >
                                    Mover de caja
                                </flux:button>
                                <flux:button
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="eliminarUnitTray('{{ $tray['unitTrayId'] }}')"
                                    wire:confirm="¿Eliminar el unit tray N.° {{ $tray['numero'] }}? Esta acción no se puede deshacer."
                                    class="min-h-[44px] text-error hover:text-error"
                                >
                                    Eliminar
                                </flux:button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-border p-8 text-center text-text-secondary">
                        Esta caja no tiene unit trays todavía.
                    </div>
                @endforelse
            </div>
        @endif
    </div>

    {{-- Paso 2: Asignar especímenes al unit tray seleccionado --}}
    @if($unitTraySeleccionado !== '')
        <div class="rounded-lg border border-border bg-surface shadow-sm p-4 space-y-4">
            <flux:heading size="lg" class="text-blue-navy">2. Especímenes a asignar</flux:heading>
            <p class="text-sm text-text-secondary">
                Busca por código de catálogo o nombre científico y marca los especímenes que pertenecen al unit tray seleccionado. La clasificación de la caja se recalcula automáticamente.
            </p>

            <flux:field>
                <flux:label>Buscar espécimen</flux:label>
                <flux:input
                    wire:model.live.debounce.400ms="busquedaEspecimen"
                    icon="magnifying-glass"
                    placeholder="Código de catálogo o nombre científico..."
                />
                <flux:description>
                    La lista muestra los ya asignados a este tray más las primeras coincidencias. Refina la búsqueda para encontrar otros.
                </flux:description>
            </flux:field>

            <div
                wire:loading.flex
                wire:target="busquedaEspecimen"
                class="items-center gap-2 text-sm text-text-secondary"
            >
                <flux:icon name="arrow-path" class="size-4 animate-spin text-blue-navy" />
                <span>Buscando especímenes…</span>
            </div>

            <div class="max-h-96 overflow-y-auto rounded-lg border border-border divide-y divide-border">
                @forelse($especimenes as $especimen)
                    <label
                        wire:key="especimen-{{ $especimen['id'] }}"
                        class="flex items-start gap-3 px-4 py-3 hover:bg-bg-main cursor-pointer min-h-[44px]"
                    >
                        <flux:checkbox
                            wire:model.live="especimenesSeleccionados"
                            value="{{ $especimen['id'] }}"
                            class="mt-0.5"
                        />
                        <div class="min-w-0 flex-1 space-y-0.5">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="font-medium text-text-primary">{{ $especimen['codigoCatalogo'] }}</span>
                                <span class="font-serif italic text-text-secondary">{{ $especimen['taxonNombre'] }}</span>
                                @if($especimen['unitTrayId'] === $unitTraySeleccionado)
                                    <flux:badge size="sm" color="green" icon="check" class="ml-auto shrink-0">Ya en este tray</flux:badge>
                                @elseif($especimen['unitTrayId'])
                                    <flux:badge size="sm" color="amber" class="ml-auto shrink-0">En otro unit tray</flux:badge>
                                @endif
                            </div>
                            {{-- Resumen de ubicación física: distingue cuál de varios especímenes
                                 con el mismo nombre científico es cuál, mostrando dónde está. --}}
                            @php
                                $u = $especimen['ubicacion'] ?? null;
                                $partesUbicacion = $u === null ? [] : array_filter([
                                    $u['gabineteCodigo'] ? 'Gab. '.$u['gabineteCodigo'] : null,
                                    $u['ranuraNumero'] ? 'Ranura '.$u['ranuraNumero'] : null,
                                    $u['cajaCodigo'] ?: null,
                                    $u['trayNumero'] ? 'Tray '.$u['trayNumero'] : null,
                                ]);
                            @endphp
                            @if($partesUbicacion !== [])
                                <p class="flex items-center gap-1 text-xs text-text-secondary">
                                    <flux:icon name="map-pin" class="size-3.5 shrink-0 text-blue-navy" />
                                    <span>{{ implode(' · ', $partesUbicacion) }}</span>
                                </p>
                            @else
                                <p class="text-xs italic text-text-secondary">Sin ubicación física asignada</p>
                            @endif
                        </div>
                    </label>
                @empty
                    <div class="px-4 py-6 text-center text-text-secondary">
                        @if(trim($busquedaEspecimen) !== '')
                            Ningún espécimen coincide con «{{ $busquedaEspecimen }}».
                        @else
                            No hay especímenes registrados.
                        @endif
                    </div>
                @endforelse
            </div>

            {{-- Confirmación inline, junto a la acción: el callout superior queda fuera de
                 vista tras desplazar la lista, así que el feedback se repite aquí. --}}
            @if($successMessage)
                <div class="flex items-center gap-2 rounded-lg bg-success/10 px-3 py-2 text-sm font-medium text-bio-green">
                    <flux:icon name="check-circle" class="size-5" />
                    <span>{{ $successMessage }}</span>
                </div>
            @endif

            <div class="flex flex-col gap-3 border-t border-border pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-text-secondary">
                    <span class="font-medium text-text-primary">{{ count($especimenesSeleccionados) }}</span>
                    {{ count($especimenesSeleccionados) === 1 ? 'espécimen seleccionado' : 'especímenes seleccionados' }}
                </p>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <flux:button
                        variant="ghost"
                        wire:click="cancelarSeleccion"
                        class="w-full min-h-[44px] sm:w-auto"
                    >
                        Cancelar
                    </flux:button>
                    <flux:button
                        variant="primary"
                        icon="check"
                        wire:click="asignarEspecimenes"
                        wire:loading.attr="disabled"
                        wire:target="asignarEspecimenes"
                        class="w-full min-h-[44px] sm:w-auto"
                    >
                        <span wire:loading.remove wire:target="asignarEspecimenes">Guardar asignación</span>
                        <span wire:loading wire:target="asignarEspecimenes">Guardando…</span>
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
    </div>{{-- fin contenido difuminable --}}
    </div>

    {{-- Modal: QR imprimible del unit tray (server-side, BaconQrCode, codifica el UnitTrayId) --}}
    <flux:modal wire:model="modalQr" wire:close="cerrarQrTray" class="w-full max-w-sm">
        <div class="space-y-4 p-1 text-center">
            <flux:heading size="lg" class="text-text-primary">QR del unit tray N.° {{ $qrTrayNumero }}</flux:heading>
            <p class="text-sm text-text-secondary">
                Imprime esta etiqueta y pégala en el unit tray. Siempre es el mismo código: sirve para escanearlo al reubicar.
            </p>
            @if($qrSvg)
                <div x-data="{
                        imprimir() {
                            const w = window.open('', '_blank');
                            if (! w) { return; }
                            w.document.write('<div style=\'width:240px\'>' + this.$refs.qr.innerHTML + '</div>');
                            w.document.close();
                            w.focus();
                            w.print();
                            w.close();
                        },
                     }">
                    <div x-ref="qr" class="flex justify-center rounded-lg border border-border bg-white p-2">
                        {!! $qrSvg !!}
                    </div>
                    <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-center">
                        <flux:button variant="primary" icon="printer" x-on:click="imprimir()" class="w-full min-h-[44px] sm:w-auto">
                            Imprimir
                        </flux:button>
                        <flux:button variant="ghost" icon="arrow-down-tray"
                                     href="data:image/svg+xml;charset=utf-8,{{ rawurlencode($qrSvg) }}"
                                     download="unit-tray-{{ $qrTrayNumero }}.svg" class="w-full min-h-[44px] sm:w-auto">
                            Descargar
                        </flux:button>
                    </div>
                </div>
            @endif
        </div>
    </flux:modal>

    {{-- Modal: reubicar especímenes a un unit tray de destino --}}
    <flux:modal wire:model="modalReubicarEspecimenes" wire:close="cerrarReubicarEspecimenes" class="w-full max-w-lg">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Reubicar especímenes</flux:heading>
            <p class="text-sm text-text-secondary">
                Escanea el QR de cada espécimen, confirma su información, elige el unit tray de destino y reubica.
            </p>

            @if($errorMessage)
                <flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>
            @endif

            {{-- Escáner por cámara (html5-qrcode): el QR del espécimen codifica su código de catálogo. --}}
            <div
                x-data="reubicacionScanner()"
                x-effect="$wire.modalReubicarEspecimenes || detener()"
                wire:ignore
            >
                <div class="flex flex-wrap items-center gap-2">
                    <flux:button type="button" size="sm" x-show="!escaneando" x-on:click="iniciar()" icon="camera" variant="primary">
                        Activar cámara
                    </flux:button>
                    <flux:button type="button" size="sm" x-show="escaneando" x-on:click="detener()" icon="x-mark" variant="danger">
                        Detener cámara
                    </flux:button>
                    {{-- Qué resuelve el próximo escaneo: un espécimen o el unit tray de destino --}}
                    <flux:button type="button" size="sm" x-on:click="modo = 'especimen'" x-bind:variant="modo === 'especimen' ? 'filled' : 'ghost'">
                        Escanear espécimen
                    </flux:button>
                    <flux:button type="button" size="sm" x-on:click="modo = 'destino'" x-bind:variant="modo === 'destino' ? 'filled' : 'ghost'">
                        Escanear destino
                    </flux:button>
                </div>
                <div x-ref="lector" class="mt-3 overflow-hidden rounded-lg"></div>
                <template x-if="error">
                    <p class="mt-2 text-sm text-error" x-text="error"></p>
                </template>
            </div>

            {{-- Popup de confirmación del espécimen recién escaneado --}}
            @if($especimenPorConfirmar)
                <div class="rounded-lg border border-info bg-info/10 p-3 space-y-2">
                    <p class="text-sm font-medium text-text-primary">¿Agregar este espécimen?</p>
                    <div class="text-sm text-text-secondary">
                        <span class="font-medium text-text-primary">{{ $especimenPorConfirmar['codigoCatalogo'] }}</span>
                        — <span class="font-serif italic">{{ $especimenPorConfirmar['taxonNombre'] }}</span>
                    </div>
                    <div class="flex gap-2">
                        <flux:button size="sm" variant="primary" icon="check" wire:click="confirmarEspecimenEscaneado" class="min-h-[44px]">Agregar</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="descartarEspecimenEscaneado" class="min-h-[44px]">Descartar</flux:button>
                    </div>
                </div>
            @endif

            {{-- Especímenes acumulados --}}
            <div>
                <p class="text-sm font-medium text-text-primary">
                    Especímenes a reubicar
                    <span class="text-text-secondary">({{ count($especimenesReubicar) }})</span>
                </p>
                @if($especimenesReubicar !== [])
                    <ul class="mt-2 divide-y divide-border rounded-lg border border-border">
                        @foreach($especimenesReubicar as $e)
                            <li wire:key="reub-{{ $e['id'] }}" class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                                <span class="min-w-0 truncate">
                                    <span class="font-medium text-text-primary">{{ $e['codigoCatalogo'] }}</span>
                                    <span class="font-serif italic text-text-secondary"> — {{ $e['taxonNombre'] }}</span>
                                </span>
                                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="quitarEspecimenReubicar('{{ $e['id'] }}')" class="shrink-0 min-h-[44px]" aria-label="Quitar" />
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-2 rounded-lg border border-dashed border-border px-3 py-4 text-center text-sm text-text-secondary">
                        Aún no has escaneado especímenes.
                    </p>
                @endif
            </div>

            {{-- Unit tray de destino: se elige de la lista de la caja actual o se escanea su QR --}}
            <flux:field>
                <flux:label>Unit tray de destino</flux:label>
                @if($trayDestinoReubicarLabel)
                    <div class="flex items-center gap-2 rounded-lg bg-bg-main px-3 py-2 text-sm">
                        <flux:icon name="check-circle" class="size-5 text-bio-green" />
                        <span class="text-text-primary">Destino: <span class="font-medium">{{ $trayDestinoReubicarLabel }}</span></span>
                    </div>
                @endif
                <flux:select wire:model="trayDestinoReubicar" wire:change="fijarTrayDestino($event.target.value)" placeholder="Selecciona un unit tray...">
                    @foreach($unitTrays as $tray)
                        <flux:select.option value="{{ $tray['unitTrayId'] }}">N.° {{ $tray['numero'] }} ({{ $tray['totalEspecimenes'] }} especímenes)</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>O escanea el QR del unit tray con la cámara de arriba (botón «Escanear destino»).</flux:description>
            </flux:field>

            {{-- Confirmación de advertencia taxonómica suave --}}
            @if($reubicacionRequiereConfirmacion)
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <p>
                        Estos especímenes no parecen pertenecer al unit tray de destino según su taxonomía:
                        <span class="font-medium">{{ implode(', ', $reubicacionFueraDeLugar) }}</span>.
                    </p>
                    <div class="mt-2 flex gap-2">
                        <flux:button size="sm" variant="primary" wire:click="reubicarEspecimenes(true)" class="min-h-[44px]">Reubicar de todos modos</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="cerrarReubicarEspecimenes" class="min-h-[44px]">Cancelar</flux:button>
                    </div>
                </flux:callout>
            @endif

            @unless($reubicacionRequiereConfirmacion)
                <div class="flex flex-col gap-2 border-t border-border pt-4 sm:flex-row sm:justify-end">
                    <flux:button variant="ghost" wire:click="cerrarReubicarEspecimenes" class="w-full min-h-[44px] sm:w-auto">Cancelar</flux:button>
                    <flux:button variant="primary" icon="arrows-right-left" wire:click="reubicarEspecimenes" wire:loading.attr="disabled" wire:target="reubicarEspecimenes" class="w-full min-h-[44px] sm:w-auto">
                        Reubicar especímenes
                    </flux:button>
                </div>
            @endunless
        </div>
    </flux:modal>

    {{-- Modal: reubicar un unit tray completo a otra caja (NFC de la caja o selección manual) --}}
    <flux:modal wire:model="modalReubicarTray" wire:close="cerrarReubicarTray" class="w-full max-w-md">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Mover unit tray {{ $trayAReubicarLabel }} a otra caja</flux:heading>
            <p class="text-sm text-text-secondary">Escanea el NFC de la caja de destino o selecciónala de la lista.</p>

            @if($errorMessage)
                <flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>
            @endif

            {{-- Escaneo NFC de la caja de destino (Web NFC; reusa el patrón de cajas/index) --}}
            <div
                x-data="{
                    nfcSupported: false,
                    scanning: false,
                    nfcError: null,
                    abort: null,
                    init() { this.nfcSupported = ('NDEFReader' in window); },
                    async scan() {
                        if (!this.nfcSupported || this.scanning) return;
                        this.nfcError = null;
                        this.scanning = true;
                        try {
                            this.abort = new AbortController();
                            const reader = new NDEFReader();
                            await reader.scan({ signal: this.abort.signal });
                            reader.onreadingerror = () => { this.nfcError = 'No se pudo leer la tarjeta. Reintenta.'; };
                            reader.onreading = (event) => {
                                const uid = (event.serialNumber || '').replace(/:/g, '').toUpperCase();
                                this.stop();
                                if (uid) { this.$wire.reubicarTrayPorRfid(uid); }
                            };
                        } catch (err) {
                            this.nfcError = (err && err.name === 'NotAllowedError') ? 'Permiso de NFC denegado.' : 'No se pudo iniciar el escaneo NFC.';
                            this.scanning = false;
                        }
                    },
                    stop() { if (this.abort) { this.abort.abort(); this.abort = null; } this.scanning = false; },
                }"
            >
                <flux:button type="button" x-show="nfcSupported" x-on:click="scanning ? stop() : scan()" x-bind:variant="scanning ? 'danger' : 'primary'" icon="wifi" class="w-full min-h-[44px]">
                    <span x-text="scanning ? 'Acerca la caja…' : 'Escanear NFC de la caja'"></span>
                </flux:button>
                <template x-if="nfcError">
                    <p class="mt-2 text-sm text-error" x-text="nfcError"></p>
                </template>
                <p x-show="!nfcSupported" class="text-xs text-text-secondary">Este dispositivo no soporta NFC; selecciona la caja de la lista.</p>
            </div>

            <flux:field>
                <flux:label>Caja de destino</flux:label>
                <flux:select wire:model="cajaDestinoSeleccionada" wire:change="reubicarTrayACaja($event.target.value)" placeholder="Selecciona una caja...">
                    @foreach($cajas as $caja)
                        <flux:select.option value="{{ $caja['id'] }}">{{ $caja['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <div class="flex justify-end pt-2">
                <flux:button variant="ghost" wire:click="cerrarReubicarTray" class="w-full min-h-[44px] sm:w-auto">Cancelar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

@script
<script>
    // html5-qrcode: escaneo de QR por cámara (iOS + Android) para reubicar especímenes/unit trays.
    if (! window.Html5QrcodeScanner && ! document.getElementById('html5-qrcode-lib')) {
        const s = document.createElement('script');
        s.id = 'html5-qrcode-lib';
        s.src = 'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js';
        document.head.appendChild(s);
    }

    Alpine.data('reubicacionScanner', () => ({
        // modo: 'especimen' (envía código de catálogo) | 'destino' (envía UnitTrayId del QR)
        modo: 'especimen',
        escaneando: false,
        error: null,
        scanner: null,
        ultimo: { texto: null, en: 0 },
        iniciar() {
            if (this.escaneando) return;
            if (! window.Html5QrcodeScanner) {
                this.error = 'Cargando el escáner… reintenta en un segundo.';
                return;
            }
            this.error = null;
            this.$refs.lector.id = this.$refs.lector.id || ('lector-' + Math.random().toString(36).slice(2));
            this.scanner = new window.Html5QrcodeScanner(this.$refs.lector.id, { fps: 10, qrbox: 250 }, false);
            this.scanner.render((texto) => this.onDecode(texto), () => {});
            this.escaneando = true;
        },
        detener() {
            if (this.scanner) { try { this.scanner.clear(); } catch (e) {} this.scanner = null; }
            this.escaneando = false;
        },
        onDecode(texto) {
            const ahora = Date.now();
            // Evita ráfagas: el mismo QR dispara el callback muchas veces por segundo.
            if (texto === this.ultimo.texto && (ahora - this.ultimo.en) < 2000) return;
            this.ultimo = { texto, en: ahora };
            if (this.modo === 'destino') {
                this.$wire.fijarTrayDestino(texto);
                this.modo = 'especimen';
            } else {
                this.$wire.escanearEspecimen(texto);
            }
        },
    }));
</script>
@endscript
