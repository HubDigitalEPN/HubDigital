@component('layouts.portal', ['title' => 'Comparar especies de la colección'])
<div class="min-h-screen bg-[#f3f7fb] pb-16">
    <header class="border-b border-blue-navy/10 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-9 sm:px-6 lg:px-8">
            <p class="text-xs font-bold uppercase tracking-[0.15em] text-science-blue">Colección biológica · comparación</p>
            <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="font-display text-3xl font-bold text-blue-navy sm:text-4xl">Comparar especies</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-text-secondary">Contrasta los datos publicados de dos especies de la colección: registros, provincias, fechas y ejemplares tipo.</p>
                </div>
                <a href="{{ route('portal.catalogo') }}" class="text-sm font-semibold text-science-blue hover:underline">Volver al catálogo →</a>
            </div>
            <form method="get" action="{{ route('portal.comparar-especies') }}" class="mt-7 grid gap-3 rounded-xl border border-blue-navy/10 bg-[#f8fbfe] p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
                <label class="text-xs font-semibold text-blue-navy">Primera especie
                    <input name="a" list="especies-comparables" value="{{ $a }}" required autocomplete="off" placeholder="Escribe una especie de la colección" class="mt-1 block h-11 w-full rounded-md border border-blue-navy/20 bg-white px-3 text-sm font-normal">
                    @error('a')<span class="mt-1 block text-xs text-red-700">Selecciona una especie de la lista.</span>@enderror
                </label>
                <label class="text-xs font-semibold text-blue-navy">Segunda especie
                    <input name="b" list="especies-comparables" value="{{ $b }}" required autocomplete="off" placeholder="Escribe otra especie" class="mt-1 block h-11 w-full rounded-md border border-blue-navy/20 bg-white px-3 text-sm font-normal">
                    @error('b')<span class="mt-1 block text-xs text-red-700">Selecciona una especie de la lista.</span>@enderror
                </label>
                <datalist id="especies-comparables">@foreach($especies as $especie)<option value="{{ $especie }}"></option>@endforeach</datalist>
                <button type="submit" class="h-11 rounded-md bg-science-blue px-6 text-sm font-semibold text-white hover:bg-blue-navy">Comparar</button>
            </form>
            @if($a !== '' && $a === $b)
                <p class="mt-3 text-sm text-amber-800">Selecciona dos especies diferentes.</p>
            @endif
        </div>
    </header>

    @if($a !== '' && $b !== '' && $a !== $b)
        <div class="mx-auto max-w-7xl px-4 pt-8 sm:px-6 lg:px-8">
            <div class="grid gap-5 lg:grid-cols-2">
                @foreach([$a, $b] as $nombre)
                    @php $dato = $datos[$nombre] ?? null; @endphp
                    <section class="overflow-hidden rounded-xl border border-blue-navy/10 bg-white shadow-sm" aria-label="Datos de {{ $nombre }}">
                        <div class="border-b border-blue-navy/10 bg-[#f8fbfe] px-6 py-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-science-blue">Especie</p>
                            <h2 class="mt-1 font-display text-2xl italic text-blue-navy">{{ $nombre }}</h2>
                            <a href="{{ route('portal.catalogo', ['nivel' => 'species', 'taxon' => $nombre]) }}" class="mt-2 inline-block text-xs font-semibold text-science-blue hover:underline">Ver ficha y registros →</a>
                        </div>
                        <dl class="divide-y divide-blue-navy/10 px-6">
                            @foreach([
                                ['Registros publicados', $dato->registros ?? 0],
                                ['Provincias con dato visible', $dato->provincias ?? 0],
                                ['Registros con coordenadas visibles', $dato->georreferenciados ?? 0],
                                ['Ejemplares tipo publicados', $dato->ejemplares_tipo ?? 0],
                            ] as [$etiqueta, $valor])
                                <div class="flex items-center justify-between gap-4 py-4"><dt class="text-sm text-text-secondary">{{ $etiqueta }}</dt><dd class="font-display text-xl font-bold tabular-nums text-blue-navy">{{ number_format($valor, 0, ',', '.') }}</dd></div>
                            @endforeach
                            <div class="flex items-center justify-between gap-4 py-4"><dt class="text-sm text-text-secondary">Primera fecha visible</dt><dd class="text-sm font-semibold text-blue-navy">{{ $dato && $dato->primera_fecha ? \Carbon\CarbonImmutable::parse($dato->primera_fecha)->format('d/m/Y') : '—' }}</dd></div>
                            <div class="flex items-center justify-between gap-4 py-4"><dt class="text-sm text-text-secondary">Última fecha visible</dt><dd class="text-sm font-semibold text-blue-navy">{{ $dato && $dato->ultima_fecha ? \Carbon\CarbonImmutable::parse($dato->ultima_fecha)->format('d/m/Y') : '—' }}</dd></div>
                        </dl>
                    </section>
                @endforeach
            </div>
            <p class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">La comparación describe registros conservados y divulgados por el laboratorio; no mide diferencias biológicas entre especies ni abundancia en la naturaleza. No hay fotografías publicadas para una comparación visual.</p>
        </div>
    @endif
</div>
@endcomponent
