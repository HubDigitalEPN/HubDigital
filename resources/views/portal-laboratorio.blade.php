@php($pagina = app(\App\Services\PaginaLaboratorio::class)->leer())
@php($t = $pagina['textos'])
@component('layouts.portal', ['title' => $t['titulo']])
    <article class="lab-page">
        <section class="lab-introduction" aria-labelledby="lab-title">
            <h1 id="lab-title">{{ $t['titulo'] }}</h1>
            <p>{{ $t['principal'] }}</p>
        </section>
        <section class="lab-personal" aria-labelledby="personal-title" data-lab-personal>
            <header class="lab-personal-hero">
                <div class="lab-personal-heading">
                    <span class="lab-rule" aria-hidden="true"></span>
                    <h2 id="personal-title"><strong>{{ $t['personal_destacado'] }}</strong> {{ $t['personal_titulo'] }}</h2>
                    <p>{{ $t['personal_descripcion'] }}</p>
                </div>
                <x-laboratorio-foto class="lab-personal-banner" clave="cabecera" :foto="$pagina['fotos']['cabecera']" />
            </header>
            <div class="lab-staff-grid">
                <section class="lab-curador" aria-labelledby="adrian-title">
                    <x-laboratorio-foto class="lab-curador-portrait" clave="adrian" :foto="$pagina['fotos']['adrian']" />
                    <div class="lab-curador-info">
                        <h3 id="adrian-title">{{ $t['adrian_nombre'] }}</h3>
                        <p class="lab-role">{{ $t['adrian_cargo'] }}</p>
                        <p class="lab-adrian-bio">{{ $t['adrian_bio'] }}</p>
                        <span class="lab-rule" aria-hidden="true"></span>
                        <p class="lab-adrian-specialty">{{ $t['adrian_especialidad'] }}</p>
                        <x-laboratorio-foto class="lab-ant" clave="hormiga" :foto="$pagina['fotos']['hormiga']" />
                    </div>
                </section>
                <div class="lab-right-column">
                    <section class="lab-vladimir" aria-labelledby="vladimir-title">
                        <div class="lab-vladimir-top">
                            <x-laboratorio-foto clave="vladimir" :foto="$pagina['fotos']['vladimir']" />
                            <div>
                                <h3 id="vladimir-title">{{ $t['vladimir_nombre'] }}</h3>
                                <p class="lab-role">{{ $t['vladimir_cargo'] }}</p>
                                <span class="lab-rule" aria-hidden="true"></span>
                                <p class="lab-vladimir-bio">{{ $t['vladimir_bio'] }}</p>
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
                            <h3 id="david-title">{{ $t['david_nombre'] }}</h3>
                            <p class="lab-role">{{ $t['david_cargo'] }}</p>
                            <span class="lab-rule" aria-hidden="true"></span>
                            <p class="lab-david-bio">{{ $t['david_bio'] }}</p>
                        </div>
                    </section>
                </div>
            </div>
            <section class="lab-volunteers" aria-labelledby="volunteers-title">
                <x-laboratorio-foto class="lab-volunteers-photo" clave="colaboracion" :foto="$pagina['fotos']['colaboracion']" />
                <div class="lab-volunteers-info">
                    <h3 id="volunteers-title">{{ $t['colaboracion_titulo'] }}</h3>
                    <span class="lab-rule" aria-hidden="true"></span>
                    <p>{{ $t['colaboracion_texto'] }}</p>
                </div>
                <div class="lab-contact">
                    <h3>{{ $t['contacto_titulo'] }}</h3>
                    <a href="mailto:{{ $t['contacto_email'] }}">{{ $t['contacto_accion'] }}</a>
                </div>
            </section>
        </section>
    </article>
@endcomponent
