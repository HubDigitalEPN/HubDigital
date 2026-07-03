@php
    $tarjetas = [
        [
            'titulo' => 'Revisión de localidades',
            'descripcion' => 'Enlaza nombres crudos del Excel con localidades canónicas registradas.',
            'count' => $localidadesVerbatimPendientes,
            'unit' => 'verbatims',
            'ruta' => 'inventario.taxonomia.localidades.revision',
            'icon' => 'map-pin',
            'orden' => 1,
        ],
        [
            'titulo' => 'Revisión de taxa',
            'descripcion' => 'Asocia nombres taxonómicos crudos a especies del catálogo.',
            'count' => $taxonVerbatimPendientes,
            'unit' => 'verbatims',
            'ruta' => 'inventario.taxonomia.taxones.revision',
            'icon' => 'tag',
            'orden' => 2,
        ],
        [
            'titulo' => 'Parseo de fechas',
            'descripcion' => 'Asigna fechas canónicas a los verbatims que el parser no reconoció.',
            'count' => $fechaVerbatimPendientes,
            'unit' => 'verbatims',
            'ruta' => 'inventario.taxonomia.fechas.revision',
            'icon' => 'calendar-days',
            'orden' => 3,
        ],
        [
            'titulo' => 'Duplicados de catalog#',
            'descripcion' => 'Decide si grupos con catalog_number repetido son eventos distintos o error.',
            'count' => $duplicadosCatalogNumberGrupos,
            'unit' => 'grupos',
            'ruta' => 'inventario.taxonomia.especimenes.duplicados',
            'icon' => 'exclamation-triangle',
            'orden' => 4,
        ],
        [
            'titulo' => 'Muestras de colecta',
            'descripcion' => 'Confirma o descarta las muestras que el importador agrupó por su código de colecta original.',
            'count' => $muestrasPendientes,
            'unit' => 'muestras',
            'ruta' => 'inventario.taxonomia.muestras',
            'icon' => 'rectangle-stack',
            'orden' => 5,
        ],
    ];
@endphp

