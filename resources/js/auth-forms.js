import { showNotification } from './hub-notifications';

export function authenticationDestination(data, { successUrl, twoFactorUrl } = {}, origin) {
    const target = data.two_factor ? twoFactorUrl : (data.redirect || successUrl);
    if (!target) return null;
    const url = new URL(target, origin);
    if (url.origin !== origin || !['http:', 'https:'].includes(url.protocol)) throw new Error('Destino de acceso no permitido.');
    return url.href;
}

export async function requestAuthentication(action, body, fetchRequest = fetch) {
    const response = await fetchRequest(action, {
        method: 'POST', body, credentials: 'same-origin', cache: 'no-store',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        const errors = Object.fromEntries(Object.entries(data.errors || {})
            .map(([field, messages]) => [field, (Array.isArray(messages) ? messages : [messages]).filter(value => typeof value === 'string')]));
        const message = Object.values(errors).flat().join('\n') || ({
            419: 'La sesión venció. Vuelve a abrir este formulario.',
            429: 'Has realizado muchos intentos. Espera un momento y vuelve a intentarlo.',
        })[response.status] || 'No se pudo completar la solicitud. Intenta nuevamente.';
        return { ok: false, errors, message };
    }
    // Un formulario anterior puede enviarse cuando la sesión ya está activa:
    // el middleware de invitados redirige entonces a una página HTML.
    // authenticationDestination sigue validando el origen antes de navegar.
    if (response.redirected && !data.redirect && response.url) {
        return { ok: true, data: { ...data, redirect: response.url } };
    }
    return { ok: true, data };
}

export function bootAuthForms(livewire) {
    const pending = new WeakSet();
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!form.matches?.('form[data-async-auth]')) return;
        event.preventDefault();
        if (pending.has(form)) return;
        pending.add(form);
        const buttons = [...form.querySelectorAll('button[type=submit]')].map(button => ({ button, disabled: button.disabled, text: button.textContent }));
        const feedback = form.querySelector('[data-auth-feedback]');
        const notify = (message, error = false) => {
            if (feedback) {
                feedback.hidden = false;
                feedback.textContent = message;
                feedback.setAttribute('role', error ? 'alert' : 'status');
            }
        };
        form.querySelectorAll('[data-auth-error]').forEach(error => error.remove());
        form.querySelectorAll('[aria-describedby]').forEach(input => {
            const ids = input.getAttribute('aria-describedby').split(/\s+/).filter(id => !id.startsWith('auth-error-')).join(' ');
            if (ids) input.setAttribute('aria-describedby', ids);
            else input.removeAttribute('aria-describedby');
        });
        form.querySelectorAll('[aria-invalid=true]').forEach(input => input.removeAttribute('aria-invalid'));
        form.setAttribute('aria-busy', 'true');
        for (const { button } of buttons) { button.disabled = true; button.textContent = 'Validando…'; }
        notify('Validando…');
        try {
            const result = await requestAuthentication(form.action, new FormData(form));
            if (!result.ok) {
                notify(result.message, true);
                for (const [name, messages] of Object.entries(result.errors)) {
                    const input = form.querySelector(`[name="${CSS.escape(name)}"]`);
                    if (!input || !messages.length) continue;
                    input.setAttribute('aria-invalid', 'true');
                    const description = document.createElement('span');
                    description.id = `auth-error-${name.replace(/[^a-z0-9_-]/gi, '-')}`;
                    description.dataset.authError = '';
                    description.className = 'sr-only';
                    description.textContent = messages.join(' ');
                    input.insertAdjacentElement('afterend', description);
                    input.setAttribute('aria-describedby', `${input.getAttribute('aria-describedby') || ''} ${description.id}`.trim());
                }
                form.querySelector('[aria-invalid=true]')?.focus();
                // Turnstile consume su token incluso con una contraseña incorrecta.
                if (form.querySelector('[name="cf-turnstile-response"]')) {
                    form.querySelector('[data-turnstile-retry]')?.click();
                }
                return;
            }
            if (result.data.csrf) {
                document.querySelector('meta[name=csrf-token]')?.setAttribute('content', result.data.csrf);
                if (window.livewireScriptConfig) window.livewireScriptConfig.csrf = result.data.csrf;
            }
            const destination = authenticationDestination(result.data, form.dataset, location.origin);
            if (destination) {
                if (result.data.message) document.addEventListener('livewire:navigated', () => {
                    showNotification({ message: result.data.message, variant: 'success' });
                }, { once: true });
                livewire.navigate(destination);
            } else notify(result.data.message || 'Solicitud completada.');
        } catch {
            notify('No se pudo conectar. Revisa tu conexión e intenta nuevamente.', true);
        } finally {
            // El valor sólo vive durante esta solicitud; tampoco queda escrito
            // cuando la validación falla o el usuario vuelve con Atrás.
            for (const input of form.querySelectorAll('input[data-manual-password]')) {
                input.value = '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
            pending.delete(form);
            form.removeAttribute('aria-busy');
            for (const { button, disabled, text } of buttons) { button.disabled = disabled; button.textContent = text; }
        }
    }, true);
}
