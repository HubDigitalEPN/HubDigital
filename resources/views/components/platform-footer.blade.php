@php
    $identidad = app(\App\Support\InformacionRelease::class)->obtener();
    $versionCita = $identidad['version'] ?? 'desarrollo';
    $fechaCita = now('America/Guayaquil')->locale('es');
    $sitioCita = 'https://labinvepn.org';
@endphp
<footer class="platform-footer border-t border-border bg-surface px-4 py-5 text-sm text-text-secondary" aria-label="Citación, licencia y créditos de la plataforma">
    <div class="mx-auto max-w-7xl space-y-3 leading-relaxed">
        <p>© {{ $fechaCita->year }} Escuela Politécnica Nacional. Contenido propio bajo <a class="underline" href="https://creativecommons.org/licenses/by/4.0/deed.es" rel="license">Creative Commons Atribución 4.0 Internacional (CC BY 4.0)</a>. Se reservan los derechos sobre marcas y materiales de terceros según sus condiciones originales.</p>
        <details>
            <summary class="cursor-pointer font-semibold text-blue-navy">Cómo citar la plataforma y sus fotografías</summary>
            <div class="mt-3 space-y-3">
                <p><strong>Para citar la Plataforma de Gestión de Información y Base de Datos del Laboratorio de Invertebrados:</strong></p>
                <p>Plataforma de Gestión de Información y Base de Datos del Laboratorio de Invertebrados. Versión {{ $versionCita }}. Departamento de Biología, Facultad de Ciencias, Escuela Politécnica Nacional. Versión en línea en <a class="underline" href="{{ $sitioCita }}">{{ $sitioCita }}</a>. Fecha de consulta: {{ $fechaCita->translatedFormat('j \d\e F \d\e Y') }}.</p>
                <p>Fomentamos el uso de imágenes del laboratorio. En publicaciones impresas o digitales, recomendamos citar a la persona autora, el año, el código único del espécimen y el sitio de origen. Ejemplo: <em>Juan Pérez, 2026, MEPNINV00001, labinvepn.org</em>. Incluya el enlace a la licencia e indique cualquier modificación.</p>
                <p>En sitios web, identifique a la persona autora y la procedencia e incluya un enlace activo a la página de origen de la fotografía. Las imágenes externas conservan la licencia, autoría y fuente indicadas en su ficha; CC BY 4.0 no sustituye esas condiciones.</p>
            </div>
        </details>
        <details>
            <summary class="cursor-pointer font-semibold text-blue-navy">Créditos y financiamiento</summary>
            <p class="mt-3">La plataforma es una creación del Arq. de Software Hernán Troya y del Laboratorio de ADN, Facultad de Ingeniería en Sistemas, Escuela Politécnica Nacional, Quito, Ecuador. Créditos: Hernán Troya, Alejandro Alemán, Jimmy Valladares, Michael Trocellier, Miguel Mendoza y Paul Cajas. Comentarios y observaciones: Carlos Anchundia, PhD., y Pamela Flores, PhD. Comentarios de forma y diseño: Adrian Troya, PhD. Financiamiento: fondos del Laboratorio de Invertebrados.</p>
        </details>
        <div class="flex flex-wrap items-center justify-between gap-3 text-xs">
            <nav class="flex flex-wrap gap-4" aria-label="Información legal"><a class="underline" href="{{ route('portal.privacidad') }}">Política de privacidad</a><a class="underline" href="{{ route('portal.terminos') }}">Términos de uso</a></nav>
            <time datetime="{{ $fechaCita->toIso8601String() }}">{{ $fechaCita->translatedFormat('l j \d\e F \d\e Y, H:i') }} · Ecuador</time>
        </div>
    </div>
</footer>
