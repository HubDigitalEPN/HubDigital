@php
    use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
    use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;

    $badgeVariant = fn(string $estado) => match($estado) {
        EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value => 'info',
        EstadoSolicitudDeposito::RetenidaParaAsesoriaCuratorial->value  => 'warning',
        EstadoSolicitudDeposito::Rechazada->value                       => 'danger',
        default                                                          => 'ghost',
    };

    $leftBorder = fn(string $estado) => match($estado) {
        EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value => 'border-l-science-blue',
        EstadoSolicitudDeposito::RetenidaParaAsesoriaCuratorial->value  => 'border-l-warning',
        EstadoSolicitudDeposito::Rechazada->value                       => 'border-l-error',
        default                                                          => 'border-l-border',
    };

    $activeFilters = collect([$filtroTipo, $filtroEstado, $filtroDesde, $filtroHasta])->filter()->count();
@endphp

<div class="space-y-6">

    {{-- Encabezado --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1" class="font-display">Mis depósitos</flux:heading>
            <flux:text class="text-text-secondary text-sm mt-1">
                Historial de tus solicitudes de depósito de especímenes.
            </flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:navigate href="{{ route('prestamos.investigador.deposito.crear') }}">
            Nueva solicitud
        </flux:button>
    </div>

    {{-- Panel de filtros --}}
    <div class="rounded-xl border border-border bg-surface shadow-sm overflow-hidden">

        {{-- Header del panel --}}
        <div class="flex items-center justify-between px-4 py-2.5 bg-bg-main border-b border-border">
            <div class="flex items-center gap-2">
                <flux:icon name="funnel" class="size-3.5 text-text-secondary" />
                <span class="text-xs font-semibold uppercase tracking-wide text-text-secondary">Filtros</span>
                @if($activeFilters > 0)
                    <span class="inline-flex items-center justify-center size-4 rounded-full bg-science-blue text-white text-[10px] font-bold leading-none">
                        {{ $activeFilters }}
                    </span>
                @endif
            </div>
            @if($hayFiltros)
                <button wire:click="limpiarFiltros" class="text-xs font-medium text-science-blue hover:underline transition-colors">
                    Limpiar todo
                </button>
            @endif
        </div>

        {{-- Filtros en horizontal --}}
        <div class="px-4 py-3 flex flex-wrap items-end gap-4">

            {{-- Tipo --}}
            <flux:field>
                <flux:label class="text-xs">Tipo</flux:label>
                <flux:select wire:model.live="filtroTipo" size="sm" class="w-36">
                    <flux:select.option value="">Todos</flux:select.option>
                    <flux:select.option value="{{ TipoTramite::Deposito->value }}">Depósito</flux:select.option>
                    <flux:select.option value="{{ TipoTramite::Donacion->value }}">Donación</flux:select.option>
                </flux:select>
            </flux:field>

            {{-- Estado --}}
            <flux:field>
                <flux:label class="text-xs">Estado</flux:label>
                <flux:select wire:model.live="filtroEstado" size="sm" class="w-52">
                    <flux:select.option value="">Todos</flux:select.option>
                    <flux:select.option value="{{ EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value }}">Pendiente de Revisión</flux:select.option>
                    <flux:select.option value="{{ EstadoSolicitudDeposito::RetenidaParaAsesoriaCuratorial->value }}">Retenida para Asesoría</flux:select.option>
                    <flux:select.option value="{{ EstadoSolicitudDeposito::Rechazada->value }}">Rechazada</flux:select.option>
                </flux:select>
            </flux:field>

            {{-- Rango de fechas --}}
            <flux:field>
                <flux:label class="text-xs">Desde</flux:label>
                <input
                    type="date"
                    wire:model.live="filtroDesde"
                    class="px-3 py-1 text-xs rounded-lg border bg-bg-main text-text-primary transition-colors focus:outline-none focus:ring-1
                        {{ $filtroDesde ? 'border-science-blue ring-1 ring-science-blue' : 'border-border' }}"
                />
            </flux:field>

            <flux:field>
                <flux:label class="text-xs">Hasta</flux:label>
                <input
                    type="date"
                    wire:model.live="filtroHasta"
                    class="px-3 py-1 text-xs rounded-lg border bg-bg-main text-text-primary transition-colors focus:outline-none focus:ring-1
                        {{ $filtroHasta ? 'border-science-blue ring-1 ring-science-blue' : 'border-border' }}"
                />
            </flux:field>


        </div>
    </div>

    @if($depositos->isEmpty())

        <div class="flex flex-col items-center justify-center rounded-xl bg-surface min-h-[420px] px-8 text-center gap-4">
            @if($hayFiltros)
                <flux:icon name="funnel" class="size-10 text-warning/60" />
                <div>
                    <flux:heading size="lg" level="2">Sin resultados</flux:heading>
                    <flux:text class="text-text-secondary mt-1 text-sm max-w-xs">
                        No hay solicitudes que coincidan con los filtros aplicados.
                    </flux:text>
                </div>
                <button wire:click="limpiarFiltros" class="flex items-center gap-1.5 text-sm text-science-blue hover:text-science-blue/70 transition-colors cursor-pointer">
                    <flux:icon name="x-mark" class="size-4" />
                    Limpiar filtros
                </button>
            @else
                <flux:icon name="archive-box" class="size-10 text-science-blue/50" />
                <div>
                    <flux:heading size="lg" level="2">Aún no tienes solicitudes de depósito</flux:heading>
                    <flux:text class="text-text-secondary mt-1 text-sm max-w-xs">
                        Inicia el proceso para depositar especímenes en la colección de la EPN.
                    </flux:text>
                </div>
                <flux:button variant="filled" icon="plus" wire:navigate href="{{ route('prestamos.investigador.deposito.crear') }}">
                    Nueva solicitud de depósito
                </flux:button>
            @endif
        </div>

    @else

        {{-- Alerta retenidas --}}
        @if($activas->where('estado', EstadoSolicitudDeposito::RetenidaParaAsesoriaCuratorial->value)->isNotEmpty())
            <flux:callout variant="warning" icon="pause-circle">
                <flux:heading>{{ $activas->where('estado', EstadoSolicitudDeposito::RetenidaParaAsesoriaCuratorial->value)->count() }} solicitud(es) retenida(s) para Asesoría Curatorial</flux:heading>
                <flux:text>Un curador se pondrá en contacto contigo próximamente. No necesitas hacer nada por ahora.</flux:text>
            </flux:callout>
        @endif

        {{-- Sección: En curso --}}
        @if($activas->isNotEmpty())
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-science-blue animate-pulse"></span>
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-secondary">En curso &nbsp;·&nbsp; {{ $activas->count() }}</p>
                </div>
                <div class="space-y-3">
                    @foreach($activas as $deposito)
                        <div class="rounded-lg border border-border border-l-4 {{ $leftBorder($deposito->estado) }} bg-surface shadow-sm px-5 py-4">
                            @include('gestionprestamosrecepciones::investigador.mis-depositos._card', ['deposito' => $deposito, 'badgeVariant' => $badgeVariant])
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Sección: Historial --}}
        @if($historial->isNotEmpty())
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-border"></span>
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-secondary">Historial &nbsp;·&nbsp; {{ $historial->count() }}</p>
                </div>
                <div class="space-y-3">
                    @foreach($historial as $deposito)
                        <div class="rounded-lg border border-border border-l-4 {{ $leftBorder($deposito->estado) }} bg-surface shadow-sm px-5 py-4 opacity-75">
                            @include('gestionprestamosrecepciones::investigador.mis-depositos._card', ['deposito' => $deposito, 'badgeVariant' => $badgeVariant])
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    @endif

</div>
