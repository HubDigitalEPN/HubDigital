@php
    $sinAportes = $totalEnviadas === 0;
    $cupoRestante = max(0, $cupoMaximoDepositos - $depositosAnio);
    $cupoLleno = $depositosAnio >= $cupoMaximoDepositos;
    $porcentajeCupo = min(100, (int) round(($depositosAnio / $cupoMaximoDepositos) * 100));

    $chartData = [
        'labels' => ['Pendientes de revisión', 'Pausadas para asesoría', 'Rechazadas'],
        'valores' => [$pendientesRevision, $pausadasAsesoria, $rechazadas],
    ];
    $hayDatosGrafico = ($pendientesRevision + $pausadasAsesoria + $rechazadas) > 0;
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-4 p-6">

    {{-- Invitación a activar el rol complementario. Vive aquí y no en el layout:
         es contenido del panel, no del armazón, y antes salía en todas las pantallas. --}}
    <x-banner-activar-rol />

    {{-- Encabezado --}}
    <div class="flex flex-col gap-1">
        <h1 class="font-display text-2xl font-bold text-blue-navy">Mi contribución</h1>
        <p class="text-sm text-text-secondary">Bienvenido, {{ auth()->user()->name }}</p>
    </div>

    {{-- Hero: Tu contribución a la colección --}}
    <div class="relative overflow-hidden rounded-lg border border-border bg-surface p-6 shadow-sm">
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-bio-green/5 via-surface to-science-blue/5"></div>

        <div class="relative">
            <div class="flex items-center gap-2">
                <flux:icon name="sparkles" variant="outline" class="size-5 text-bio-green" />
                <h2 class="font-display text-lg font-semibold text-text-primary">Tu contribución a la colección</h2>
            </div>

            @if($sinAportes)
                <div class="mt-4 flex flex-col items-start gap-3">
                    <p class="text-sm text-text-secondary">
                        Aún no has registrado aportes. Cuando envíes tu primera solicitud, aquí verás el
                        impacto de tus especímenes en la colección del museo.
                    </p>
                    <flux:button
                        href="{{ route('prestamos.investigador.deposito.crear') }}"
                        wire:navigate
                        variant="primary"
                        icon="plus"
                    >
                        Crear mi primera solicitud
                    </flux:button>
                </div>
            @else
                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    <div class="flex flex-col">
                        <span class="font-display text-3xl font-bold text-bio-green">{{ number_format($individuos) }}</span>
                        <span class="text-xs font-medium uppercase tracking-wide text-text-secondary">Individuos aportados</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-display text-3xl font-bold text-science-blue">{{ number_format($morfoespecies) }}</span>
                        <span class="text-xs font-medium uppercase tracking-wide text-text-secondary">Morfoespecies</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-display text-3xl font-bold text-blue-navy">{{ number_format($lotes) }}</span>
                        <span class="text-xs font-medium uppercase tracking-wide text-text-secondary">Lotes</span>
                    </div>
                </div>

                <p class="mt-5 flex items-center gap-1.5 text-sm text-text-secondary">
                    <flux:icon name="globe-americas" variant="outline" class="size-4 shrink-0 text-bio-green" />
                    A través de
                    <strong class="text-text-primary">{{ $gruposTaxonomicos }}</strong>
                    {{ $gruposTaxonomicos === 1 ? 'grupo taxonómico' : 'grupos taxonómicos' }} de
                    <strong class="text-text-primary">{{ $provinciasOrigen }}</strong>
                    {{ $provinciasOrigen === 1 ? 'provincia' : 'provincias' }} del Ecuador.
                </p>
            @endif
        </div>
    </div>

    {{-- Tarjetas de estado --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-border bg-surface p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-science-blue/10">
                    <flux:icon name="document-text" variant="outline" class="size-5 text-science-blue" />
                </div>
                <div>
                    <p class="text-xs text-text-secondary">Solicitudes enviadas</p>
                    <p class="text-xl font-semibold text-text-primary">{{ $totalEnviadas }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-border bg-surface p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/10">
                    <flux:icon name="clock" variant="outline" class="size-5 text-warning" />
                </div>
                <div>
                    <p class="text-xs text-text-secondary">Pendientes de revisión</p>
                    <p class="text-xl font-semibold text-text-primary">{{ $pendientesRevision }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-border bg-surface p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-info/10">
                    <flux:icon name="pause-circle" variant="outline" class="size-5 text-info" />
                </div>
                <div>
                    <p class="text-xs text-text-secondary">Pausadas para asesoría</p>
                    <p class="text-xl font-semibold text-text-primary">{{ $pausadasAsesoria }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-border bg-surface p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-error/10">
                    <flux:icon name="x-circle" variant="outline" class="size-5 text-error" />
                </div>
                <div>
                    <p class="text-xs text-text-secondary">Rechazadas</p>
                    <p class="text-xl font-semibold text-text-primary">{{ $rechazadas }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Fila inferior: cupo anual + gráfico --}}
    <div class="grid gap-4 lg:grid-cols-2">

        {{-- Cupo anual de depósitos --}}
        <div class="rounded-lg border border-border bg-surface p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <flux:icon name="archive-box" variant="outline" class="size-5 text-blue-navy" />
                    <h2 class="text-base font-semibold text-text-primary">Cupo anual de depósitos</h2>
                </div>
                <span class="text-sm font-medium text-text-secondary">{{ now()->year }}</span>
            </div>

            <div class="mt-5 flex items-end justify-between">
                <p class="font-display text-3xl font-bold text-text-primary">
                    {{ $depositosAnio }}<span class="text-lg font-medium text-text-secondary">/{{ $cupoMaximoDepositos }}</span>
                </p>
                @if($cupoLleno)
                    <span class="inline-flex items-center gap-1 rounded-full bg-error/10 px-2.5 py-1 text-xs font-semibold text-error">
                        <flux:icon name="exclamation-circle" variant="outline" class="size-3.5" />
                        Cupo lleno
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-bio-green/10 px-2.5 py-1 text-xs font-semibold text-bio-green">
                        {{ $cupoRestante }} {{ $cupoRestante === 1 ? 'disponible' : 'disponibles' }}
                    </span>
                @endif
            </div>

            <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-border">
                <div
                    class="h-full rounded-full transition-all duration-500 {{ $cupoLleno ? 'bg-warning' : 'bg-science-blue' }}"
                    style="width: {{ $porcentajeCupo }}%"
                ></div>
            </div>

            <p class="mt-4 flex items-center gap-1.5 text-xs text-text-secondary">
                <flux:icon name="heart" variant="outline" class="size-4 shrink-0 text-bio-green" />
                Las <strong class="text-text-primary">donaciones</strong> no tienen límite anual.
                Has realizado <strong class="text-text-primary">{{ $donacionesRealizadas }}</strong>.
            </p>
        </div>

        {{-- Gráfico: distribución de solicitudes por estado --}}
        <div class="rounded-lg border border-border bg-surface p-6 shadow-sm">
            <div class="flex items-center gap-2">
                <flux:icon name="chart-pie" variant="outline" class="size-5 text-science-blue" />
                <h2 class="text-base font-semibold text-text-primary">Estado de mis solicitudes</h2>
            </div>

            @if($hayDatosGrafico)
                <div wire:ignore class="mt-4 flex items-center justify-center">
                    <div class="relative h-56 w-full max-w-xs">
                        <canvas id="dep-estado-chart"></canvas>
                    </div>
                </div>
            @else
                <div class="mt-4 flex h-56 flex-col items-center justify-center gap-2 text-center">
                    <flux:icon name="chart-pie" variant="outline" class="size-8 text-text-secondary/50" />
                    <p class="text-sm text-text-secondary">Sin datos para mostrar aún.</p>
                    <p class="text-xs text-text-secondary">El gráfico aparecerá cuando tengas solicitudes en proceso.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Accesos rápidos --}}
    <div class="rounded-lg border border-border bg-surface p-6 shadow-sm">
        <div class="flex flex-col items-start gap-4">
            <h2 class="text-base font-semibold text-text-primary">Accesos rápidos</h2>
            <div class="flex flex-wrap gap-3">
                <flux:button href="{{ route('prestamos.investigador.mis-depositos') }}" wire:navigate variant="primary" icon="clipboard-document-list">
                    Ver mis depósitos
                </flux:button>
                <flux:button href="{{ route('prestamos.investigador.deposito.crear') }}" wire:navigate variant="ghost" icon="plus">
                    Nueva solicitud
                </flux:button>
            </div>
        </div>
    </div>

</div>

@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
@endassets

@script
<script>
    const canvas = document.getElementById('dep-estado-chart');

    if (canvas && window.Chart) {
        const datos = @js($chartData);

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: datos.labels,
                datasets: [{
                    data: datos.valores,
                    // Colores de marca (canvas no lee tokens Tailwind):
                    // warning #FF9800, info #0288D1, error #D32F2F
                    backgroundColor: ['#FF9800', '#0288D1', '#D32F2F'],
                    borderColor: '#FFFFFF',
                    borderWidth: 2,
                    hoverOffset: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 14,
                            font: { size: 12, family: 'Inter, sans-serif' },
                            color: '#212121',
                        },
                    },
                },
            },
        });
    }
</script>
@endscript
