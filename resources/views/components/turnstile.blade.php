@if(config('services.turnstile.enabled'))
    <div class="hub-turnstile flex min-w-0 flex-col gap-2" aria-label="Verificación de seguridad">
        @if(filled(config('services.turnstile.site_key')))
            <div class="cf-turnstile min-h-[4.0625rem]" data-hub-turnstile
                data-sitekey="{{ config('services.turnstile.site_key') }}" data-action="turnstile-spin-v2"
                data-theme="auto" data-size="flexible" data-response-field="false" wire:ignore></div>
            <p data-turnstile-status class="hub-turnstile-status" role="status">Conectando con la verificación de seguridad…</p>
            <button type="button" data-turnstile-retry class="hub-turnstile-retry" hidden>Reintentar verificación</button>
        @else
            <p class="text-sm text-error" role="alert">La verificación de seguridad no está configurada. Solicita asistencia.</p>
        @endif
        @error('cf-turnstile-response')<p class="text-sm text-error" role="alert">{{ $message }}</p>@enderror
    </div>
@endif
