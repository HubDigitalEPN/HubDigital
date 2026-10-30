@php($pagina = app(\App\Services\PaginaLaboratorio::class)->leer())
@php($t = $pagina['textos'])
@component('layouts.portal', ['title' => $t['titulo']])
    <article class="lab-page">
        @if($pagina['posiciones'])
            @include('partials.laboratorio-libre')
        @else
            <section class="lab-introduction" aria-labelledby="lab-title">
                <h1 id="lab-title" data-lab-element="texto-titulo">{{ $t['titulo'] }}</h1>
                <x-laboratorio-texto clave="principal" :texto="$t['principal']" />
            </section>
            <section class="lab-personal" aria-labelledby="personal-title" data-lab-personal>
                <header class="lab-personal-hero">
                    <div class="lab-personal-heading">
                        <h2 id="personal-title" data-lab-element="texto-personal"><strong>{{ $t['personal_destacado'] }}</strong> {{ $t['personal_titulo'] }}</h2>
                        <x-laboratorio-texto clave="personal_descripcion" :texto="$t['personal_descripcion']" />
                    </div>
                </header>
                @php($ramas = ['entomologia' => ['personas' => ['adrian', 'vladimir', 'david']]] + config('pagina-laboratorio.ramas'))
                <nav class="lab-branch-nav" aria-label="Áreas de investigación">
                    @foreach($ramas as $rama => $datosRama)<a href="#rama-{{ $rama }}">{{ $rama === 'entomologia' ? 'Entomología' : $t['rama_'.$rama.'_titulo'] }}</a>@endforeach
                </nav>
                @foreach($ramas as $rama => $datosRama)
                    <section class="lab-branch" aria-labelledby="rama-{{ $rama }}" data-lab-branch="{{ $rama }}">
                        <header><h2 id="rama-{{ $rama }}" class="lab-branch-title" data-lab-element="texto-rama_{{ $rama }}_titulo">{{ $t['rama_'.$rama.'_titulo'] }}</h2></header>
                        <div class="lab-staff-grid">
                            @foreach($datosRama['personas'] as $persona)
                                <article class="lab-profile" data-lab-profile="{{ $persona }}">
                                    <x-laboratorio-retrato :clave="$persona" :foto="$pagina['fotos'][$persona]" />
                                    <div class="lab-profile-copy">
                                        <h3 data-lab-element="texto-{{ $persona }}_nombre">{{ $t[$persona.'_nombre'] }}</h3>
                                        @if(isset($t[$persona.'_cargo']))<p class="lab-role" data-lab-element="texto-{{ $persona }}_cargo">{{ $t[$persona.'_cargo'] }}</p>@endif
                                        <x-laboratorio-perfil-texto :persona="$persona" :pagina="$pagina" />
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
                @if($pagina['bloques'])
                    <section class="lab-extra-grid" aria-label="Más sobre el laboratorio" data-lab-extra>
                        @foreach($pagina['bloques'] as $bloque)
                            <article class="lab-extra-card" data-lab-block="{{ $bloque['id'] }}">
                                @if($bloque['foto']['ruta'])<x-laboratorio-foto class="lab-extra-photo" :clave="'bloque-'.$bloque['id']" :foto="$bloque['foto']" />@endif
                                @if(filled($bloque['titulo']))<h3 data-lab-element="texto-bloque-{{ $bloque['id'] }}-titulo">{{ $bloque['titulo'] }}</h3>@endif
                                @if(filled($bloque['texto']))<x-laboratorio-texto :clave="'bloque-'.$bloque['id'].'-texto'" :texto="$bloque['texto']" />@endif
                            </article>
                        @endforeach
                    </section>
                @endif
                <section class="lab-volunteers" aria-labelledby="volunteers-title">
                    <div class="lab-volunteers-info">
                        <h3 id="volunteers-title" data-lab-element="texto-colaboracion_titulo">{{ $t['colaboracion_titulo'] }}</h3>
                        <x-laboratorio-texto clave="colaboracion_texto" :texto="$t['colaboracion_texto']" />
                        <div class="lab-contact">
                            <h3 data-lab-element="texto-contacto_titulo">{{ $t['contacto_titulo'] }}</h3>
                            <a data-lab-element="texto-contacto_accion" href="mailto:{{ $t['contacto_email'] }}">{{ $t['contacto_accion'] }}</a>
                        </div>
                    </div>
                </section>
            </section>
        @endif
    </article>
@endcomponent
