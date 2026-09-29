@component('layouts.app', ['title' => 'Análisis de diversidad'])
<div class="mx-auto w-full max-w-7xl space-y-6 p-4 pb-12 sm:p-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-science-blue">Investigación · colección biológica</p>
            <h1 class="mt-2 font-display text-3xl font-bold text-blue-navy">Análisis de diversidad</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-text-secondary">Calcula uno, varios o todos los indicadores sobre ejemplares identificados a nivel de especie y con un conteo individual positivo. Los registros sin identificación o sin conteo quedan fuera de estas fórmulas.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-science-blue hover:underline">Volver al panel</a>
    </header>

    <form method="get" action="{{ route('divulgacion.analisis-diversidad') }}" class="rounded-xl border border-border bg-white p-5 shadow-sm">
        <h2 class="font-display text-lg font-bold text-blue-navy">1. Delimitar el conjunto</h2>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <label class="text-xs font-semibold text-blue-navy">Provincia
                <select name="provincia" class="mt-1 block h-10 w-full rounded-md border border-border bg-white px-2 text-sm font-normal"><option value="">Todas</option>@foreach($provincias as $provincia)<option value="{{ $provincia }}" @selected(($filtros['provincia'] ?? '') === $provincia)>{{ $provincia }}</option>@endforeach</select>
            </label>
            <label class="text-xs font-semibold text-blue-navy">Año desde
                <input type="number" name="desde" min="1800" max="2100" value="{{ $filtros['desde'] ?? '' }}" class="mt-1 block h-10 w-full rounded-md border border-border px-3 text-sm font-normal">
            </label>
            <label class="text-xs font-semibold text-blue-navy">Año hasta
                <input type="number" name="hasta" min="1800" max="2100" value="{{ $filtros['hasta'] ?? '' }}" class="mt-1 block h-10 w-full rounded-md border border-border px-3 text-sm font-normal">
            </label>
            <label class="text-xs font-semibold text-blue-navy">Localidad A · comunidad
                <select name="sitio_a" class="mt-1 block h-10 w-full rounded-md border border-border bg-white px-2 text-sm font-normal"><option value="">Toda la selección</option>@foreach($localidades as $localidad)<option value="{{ $localidad->id }}" @selected(($filtros['sitio_a'] ?? '') === $localidad->id)>{{ $localidad->nombre_canonico }} · {{ $localidad->state_province }} · {{ substr($localidad->id, 0, 6) }}</option>@endforeach</select>
            </label>
            <label class="text-xs font-semibold text-blue-navy">Localidad B · comparación
                <select name="sitio_b" class="mt-1 block h-10 w-full rounded-md border border-border bg-white px-2 text-sm font-normal"><option value="">Sin comparación</option>@foreach($localidades as $localidad)<option value="{{ $localidad->id }}" @selected(($filtros['sitio_b'] ?? '') === $localidad->id)>{{ $localidad->nombre_canonico }} · {{ $localidad->state_province }} · {{ substr($localidad->id, 0, 6) }}</option>@endforeach</select>
            </label>
        </div>
        <p class="mt-2 text-xs text-text-secondary">Beta compara A y B; gamma usa la unión de ambos cuando se seleccionan. Los demás índices usan A, o toda la selección si A está vacía.</p>

        <fieldset class="mt-6 border-t border-border pt-5">
            <legend class="font-display text-lg font-bold text-blue-navy">2. Elegir indicadores</legend>
            <div class="mb-3 mt-3 flex gap-3 text-xs font-semibold"><button type="button" id="seleccionar-indicadores" class="text-science-blue hover:underline">Seleccionar todos</button><button type="button" id="limpiar-indicadores" class="text-text-secondary hover:underline">Limpiar</button></div>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach(\Modules\CatalogoPublico\Application\Services\CalcularDiversidadColeccion::METRICAS as $clave => $nombre)
                    <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-border px-3 py-2.5 text-sm text-text-primary hover:border-science-blue/40">
                        <input type="checkbox" name="metricas[]" value="{{ $clave }}" @checked(in_array($clave, $metricas, true)) class="indicador-check mt-0.5 rounded border-border text-science-blue">
                        <span>{{ $nombre }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        <div class="mt-5 flex flex-wrap items-center gap-4"><button type="submit" class="min-h-11 rounded-md bg-science-blue px-6 text-sm font-semibold text-white hover:bg-blue-navy">Calcular selección</button><span class="text-xs text-text-secondary">Consultas agrupadas en la base; resultados conservados cinco minutos.</span></div>
    </form>

    @if($resultado)
        <section aria-labelledby="titulo-resultados">
            <h2 id="titulo-resultados" class="font-display text-xl font-bold text-blue-navy">Resultados de la selección</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                @foreach([['Registros elegibles', $resultado['registros']], ['Individuos contados', $resultado['individuos']], ['Especies identificadas', $resultado['especies']]] as [$etiqueta, $valor])
                    <div class="rounded-xl border border-border bg-white p-4 shadow-sm"><span class="text-xs font-semibold uppercase tracking-wide text-text-secondary">{{ $etiqueta }}</span><strong class="mt-1 block font-display text-2xl text-blue-navy tabular-nums">{{ number_format($valor, 0, ',', '.') }}</strong></div>
                @endforeach
            </div>
            @if($resultado['individuos'] === 0)
                <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">No hay especies con conteos individuales positivos para estos filtros. Ajusta provincia, años o localidad.</p>
            @endif
            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($metricas as $clave)
                    @if(! in_array($clave, ['abundancia', 'acumulacion'], true))
                        @php $valor = $resultado['valores'][$clave] ?? null; @endphp
                        <article class="rounded-xl border border-border bg-white p-5 shadow-sm">
                            <h3 class="text-sm font-semibold text-text-secondary">{{ \Modules\CatalogoPublico\Application\Services\CalcularDiversidadColeccion::METRICAS[$clave] }}</h3>
                            <p class="mt-3 font-display text-3xl font-bold tabular-nums text-blue-navy">{{ $valor === null ? '—' : number_format($valor, in_array($clave, ['alfa', 'gamma'], true) ? 0 : 4, ',', '.') }}</p>
                            @if($valor === null)<p class="mt-2 text-xs text-text-secondary">No calculable con esta selección o faltan dos localidades distintas.</p>@endif
                        </article>
                    @endif
                @endforeach
            </div>
            @if(in_array('abundancia', $metricas, true))
                <div class="mt-5 overflow-hidden rounded-xl border border-border bg-white shadow-sm">
                    <div class="border-b border-border p-5"><h3 class="font-display text-lg font-bold text-blue-navy">Abundancia por especie</h3><p class="text-xs text-text-secondary">Treinta especies con mayor conteo conocido. El porcentaje usa {{ number_format($resultado['individuos'], 0, ',', '.') }} individuos como denominador.</p></div>
                    <div class="max-h-96 overflow-auto"><table class="w-full min-w-[30rem] text-left text-sm"><thead class="sticky top-0 bg-[#f3f7fb]"><tr><th class="px-4 py-2">Especie</th><th class="px-4 py-2 text-right">Individuos</th><th class="px-4 py-2 text-right">Relativa</th></tr></thead><tbody class="divide-y divide-border">@forelse(($resultado['valores']['abundancia'] ?? []) as $fila)<tr><td class="px-4 py-2 italic">{{ $fila['especie'] }}</td><td class="px-4 py-2 text-right tabular-nums">{{ number_format($fila['individuos'], 0, ',', '.') }}</td><td class="px-4 py-2 text-right tabular-nums">{{ number_format($fila['porcentaje'], 2, ',', '.') }} %</td></tr>@empty<tr><td colspan="3" class="px-4 py-4 text-text-secondary">Sin datos suficientes</td></tr>@endforelse</tbody></table></div>
                </div>
            @endif
            @if(in_array('acumulacion', $metricas, true))
                <div class="mt-5 rounded-xl border border-border bg-white p-5 shadow-sm"><h3 class="font-display text-lg font-bold text-blue-navy">Acumulación observada por muestra</h3><p class="mt-1 text-xs text-text-secondary">Muestras en orden de identificador; la curva muestra riqueza observada y no extrapola esfuerzo de muestreo futuro.</p><div class="mt-4 h-72"><canvas id="curva-acumulacion" role="img" aria-label="Curva de especies observadas por muestras"></canvas></div></div>
            @endif
        </section>
    @endif

    <aside class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950">
        <h2 class="font-display text-lg font-bold">Límites de interpretación</h2>
        <p class="mt-2">Estos índices describen los ejemplares y muestras conservados, no una estimación automática de la biodiversidad del hábitat. Simpson D es la probabilidad de que dos individuos sin reemplazo correspondan a la misma especie. Beta usa disimilitud de Sørensen entre dos localidades. Chao1 usa especies con uno o dos individuos; Jackknife de primer orden usa especies presentes en una sola muestra. Una localidad con muy poco esfuerzo de colecta puede producir valores inestables.</p>
        <p class="mt-2"><strong>Modelos depredador-presa:</strong> no se calculan con esta hoja porque no registra relaciones tróficas verificadas ni series temporales comparables de abundancia para pares de especies. Se requieren esos datos antes de ajustar ecuaciones de población.</p>
    </aside>
</div>
<script>
(() => {
    document.getElementById('seleccionar-indicadores')?.addEventListener('click', () => document.querySelectorAll('.indicador-check').forEach(c => c.checked = true));
    document.getElementById('limpiar-indicadores')?.addEventListener('click', () => document.querySelectorAll('.indicador-check').forEach(c => c.checked = false));
    const puntos = @json($resultado['valores']['acumulacion'] ?? []);
    const iniciar = () => {
        const canvas = document.getElementById('curva-acumulacion');
        if (!canvas || !window.HubDigitalChart || puntos.length === 0) return;
        new window.HubDigitalChart(canvas, {type: 'line', data: {labels: puntos.map(p => p.muestras), datasets: [{data: puntos.map(p => p.especies), borderColor: '#1476a7', backgroundColor: 'rgba(20,118,167,.12)', pointRadius: 0, fill: true, tension: 0.12}]}, options: {responsive: true, maintainAspectRatio: false, animation: false, plugins: {legend: {display: false}}, scales: {x: {title: {display: true, text: 'Muestras'}}, y: {title: {display: true, text: 'Especies observadas'}, beginAtZero: true}}}});
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar, {once: true}); else iniciar();
})();
</script>
@endcomponent
