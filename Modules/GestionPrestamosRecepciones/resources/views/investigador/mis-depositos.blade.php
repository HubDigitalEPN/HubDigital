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

    $activeFilters = collect([$filtroTipo, $filtroEstado, $filtroDesde, $filtroHasta, $soloEnProceso])->filter()->count();
@endphp

<div class="hub-transactional-ui hub-depositos-ui hub-workspace space-y-5 p-4 sm:p-6">

    {{-- Encabezado --}}
    <header class="hub-page-header">
        <div>
            <p class="hub-page-kicker">Portal del depositante</p>
            <h1 class="mt-1 hub-page-title">Mis depósitos</h1>
            <flux:text class="text-text-secondary text-sm mt-1">
                Historial de tus solicitudes de depósito de especímenes.
            </flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:navigate href="{{ route('prestamos.investigador.deposito.crear') }}" class="shrink-0 self-start sm:self-auto">
            {{ $borrador ? 'Continuar borrador' : 'Nueva solicitud' }}
        </flux:button>
    </header>

    @if($borrador)
        <section class="hub-table-shell p-4" aria-label="Borrador pendiente">
            <h2 class="font-semibold text-blue-navy">Tienes un borrador guardado</h2>
            <p class="mt-1 text-sm text-text-secondary">{{ $borrador->numero }} · Paso {{ $borrador->paso_actual <= 4 ? $borrador->paso_actual + 1 : $borrador->paso_actual }} de 8. Puedes continuar desde el último paso guardado.</p>
            <a href="{{ route('prestamos.investigador.deposito.crear') }}" wire:navigate class="mt-2 inline-block font-semibold text-science-blue underline">Retomar borrador</a>
        </section>
    @endif

    {{-- Panel de filtros --}}
    <div class="hub-table-shell">

        {{-- Header del panel --}}
        <div class="flex items-center justify-between px-4 py-2.5 bg-bg-main border-b border-border">
            <div class="flex items-center gap-2">
                <flux:icon name="funnel" class="size-3.5 text-text-secondary" />
                <span class="text-xs font-semibold uppercase tracking-wide text-text-secondary">Filtros</span>
                @if($soloEnProceso)<span class="text-xs text-science-blue">En proceso</span>@endif
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
        <div class="grid gap-3 px-4 py-3 sm:grid-cols-2 xl:grid-cols-[9rem_minmax(13rem,1fr)_10rem_10rem] xl:items-end">

            {{-- Tipo --}}
            <flux:field>
                <flux:label class="text-xs">Tipo</flux:label>
                <flux:select wire:model.live="filtroTipo" class="w-full">
                    <flux:select.option value="">Todos</flux:select.option>
                    <flux:select.option value="{{ TipoTramite::Deposito->value }}">Depósito</flux:select.option>
                    <flux:select.option value="{{ TipoTramite::Donacion->value }}">Donación</flux:select.option>
                </flux:select>
            </flux:field>

            {{-- Estado --}}
            <flux:field>
                <flux:label class="text-xs">Estado</flux:label>
                <flux:select wire:model.live="filtroEstado" class="w-full">
                    <flux:select.option value="">Todos</flux:select.option>
                    <flux:select.option value="{{ EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value }}">Pendiente de Revisión</flux:select.option>
                    <flux:select.option value="por_revisar">Por revisar (todas)</flux:select.option>
                    <flux:select.option value="rechazadas">Rechazadas (todas)</flux:select.option>
                    <flux:select.option value="{{ EstadoSolicitudDeposito::PendienteDeRevisionDocumentalPrevia->value }}">Revisión documental previa</flux:select.option>
                    <flux:select.option value="{{ EstadoSolicitudDeposito::RetenidaParaAsesoriaCuratorial->value }}">Pausada para Asesoría</flux:select.option>
                    <flux:select.option value="{{ EstadoSolicitudDeposito::Rechazada->value }}">Rechazada</flux:select.option>
                    <flux:select.option value="{{ EstadoSolicitudDeposito::RequiereCorreccion->value }}">Requiere corrección</flux:select.option>
                    <flux:select.option value="{{ EstadoSolicitudDeposito::AprobadaDocumentalmente->value }}">Aprobada documentalmente</flux:select.option>
                    <flux:select.option value="{{ EstadoSolicitudDeposito::RechazoPermanente->value }}">Rechazo permanente</flux:select.option>
                    <flux:select.option value="{{ EstadoSolicitudDeposito::Devuelta->value }}">Devuelta</flux:select.option>
                </flux:select>
            </flux:field>

            {{-- Rango de fechas --}}
            <flux:field>
                <flux:label for="depositos-desde" class="text-xs">Desde</flux:label>
                <input
                    id="depositos-desde"
                    type="date"
                    wire:model.live="filtroDesde"
                    aria-invalid="{{ $errors->has('filtroDesde') ? 'true' : 'false' }}"
                    @if($errors->has('filtroDesde')) aria-describedby="depositos-desde-error" @endif
                    class="rounded-lg border bg-bg-main px-3 py-2 text-sm text-text-primary transition-colors focus:outline-none focus:ring-1
                        {{ $filtroDesde ? 'border-science-blue ring-1 ring-science-blue' : 'border-border' }}"
                />
                <flux:error name="filtroDesde" id="depositos-desde-error" />
            </flux:field>

            <flux:field>
                <flux:label for="depositos-hasta" class="text-xs">Hasta</flux:label>
                <input
                    id="depositos-hasta"
                    type="date"
                    wire:model.live="filtroHasta"
                    aria-invalid="{{ $errors->has('filtroHasta') ? 'true' : 'false' }}"
                    @if($errors->has('filtroHasta')) aria-describedby="depositos-hasta-error" @endif
                    class="rounded-lg border bg-bg-main px-3 py-2 text-sm text-text-primary transition-colors focus:outline-none focus:ring-1
                        {{ $filtroHasta ? 'border-science-blue ring-1 ring-science-blue' : 'border-border' }}"
                />
                <flux:error name="filtroHasta" id="depositos-hasta-error" />
            </flux:field>


        </div>
    </div>

    @if($depositos->isEmpty())

        <div class="hub-compact-empty rounded-xl border border-border bg-surface shadow-sm">
            @if($hayFiltros)
                <flux:icon name="funnel" class="size-7 text-warning/60" />
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
                <flux:icon name="archive-box" class="size-7 text-science-blue/50" />
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
                <flux:text>El funcionario responsable se pondrá en contacto contigo próximamente. No necesitas hacer nada por ahora.</flux:text>
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
                            @include('gestionprestamosrecepciones::investigador.mis-depositos._card', ['deposito' => $deposito, 'badgeVariant' => $badgeVariant, 'estadoRecepcion' => ($recepciones[$deposito->id]->estado ?? null), 'actaFirmada' => (($recepciones[$deposito->id]->acta_firmada_ruta ?? null) !== null)])
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
                            @include('gestionprestamosrecepciones::investigador.mis-depositos._card', ['deposito' => $deposito, 'badgeVariant' => $badgeVariant, 'estadoRecepcion' => ($recepciones[$deposito->id]->estado ?? null), 'actaFirmada' => (($recepciones[$deposito->id]->acta_firmada_ruta ?? null) !== null)])
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    @endif

</div>
