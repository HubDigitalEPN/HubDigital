@component('layouts.portal', ['title' => $titulo])
@php
    $identidad = app(\App\Support\InformacionRelease::class)->obtener();
    $fechaCita = now('America/Guayaquil')->locale('es');
    $sitioCita = 'https://labinvepn.org';
@endphp
<article data-platform-information-content class="portal-container mx-auto max-w-4xl space-y-5 px-5 py-10 leading-relaxed text-text-primary">
    <h1 class="font-display text-3xl font-bold text-blue-navy">{{ $titulo }}</h1>
    @if($seccion === 'licencia')
        <p>© {{ $fechaCita->year }} Escuela Politécnica Nacional. El contenido propio del laboratorio se publica bajo <a class="underline text-science-blue" href="https://creativecommons.org/licenses/by/4.0/deed.es" rel="license">Creative Commons Atribución 4.0 Internacional (CC BY 4.0)</a>.</p>
        <p>Conserve la atribución de autoría, enlace la licencia e indique cualquier modificación. Se reservan los derechos sobre marcas y materiales de terceros según sus condiciones originales. Las fotografías externas conservan la licencia, autoría y fuente indicadas en su ficha; CC BY 4.0 no sustituye esas condiciones.</p>
    @elseif($seccion === 'citar')
        <h2 class="text-xl font-semibold">Cómo citar la plataforma y sus fotografías</h2>
        <p>Plataforma de Gestión de Información y Base de Datos del Laboratorio de Invertebrados. Versión {{ $identidad['version'] ?? 'desarrollo' }}. Departamento de Biología, Facultad de Ciencias, Escuela Politécnica Nacional. Versión en línea en <a class="underline" href="{{ $sitioCita }}">{{ $sitioCita }}</a>. Fecha de consulta: {{ $fechaCita->translatedFormat('j \\d\\e F \\d\\e Y') }}.</p>
        <p>Fomentamos el uso de imágenes del laboratorio. En publicaciones impresas o digitales, recomendamos citar a la persona autora, el año, el código único del espécimen y el sitio de origen. Ejemplo: <em>Juan Pérez, 2026, MEPNINV00001, labinvepn.org</em>. Incluya el enlace a la licencia e indique cualquier modificación.</p>
        <p>En sitios web, identifique a la persona autora y la procedencia e incluya un enlace activo a la página de origen de la fotografía. Las imágenes externas conservan sus licencias y créditos originales.</p>
    @else
        <h2 class="text-xl font-semibold">Créditos y financiamiento</h2>
        <p>La plataforma es una creación del Arq. de Software Hernán Troya y del Laboratorio de ADN, Facultad de Ingeniería en Sistemas, Escuela Politécnica Nacional, Quito, Ecuador.</p>
        <p>Créditos: Hernán Troya, Alejandro Alemán, Jimmy Valladares, Michael Trocellier, Miguel Mendoza y Paul Cajas. Comentarios y observaciones: Carlos Anchundia, PhD., y Pamela Flores, PhD. Comentarios de forma y diseño: Adrian Troya, PhD. Financiamiento: fondos del Laboratorio de Invertebrados.</p>
    @endif
    <a data-platform-information-return class="inline-block underline text-science-blue" href="{{ route('portal.catalogo', ['vista' => 'mapa']) }}">Volver a la distribución de registros</a>
</article>
@endcomponent
