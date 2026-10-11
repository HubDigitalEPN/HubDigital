<div class="relative" style="min-height: calc(100dvh - 4.75rem);" aria-busy="true">
    <div class="collection-loading-indicator">
        <span class="atlas-spinner" aria-hidden="true"></span>
        <div>
            <p role="status" aria-live="polite">{{ \App\Support\PortalCopy::text('catalogo.cargando') }}</p>
            <noscript><p>{{ \App\Support\PortalCopy::text('catalogo.sin_javascript') }}</p></noscript>
        </div>
    </div>
</div>
