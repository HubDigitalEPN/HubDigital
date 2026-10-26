const TRAY_ID = 'hub-in-app-toast-tray';
export const NOTIFICATION_DURATION = 4000;
const rendered = new WeakMap();
const recent = new Map();
let scheduled = false;

export function showNotification({ message, title, variant = 'info', id, action, onAction } = {}) {
    const text = typeof message === 'string' ? message.trim() : '';
    if (!text) return null;
    const type = ({ danger: 'error', error: 'error', warning: 'warning', success: 'success' })[variant] || 'info';
    const key = `${type}:${text}`;
    if (!id && Date.now() - (recent.get(key) || 0) < 500) return null;
    recent.set(key, Date.now());
    for (const [previous, time] of recent) if (Date.now() - time > NOTIFICATION_DURATION) recent.delete(previous);
    let tray = document.getElementById(TRAY_ID);
    if (!tray) {
        tray = document.createElement('div');
        tray.id = TRAY_ID;
        tray.className = 'hub-in-app-toast-tray';
        tray.setAttribute('popover', 'manual');
        tray.setAttribute('aria-label', 'Notificaciones');
        document.body.append(tray);
    }
    if (id) tray.querySelector(`[data-hub-in-app-toast-id="${CSS.escape(id)}"]`)?.remove();
    const toast = document.createElement('aside');
    toast.className = 'hub-in-app-toast';
    toast.dataset.variant = type;
    if (id) toast.dataset.hubInAppToastId = id;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    toast.setAttribute('aria-atomic', 'true');
    const content = document.createElement('div');
    content.className = 'hub-in-app-toast__content';
    const heading = document.createElement('p');
    heading.className = 'hub-in-app-toast__title';
    heading.textContent = title || ({ error: 'Error', warning: 'Atención', success: 'Completado', info: 'Aviso' })[type];
    const body = document.createElement('p');
    body.className = 'hub-in-app-toast__message';
    body.textContent = text;
    content.append(heading, body);
    if (action && typeof onAction === 'function') {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'hub-in-app-toast__action';
        button.textContent = action;
        button.addEventListener('click', onAction);
        content.append(button);
    }
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'hub-in-app-toast__close';
    close.setAttribute('aria-label', 'Cerrar notificación');
    close.textContent = '×';
    let timer;
    const dismiss = () => { clearTimeout(timer); toast.remove(); };
    const start = () => { clearTimeout(timer); timer = window.setTimeout(dismiss, NOTIFICATION_DURATION); };
    close.addEventListener('click', dismiss);
    // Permite leer o usar una acción sin que desaparezca bajo el puntero o el foco.
    toast.addEventListener('mouseenter', () => clearTimeout(timer));
    toast.addEventListener('mouseleave', start);
    toast.addEventListener('focusin', () => clearTimeout(timer));
    toast.addEventListener('focusout', start);
    toast.append(content, close);
    tray.append(toast);
    if (typeof tray.showPopover === 'function') {
        if (tray.matches(':popover-open')) tray.hidePopover();
        tray.showPopover();
    }
    start();
    return toast;
}

function scanFeedback() {
    scheduled = false;
    const errors = [];
    document.querySelectorAll('[data-hub-feedback], [data-flux-error], [role="alert"]').forEach(source => {
        if (source.closest('.hub-in-app-toast, [data-hub-notification-root]')) return;
        // Las confirmaciones y los resultados con controles propios conservan sus acciones.
        if (source.querySelector('button, a, input, select, textarea, [role="button"]')) return;
        const message = (source.innerText || source.textContent || '').replace(/\s+/g, ' ').trim();
        if (rendered.has(source) && rendered.get(source) !== message) source.hidden = false;
        if (!message || !source.getClientRects().length || getComputedStyle(source).visibility === 'hidden') return;
        rendered.set(source, message);
        const variant = source.dataset.hubFeedback || (source.matches('[data-flux-error], [role="alert"]') ? 'error' : 'info');
        source.hidden = true;
        if (variant === 'error' || variant === 'danger') errors.push(message);
        else showNotification({ message, variant });
    });
    if (errors.length) showNotification({ message: [...new Set(errors)].join('\n'), variant: 'error' });
}

function scheduleFeedback() {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(scanFeedback);
}

export function bootNotifications(livewire) {
    window.hubNotify = showNotification;
    for (const event of ['show-toast', 'toast', 'domain-error']) {
        window.addEventListener(event, notice => {
            if (!notice.detail?.message && !notice.detail?.text) return;
            notice.stopImmediatePropagation();
            showNotification({ ...notice.detail, message: notice.detail.message || notice.detail.text, variant: event === 'domain-error' ? 'error' : notice.detail.variant || 'info' });
        }, true);
    }
    livewire?.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            preventDefault();
            const message = ({
                0: 'No se pudo conectar con el servidor.',
                403: 'No tienes permiso para realizar esta acción.',
                419: 'La sesión ha caducado. Recarga la página para continuar.',
                429: 'Hay demasiadas solicitudes. Inténtalo de nuevo en unos momentos.',
            })[status] || 'No se pudo completar la operación. Inténtalo de nuevo.';
            showNotification({ message, variant: 'error', ...(status === 419 ? { action: 'Recargar página', onAction: () => location.reload() } : {}) });
        });
    });
    document.addEventListener('livewire:navigated', scheduleFeedback);
    document.addEventListener('livewire:initialized', scheduleFeedback);
    const observe = () => {
        new MutationObserver(scheduleFeedback).observe(document.body, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['class', 'style', 'hidden', 'data-hub-feedback'] });
        scheduleFeedback();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', observe, { once: true });
    else observe();
}
