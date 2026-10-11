const SELECTOR = '[data-hub-notification-id]';
const TOAST_STORAGE_KEY = 'hubdigital:in-app-notifications-v3';
const MAX_REMEMBERED_NOTIFICATIONS = 80;
const CONFIG_URL = '/pwa/configuracion';
const SUBSCRIPTIONS_URL = '/pwa/suscripciones';
const TOAST_TRAY_ID = 'hub-in-app-toast-tray';
let observerInitialized = false;
const enMemoria = new Set();
const entregasPorComponente = new Map();

function emitStatus(status, message) {
    window.dispatchEvent(new CustomEvent('hub-pwa-status', { detail: { status, message } }));
}

async function registration() {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) return null;
    await navigator.serviceWorker.register('/service-worker.js', { scope: '/', updateViaCache: 'none' });
    return navigator.serviceWorker.ready;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function urlBase64ToUint8Array(value) {
    const padding = '='.repeat((4 - value.length % 4) % 4);
    const raw = window.atob((value + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map((character) => character.charCodeAt(0)));
}

async function jsonRequest(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), ...options.headers },
        ...options,
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json();
}

function presentarLote(componentId, lote, avisos) {
    const entrega = { componentId, lote, avisos, presentados: new Set(), confirmacion: null };
    entregasPorComponente.set(componentId, entrega);
    avisos.forEach((aviso) => {
        if (showInAppToast(aviso)) entrega.presentados.add(aviso.recordId);
    });
    if (entrega.presentados.size === 0) {
        avisos.forEach((aviso) => enMemoria.delete(aviso.recordId));
        entregasPorComponente.delete(componentId);
        return;
    }
    entrega.confirmacion = window.setTimeout(() => confirmarEntrega(entrega), 150);
}

function confirmarEntrega(entrega) {
    entrega.confirmacion = null;
    const ids = [...entrega.presentados];
    const component = entrega?.componentId && window.Livewire?.find ? window.Livewire.find(entrega.componentId) : null;
    if (!entrega?.lote || !component || ids.length === 0) {
        ids.forEach((id) => enMemoria.delete(id));
        entregasPorComponente.delete(entrega?.componentId);
        return;
    }
    component.call('confirmarEntrega', entrega.lote, ids).then(() => {
        entregasPorComponente.delete(entrega.componentId);
    }).catch(() => {
        ids.forEach((id) => enMemoria.delete(id));
        entregasPorComponente.delete(entrega.componentId);
    });
}

function showInAppToast(aviso) {
    const id = aviso?.eventId;
    if (!id || !aviso?.body || wasRemembered(TOAST_STORAGE_KEY, id)) return false;
    document.querySelector(`[data-hub-in-app-toast-id="${CSS.escape(id)}"]`)?.remove();
    const toast = document.createElement('aside');
    toast.dataset.hubInAppToastId = id;
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.className = 'hub-in-app-toast';
    const content = document.createElement('div');
    content.className = 'hub-in-app-toast__content';
    const title = document.createElement('p');
    title.className = 'hub-in-app-toast__title';
    title.textContent = aviso.title || 'HubDigital · Curaduría';
    const message = document.createElement('p');
    message.className = 'hub-in-app-toast__message';
    message.textContent = aviso.body;
    const action = document.createElement('button');
    action.type = 'button';
    action.className = 'hub-in-app-toast__action';
    action.textContent = aviso.action || 'Abrir expediente';
    action.addEventListener('click', () => navigateToNotification(safeSameOriginUrl(aviso.url || '/dashboard')));
    content.append(title, message, action);
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'hub-in-app-toast__close';
    close.setAttribute('aria-label', 'Cerrar aviso');
    close.textContent = '×';
    close.addEventListener('click', () => toast.remove());
    toast.append(content, close);
    toastTray().append(toast);
    remember(TOAST_STORAGE_KEY, id);
    window.setTimeout(() => toast.remove(), 12000);
    return true;
}

function toastTray() {
    let tray = document.getElementById(TOAST_TRAY_ID);
    if (tray) return tray;
    tray = document.createElement('div');
    tray.id = TOAST_TRAY_ID;
    tray.className = 'hub-in-app-toast-tray';
    tray.setAttribute('aria-label', 'Avisos operativos');
    document.body.append(tray);
    return tray;
}

function rememberedIds(key) {
    try {
        const saved = JSON.parse(localStorage.getItem(key) ?? '[]');
        return Array.isArray(saved) ? saved.filter((id) => typeof id === 'string') : [];
    } catch { return []; }
}

function wasRemembered(key, id) { return rememberedIds(key).includes(id); }

function remember(key, id) {
    try {
        const ids = rememberedIds(key).filter((saved) => saved !== id);
        ids.push(id);
        localStorage.setItem(key, JSON.stringify(ids.slice(-MAX_REMEMBERED_NOTIFICATIONS)));
    } catch { /* El almacenamiento local no debe bloquear la bandeja. */ }
}

function safeSameOriginUrl(value) {
    const destination = new URL(value, window.location.origin);
    return destination.origin === window.location.origin ? destination.href : `${window.location.origin}/dashboard`;
}

