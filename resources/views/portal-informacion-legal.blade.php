@component('layouts.portal', ['title' => $tipo === 'privacidad' ? 'Política de privacidad' : 'Términos de uso'])
<article data-platform-information-content class="portal-container mx-auto max-w-4xl space-y-5 px-5 py-10 leading-relaxed text-text-primary">
    <h1 class="font-display text-3xl font-bold text-blue-navy">{{ $tipo === 'privacidad' ? 'Política de privacidad' : 'Términos de uso' }}</h1>
    @foreach(array_keys(config('portal-copy.'.$tipo)) as $clave)
        <x-portal-information-text :clave="$tipo.'.'.$clave" />
    @endforeach
    <a data-platform-information-return class="inline-block underline text-science-blue" href="{{ route('portal.catalogo', ['vista' => 'mapa']) }}">Volver a la distribución de registros</a>
</article>
@endcomponent
