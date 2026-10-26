@component('layouts.portal', ['title' => $titulo])
@php
    $identidad = app(\App\Support\InformacionRelease::class)->obtener();
    $fechaCita = now('America/Guayaquil')->locale('es');
    $variablesCita = ['{anio}' => $fechaCita->year, '{version}' => $identidad['version'] ?? 'desarrollo', '{fecha_consulta}' => $fechaCita->translatedFormat('j \\d\\e F \\d\\e Y')];
@endphp
<article data-platform-information-content class="portal-container mx-auto max-w-4xl space-y-5 px-5 py-10 leading-relaxed text-text-primary">
    <h1 class="font-display text-3xl font-bold text-blue-navy">{{ $titulo }}</h1>
    @foreach(array_keys(config('portal-copy.'.$seccion)) as $clave)
        <x-portal-information-text :clave="$seccion.'.'.$clave" :variables="$variablesCita" />
    @endforeach
    <a data-platform-information-return class="inline-block underline text-science-blue" href="{{ route('portal.catalogo', ['vista' => 'mapa']) }}">Volver a la distribución de registros</a>
</article>
@endcomponent
