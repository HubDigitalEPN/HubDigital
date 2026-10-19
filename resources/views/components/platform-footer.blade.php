@php($fechaPie = now('America/Guayaquil')->locale('es'))
<footer class="platform-footer border-t border-border bg-surface px-4 py-3 text-xs text-text-secondary" aria-label="Información de la plataforma">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-x-6 gap-y-2">
        <nav class="flex flex-wrap gap-x-4 gap-y-2" aria-label="Información legal, citación y créditos">
            <a class="underline" href="{{ route('portal.privacidad') }}">Política de privacidad</a>
            <a class="underline" href="{{ route('portal.terminos') }}">Términos de uso</a>
            <a class="underline" href="{{ route('portal.licencia') }}">Licencia</a>
            <a class="underline" href="{{ route('portal.citar') }}">Cómo citar</a>
            <a class="underline" href="{{ route('portal.creditos') }}">Créditos</a>
        </nav>
        <time datetime="{{ $fechaPie->toIso8601String() }}">{{ $fechaPie->translatedFormat('l j \\d\\e F \\d\\e Y, H:i') }} · Ecuador</time>
    </div>
</footer>