function navigateToNotification(url) {
    if (window.Livewire?.navigate) {
        window.Livewire.navigate(url);
        return;
    }
    window.location.assign(url);
}

async function status() {
    if (!('Notification' in window) || !('PushManager' in window) || !window.isSecureContext) {
        emitStatus('unsupported', 'Este navegador no admite avisos push seguros.');
        return;
    }
    const serviceWorker = await registration();
    const subscription = await serviceWorker?.pushManager.getSubscription();
    emitStatus(subscription ? 'enabled' : Notification.permission === 'denied' ? 'denied' : 'disabled', subscription ? 'Avisos activos en este dispositivo.' : null);
}

async function enable() {
    try {
        if (!('Notification' in window) || !('PushManager' in window) || !window.isSecureContext) {
            emitStatus('unsupported', 'Los avisos requieren HTTPS y un navegador compatible.');
            return;
        }
        if (await Notification.requestPermission() !== 'granted') {
            emitStatus('denied', 'El navegador no autorizó las notificaciones.');
            return;
        }
        const configuration = await jsonRequest(CONFIG_URL);
        if (!configuration.enabled || !configuration.publicKey) {
            emitStatus('unavailable', 'Push pendiente de configuración en este ambiente.');
            return;
        }
        const serviceWorker = await registration();
        let subscription = await serviceWorker.pushManager.getSubscription();
        if (!subscription) subscription = await serviceWorker.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(configuration.publicKey) });
        const payload = subscription.toJSON();
        await jsonRequest(SUBSCRIPTIONS_URL, { method: 'POST', body: JSON.stringify({ endpoint: subscription.endpoint, keys: payload.keys, contentEncoding: PushManager.supportedContentEncodings?.[0] ?? 'aes128gcm' }) });
        emitStatus('enabled', 'Avisos activos en este dispositivo.');
    } catch { emitStatus('error', 'No fue posible activar los avisos. Inténtalo nuevamente.'); }
}

async function disable() {
    try {
        const serviceWorker = await registration();
        const subscription = await serviceWorker?.pushManager.getSubscription();
        if (subscription) {
            await jsonRequest(SUBSCRIPTIONS_URL, { method: 'DELETE', body: JSON.stringify({ endpoint: subscription.endpoint }) });
            await subscription.unsubscribe();
        }
        emitStatus('disabled', 'Avisos desactivados en este dispositivo.');
    } catch { emitStatus('error', 'No fue posible desactivar los avisos.'); }
}

function descriptor(element) {
    return {
        eventId: element.dataset.hubNotificationId,
        recordId: element.dataset.hubNotificationRecordId,
        title: element.dataset.hubNotificationTitle,
        body: element.dataset.hubNotificationBody,
        url: element.dataset.hubNotificationUrl,
        action: element.dataset.hubNotificationAction,
    };
}

function scan() {
    const elementos = [...document.querySelectorAll(SELECTOR)].filter((element) => {
        const id = element.dataset.hubNotificationRecordId;
        if (!id || enMemoria.has(id)) return false;
        enMemoria.add(id);
        return true;
    }).slice(0, 3);
    if (elementos.length === 0) return;
    const componentId = elementos[0].closest('[wire\\:id]')?.getAttribute('wire:id');
    const avisos = elementos.map(descriptor).filter((aviso) => aviso.recordId && aviso.eventId && aviso.body);
    if (!componentId || !window.Livewire?.find || avisos.length === 0 || entregasPorComponente.has(componentId)) {
        avisos.forEach((aviso) => enMemoria.delete(aviso.recordId));
        return;
    }
    const component = window.Livewire.find(componentId);
    if (!component) {
        avisos.forEach((aviso) => enMemoria.delete(aviso.recordId));
        return;
    }
    component.call('registrarLoteEnviado', avisos.map((aviso) => aviso.recordId)).then((lote) => {
        if (!lote) {
            avisos.forEach((aviso) => enMemoria.delete(aviso.recordId));
            return;
        }
        presentarLote(componentId, lote, avisos);
    }).catch(() => avisos.forEach((aviso) => enMemoria.delete(aviso.recordId)));
}

function observe() {
    if (observerInitialized) return;
    observerInitialized = true;
    scan();
    new MutationObserver(scan).observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['data-hub-notification-id', 'data-hub-notification-record-id', 'data-hub-notification-body', 'data-hub-notification-url', 'data-hub-notification-action'] });
}

window.hubPwaNotifications = { enable, disable, status };
document.addEventListener('DOMContentLoaded', () => { observe(); status().catch(() => emitStatus('error', 'No fue posible consultar el estado de los avisos.')); }, { once: true });
document.addEventListener('livewire:navigated', observe);
navigator.serviceWorker?.addEventListener('message', (event) => {
    if (event.data?.type !== 'hubdigital-push-pendiente') return;
    const componentId = document.querySelector('[data-hub-notification-root]')?.closest('[wire\\:id]')?.getAttribute('wire:id');
    const component = componentId && window.Livewire?.find ? window.Livewire.find(componentId) : null;
    component?.call('refrescarEntregas').then(scan).catch(() => {});
});