<div class="space-y-6 p-4 sm:p-6 max-w-6xl">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Centro de revisión del catálogo</flux:heading>
            <p class="text-sm text-text-secondary mt-1">
                Punto de partida para corregir lo que el importador no pudo resolver automáticamente.
                Empieza por el orden sugerido para máxima eficiencia.
            </p>
        </div>
        <flux:button variant="ghost" icon="arrow-path"
                     wire:click="actualizar"
                     wire:loading.attr="disabled" wire:target="actualizar"
                     class="w-full sm:w-auto">
            <span wire:loading.remove wire:target="actualizar">Actualizar conteos</span>
            <span wire:loading wire:target="actualizar">Actualizando…</span>
        </flux:button>
    </div>

    @if($errorMessage)<flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>@endif

    {{-- Métricas globales --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-border bg-surface shadow-sm p-5">
            <div class="text-xs font-semibold text-text-secondary uppercase tracking-wide">Total especímenes</div>
            <div class="text-3xl font-bold text-text-primary mt-2">{{ number_format($totalEspecimenes) }}</div>
        </div>

        <div class="rounded-lg border border-success/30 bg-success/5 shadow-sm p-5">
            <div class="text-xs font-semibold text-success uppercase tracking-wide">Publicables a GBIF</div>
            <div class="text-3xl font-bold text-success mt-2">{{ number_format($publicablesGbif) }}</div>
            <div class="text-xs text-text-secondary mt-1">
                {{ $porcentajePublicables }}% del catálogo cumple los criterios mínimos
                (taxón identificado, coordenadas válidas, no descartado).
            </div>
        </div>

        <div class="rounded-lg border border-warning/30 bg-warning/5 shadow-sm p-5">
            <div class="text-xs font-semibold text-warning uppercase tracking-wide">Pendientes de revisión</div>
            <div class="text-3xl font-bold text-warning mt-2">{{ number_format($pendientesRevision) }}</div>
            <div class="text-xs text-text-secondary mt-1">
                Especímenes con motivo de revisión activo. Se resuelven usando las bandejas de abajo.
            </div>
        </div>
    </div>

    {{-- Ayuda general --}}
    <x-inventariogestioncoleccion::bandeja-ayuda titulo="¿Qué es esto? Lee antes de empezar" storage-key="centro-revision-ayuda">
        <p>
            Cuando se importó el Excel (~48.000 filas) algunos datos no pudieron interpretarse automáticamente —
            taxa con typos, localidades sin canonizar, fechas con formato raro, catalog_numbers repetidos.
            En vez de descartarlos, el sistema los marcó como <strong>pendientes</strong> y los agrupó en
            <strong>5 bandejas de revisión</strong> para que tú decidas caso por caso.
        </p>
        <p>
            <strong>Orden recomendado</strong> (las flechas indican que la primera desbloquea más matches en la siguiente):
        </p>
        <ol class="list-decimal pl-6 space-y-1 text-xs text-text-secondary">
            <li>Localidades — registra primero las canónicas en <code>/localidades</code>, después enlaza los verbatims.</li>
            <li>Taxa — registra morfoespecies en <code>/taxones</code> si hace falta, después enlaza los verbatims.</li>
            <li>Fechas — bulk-apply de fechas no parseadas.</li>
            <li>Duplicados de catalog# — caso por caso (eventos distintos vs error).</li>
            <li>Muestras de colecta — confirma/descarta en bulk.</li>
        </ol>
        <p class="text-xs text-text-secondary">
            Cuando todas las bandejas estén en 0, el catálogo está completamente curado y listo para exportar a GBIF.
        </p>
    </x-inventariogestioncoleccion::bandeja-ayuda>

    {{-- Tarjetas de bandejas --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach($tarjetas as $t)
            @php
                $esCero = $t['count'] === 0;
                $colorBorde = $esCero ? 'border-success/30 bg-success/5' : 'border-warning/30 bg-warning/5';
                $colorTexto = $esCero ? 'text-success' : 'text-warning';
            @endphp
            <a href="{{ route($t['ruta']) }}" wire:navigate
               class="block rounded-lg border-2 {{ $colorBorde }} p-5 hover:shadow-md transition-shadow group">
                <div class="flex items-start justify-between gap-2 mb-3">
                    <span class="inline-flex items-center gap-2 text-xs font-semibold {{ $colorTexto }} uppercase tracking-wide">
                        <span class="inline-flex items-center justify-center size-6 rounded-full bg-surface border border-current text-current font-bold text-xs">{{ $t['orden'] }}</span>
                        Bandeja {{ $t['orden'] }}
                    </span>
                    <flux:icon name="{{ $t['icon'] }}" class="size-5 {{ $colorTexto }}" />
                </div>
                <div class="font-medium text-text-primary mb-1">{{ $t['titulo'] }}</div>
                <p class="text-xs text-text-secondary mb-4">{{ $t['descripcion'] }}</p>
                <div class="flex items-end justify-between gap-2">
                    <div>
                        <div class="text-3xl font-bold {{ $colorTexto }}">{{ number_format($t['count']) }}</div>
                        <div class="text-xs text-text-secondary">{{ $t['unit'] }} pendiente(s)</div>
                    </div>
                    <flux:icon name="arrow-right" @class([
                        'size-5 text-text-secondary group-hover:translate-x-1 transition',
                        'group-hover:text-success' => $esCero,
                        'group-hover:text-warning' => ! $esCero,
                    ]) />
                </div>
            </a>
        @endforeach
    </div>

    {{-- Falta de información: no resoluble por revisión (depende del Excel de origen) --}}
    @php $totalSinDato = $taxonSinDatoOrigen + $fechaSinDatoOrigen + $localidadSinDatoOrigen; @endphp
    <div class="rounded-lg border border-border bg-surface shadow-sm p-5">
        <div class="mb-1 flex items-center gap-2">
            <flux:icon name="information-circle" class="size-5 text-text-secondary" />
            <div class="text-sm font-semibold text-text-primary">Falta de información en el origen</div>
        </div>
        <p class="mb-4 text-xs text-text-secondary">
            Estos especímenes <strong>no</strong> aparecen en las bandejas de arriba: el Excel no trae ningún
            texto que enlazar (ni nombre de taxón, ni fecha, ni localidad), así que no se pueden resolver
            asignando un valor —no hay de dónde partir—. Quedan registrados como <strong>falta de información</strong>
            de la fuente; depende del dato original y se corrige re-importando un Excel más completo.
        </p>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-border bg-bg-main p-3">
                <div class="text-xs text-text-secondary">Sin taxón de origen</div>
                <div class="text-2xl font-bold text-text-primary">{{ number_format($taxonSinDatoOrigen) }}</div>
            </div>
            <div class="rounded-lg border border-border bg-bg-main p-3">
                <div class="text-xs text-text-secondary">Sin fecha de origen</div>
                <div class="text-2xl font-bold text-text-primary">{{ number_format($fechaSinDatoOrigen) }}</div>
            </div>
            <div class="rounded-lg border border-border bg-bg-main p-3">
                <div class="text-xs text-text-secondary">Sin localidad de origen</div>
                <div class="text-2xl font-bold text-text-primary">{{ number_format($localidadSinDatoOrigen) }}</div>
            </div>
        </div>
        @if($totalSinDato === 0)
            <p class="mt-3 text-xs text-success">Todos los especímenes traen al menos un dato de origen para revisar. 👍</p>
        @endif
    </div>

    {{-- Atajos auxiliares --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm p-5">
        <div class="text-xs font-semibold text-text-secondary uppercase tracking-wide mb-3">Atajos útiles</div>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4 text-sm">
            <a href="{{ route('inventario.taxonomia.localidades') }}" wire:navigate
               class="text-info hover:underline inline-flex items-center gap-2">
                <flux:icon name="plus-circle" class="size-4" />
                Registrar localidad canónica
            </a>
            <a href="{{ route('inventario.taxonomia.taxones') }}" wire:navigate
               class="text-info hover:underline inline-flex items-center gap-2">
                <flux:icon name="plus-circle" class="size-4" />
                Registrar taxón / morfoespecie
            </a>
            <a href="{{ route('inventario.taxonomia.dataset.config') }}" wire:navigate
               class="text-info hover:underline inline-flex items-center gap-2">
                <flux:icon name="globe-alt" class="size-4" />
                Configuración GBIF
            </a>
            <a href="{{ route('inventario.taxonomia.especimenes') }}" wire:navigate
               class="text-info hover:underline inline-flex items-center gap-2">
                <flux:icon name="magnifying-glass" class="size-4" />
                Buscar en catálogo
            </a>
        </div>
    </div>
</div>
