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
                <div @class(['lab-personal-heading', 'lab-personal-heading--wrap' => \Illuminate\Support\Str::length($t['personal_destacado'].' '.$t['personal_titulo']) > 20])>
                    <span class="lab-rule" aria-hidden="true"></span>
                    <h2 id="personal-title" data-lab-element="texto-personal"><strong>{{ $t['personal_destacado'] }}</strong> {{ $t['personal_titulo'] }}</h2>
                    <x-laboratorio-texto clave="personal_descripcion" :texto="$t['personal_descripcion']" />
                </div>
                <x-laboratorio-foto class="lab-personal-banner" clave="cabecera" :foto="$pagina['fotos']['cabecera']" />
            </header>
            <h2 class="lab-branch-title" data-lab-element="texto-rama_entomologia_titulo">{{ $t['rama_entomologia_titulo'] }}</h2>
            <div class="lab-staff-grid">
                <section class="lab-curador" aria-labelledby="adrian-title">
                    <x-laboratorio-foto class="lab-curador-portrait" clave="adrian" :foto="$pagina['fotos']['adrian']" />
                    <div class="lab-curador-info">
                        <h3 id="adrian-title" data-lab-element="texto-adrian_nombre">{{ $t['adrian_nombre'] }}</h3>
                        <p class="lab-role" data-lab-element="texto-adrian_cargo">{{ $t['adrian_cargo'] }}</p>
                        <x-laboratorio-texto clave="adrian_bio" class="lab-adrian-bio" :texto="$t['adrian_bio']" />
                        <span class="lab-rule" aria-hidden="true"></span>
                        <x-laboratorio-texto clave="adrian_especialidad" class="lab-adrian-specialty" :texto="$t['adrian_especialidad']" />
                        <x-laboratorio-foto class="lab-ant" clave="hormiga" :foto="$pagina['fotos']['hormiga']" />
                    </div>
                </section>
                <div class="lab-right-column">
                    <section class="lab-vladimir" aria-labelledby="vladimir-title">
                        <div class="lab-vladimir-top">
                            <x-laboratorio-foto clave="vladimir" :foto="$pagina['fotos']['vladimir']" />
                            <div>
                                <h3 id="vladimir-title" data-lab-element="texto-vladimir_nombre">{{ $t['vladimir_nombre'] }}</h3>
                                <p class="lab-role" data-lab-element="texto-vladimir_cargo">{{ $t['vladimir_cargo'] }}</p>
                                <span class="lab-rule" aria-hidden="true"></span>
                                <x-laboratorio-texto clave="vladimir_bio" class="lab-vladimir-bio" :texto="$t['vladimir_bio']" />
                            </div>
                        </div>
                        <div class="lab-invertebrates">
                            @foreach(['crustaceo', 'molusco', 'escarabajo'] as $clave)
                                <x-laboratorio-foto :clave="$clave" :foto="$pagina['fotos'][$clave]" />
                            @endforeach
                        </div>
                    </section>
                    <section class="lab-david" aria-labelledby="david-title">
                        <x-laboratorio-foto class="lab-field-portrait" clave="david" :foto="$pagina['fotos']['david']" />
                        <div class="lab-david-info">
                            <x-laboratorio-foto class="lab-ground-beetle" clave="suelo" :foto="$pagina['fotos']['suelo']" />
                            <h3 id="david-title" data-lab-element="texto-david_nombre">{{ $t['david_nombre'] }}</h3>
                            <p class="lab-role" data-lab-element="texto-david_cargo">{{ $t['david_cargo'] }}</p>
                            <span class="lab-rule" aria-hidden="true"></span>
                            <x-laboratorio-texto clave="david_bio" class="lab-david-bio" :texto="$t['david_bio']" />
                        </div>
                    </section>
                </div>
            </div>
            <p class="lab-source"><a href="https://biologia.epn.edu.ec/index.php/secciones/entomologia/personal" target="_blank" rel="noopener noreferrer">Perfiles institucionales de Entomología</a></p>
            @foreach(config('pagina-laboratorio.ramas') as $rama => $datosRama)
                <section class="lab-branch" aria-labelledby="rama-{{ $rama }}" data-lab-branch="{{ $rama }}">
                    <header><h2 id="rama-{{ $rama }}" data-lab-element="texto-rama_{{ $rama }}_titulo">{{ $t['rama_'.$rama.'_titulo'] }}</h2>
                        <a href="{{ $datosRama['fuente'] }}" target="_blank" rel="noopener noreferrer">Perfiles institucionales</a></header>
                    <div class="lab-branch-grid">
                        @foreach($datosRama['personas'] as $persona)
                            <article class="lab-branch-profile">
                                <x-laboratorio-foto :clave="$persona" :foto="$pagina['fotos'][$persona]" />
                                <div><h3 data-lab-element="texto-{{ $persona }}_nombre">{{ $t[$persona.'_nombre'] }}</h3>
                                    <x-laboratorio-texto :clave="$persona.'_bio'" :texto="$t[$persona.'_bio']" /></div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
            @if($pagina['bloques'])
                <section class="lab-extra-grid" aria-label="Más sobre el laboratorio" data-lab-extra>
                    @foreach($pagina['bloques'] as $bloque)
                        <article class="lab-extra-card" data-lab-block="{{ $bloque['id'] }}">
                            @if($bloque['foto']['ruta'])
                                <x-laboratorio-foto class="lab-extra-photo" :clave="'bloque-'.$bloque['id']" :foto="$bloque['foto']" />
                            @endif
                            @if(filled($bloque['titulo']))<h3 data-lab-element="texto-bloque-{{ $bloque['id'] }}-titulo">{{ $bloque['titulo'] }}</h3>@endif
                            @if(filled($bloque['texto']))<x-laboratorio-texto :clave="'bloque-'.$bloque['id'].'-texto'" :texto="$bloque['texto']" />@endif
                        </article>
                    @endforeach
                </section>
            @endif
            <section class="lab-volunteers" aria-labelledby="volunteers-title">
                <x-laboratorio-foto class="lab-volunteers-photo" clave="colaboracion" :foto="$pagina['fotos']['colaboracion']" />
                <div class="lab-volunteers-info">
                    <h3 id="volunteers-title" data-lab-element="texto-colaboracion_titulo">{{ $t['colaboracion_titulo'] }}</h3>
                    <span class="lab-rule" aria-hidden="true"></span>
                    <x-laboratorio-texto clave="colaboracion_texto" :texto="$t['colaboracion_texto']" />
                </div>
                <div class="lab-contact">
                    <h3 data-lab-element="texto-contacto_titulo">{{ $t['contacto_titulo'] }}</h3>
                    <a data-lab-element="texto-contacto_accion" href="mailto:{{ $t['contacto_email'] }}">{{ $t['contacto_accion'] }}</a>
                </div>
            </section>
        </section>
        @endif
    </article>
@endcomponent
