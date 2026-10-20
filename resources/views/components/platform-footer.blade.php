<footer class="platform-footer border-t border-border bg-surface text-xs text-text-secondary" aria-label="Información de la plataforma">
    <nav class="platform-footer-links" aria-label="Información legal, citación y créditos">
        <a href="{{ route('portal.privacidad') }}" data-platform-information aria-haspopup="dialog" aria-controls="platform-information-dialog">Política de privacidad</a>
        <a href="{{ route('portal.terminos') }}" data-platform-information aria-haspopup="dialog" aria-controls="platform-information-dialog">Términos de uso</a>
        <a href="{{ route('portal.licencia') }}" data-platform-information aria-haspopup="dialog" aria-controls="platform-information-dialog">Licencia</a>
        <a href="{{ route('portal.citar') }}" data-platform-information aria-haspopup="dialog" aria-controls="platform-information-dialog">Cómo citar</a>
        <a href="{{ route('portal.creditos') }}" data-platform-information aria-haspopup="dialog" aria-controls="platform-information-dialog">Créditos</a>
    </nav>
    <dialog id="platform-information-dialog" class="platform-information-dialog" aria-labelledby="platform-information-title">
        <header class="platform-information-heading">
            <h2 id="platform-information-title">Información de la plataforma</h2>
            <button type="button" data-platform-information-close aria-label="Cerrar información" autofocus>×</button>
        </header>
        <div class="platform-information-content" role="region" tabindex="0" aria-label="Contenido de la información"></div>
    </dialog>
</footer>
