(() => {
    if (window.hubTurnstileInstalled) return;
    window.hubTurnstileInstalled = true;
    const widgets = new Map();
    const apiTimers = new Map();
    const eventName = (element, passed) => element.closest('#hub-register-form')
        ? `hub-register-turnstile-${passed ? 'passed' : 'reset'}`
        : `hub-turnstile-${passed ? 'passed' : 'reset'}`;
    function state(element, token = '', message = '', retry = false) {
        const form = element.closest('form');
        const field = form?.querySelector('[name="cf-turnstile-response"]');
        if (field) field.value = token;
        const status = element.parentElement.querySelector('[data-turnstile-status]');
        if (status) status.textContent = message;
        const button = element.parentElement.querySelector('[data-turnstile-retry]');
        if (button) button.hidden = !retry;
        element.setAttribute('aria-busy', token || retry ? 'false' : 'true');
        window.dispatchEvent(new CustomEvent(eventName(element, !!token)));
    }
    function render(element) {
        if (widgets.has(element)) return;
        if (!window.turnstile?.render) {
            if (!apiTimers.has(element)) {
                state(element, '', 'Conectando con la verificación de seguridad…');
                apiTimers.set(element, setTimeout(() => {
                    apiTimers.delete(element);
                    if (element.isConnected && !widgets.has(element)) state(element, '', 'No se pudo cargar la verificación. Revisa tu conexión y reintenta.', true);
                }, 12000));
            }
            return;
        }
        clearTimeout(apiTimers.get(element)); apiTimers.delete(element);
        state(element, '', 'Verificando la seguridad…');
        const entry = {id: null, timer: null};
        widgets.set(element, entry);
        entry.timer = setTimeout(() => state(element, '', 'La verificación está tardando más de lo habitual. Puedes reintentar.', true), 10000);
        const finish = (token, message, retry) => {
            if (widgets.get(element) !== entry || !element.isConnected) return;
            clearTimeout(entry.timer); state(element, token, message, retry);
        };
        try {
            entry.id = window.turnstile.render(element, {
                sitekey: element.dataset.sitekey, action: element.dataset.action,
                theme: element.dataset.theme || 'auto', size: 'flexible', 'response-field': false,
                callback: token => finish(token, 'Verificación completada.', false),
                'expired-callback': () => finish('', 'La verificación venció. Vuelve a verificar.', true),
                'error-callback': () => { finish('', 'No se pudo completar la verificación. Revisa tu conexión y reintenta.', true); return true; },
                'timeout-callback': () => finish('', 'Se agotó el tiempo de verificación. Vuelve a verificar.', true),
            });
        } catch {
            widgets.delete(element);
            clearTimeout(entry.timer);
            state(element, '', 'No se pudo iniciar la verificación. Reintenta.', true);
        }
    }
    const scan = () => {
        document.querySelectorAll('[data-hub-turnstile]').forEach(element => {
            render(element);
            const button = element.parentElement.querySelector('[data-turnstile-retry]');
            if (button && !button.dataset.bound) {
                button.dataset.bound = 'true';
                button.addEventListener('click', () => {
                    const entry = widgets.get(element);
                    if (entry) { clearTimeout(entry.timer); if (entry.id !== null) window.turnstile?.remove(entry.id); widgets.delete(element); }
                    if (window.turnstile?.render) render(element);
                    else {
                        render(element);
                        document.querySelector('script[src^="https://challenges.cloudflare.com/turnstile/v0/api.js"]')?.remove();
                        const script = document.createElement('script');
                        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=hubTurnstileReady';
                        script.async = true;
                        script.onerror = () => {
                            clearTimeout(apiTimers.get(element)); apiTimers.delete(element);
                            if (element.isConnected) state(element, '', 'No se pudo conectar. Revisa tu conexión y reintenta.', true);
                        };
                        document.head.append(script);
                    }
                });
            }
        });
    };
    window.hubTurnstileReady = scan;
    document.addEventListener('DOMContentLoaded', scan);
    document.addEventListener('livewire:navigated', scan);
    document.addEventListener('livewire:navigating', () => {
        for (const entry of widgets.values()) { clearTimeout(entry.timer); if (entry.id !== null) window.turnstile?.remove(entry.id); }
        widgets.clear();
        for (const timer of apiTimers.values()) clearTimeout(timer);
        apiTimers.clear();
    });
})();
