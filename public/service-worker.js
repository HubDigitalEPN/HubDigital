const OFFLINE_CACHE = 'hubdigital-public-offline-v1';
const OFFLINE_URL = '/offline.html';
importScripts('/pwa-cache.js?v=5');

function readPushPayload(data) {
    if (!data) return {};

    try {
        return data.json();
    } catch {
        return { body: data.text() };
    }
}

self.addEventListener('install', (event) => event.waitUntil(
    caches.open(OFFLINE_CACHE)
        .then((cache) => cache.add(OFFLINE_URL))
        .then(() => self.skipWaiting()),
));
self.addEventListener('activate', (event) => event.waitUntil(
    caches.keys()
        .then((keys) => Promise.all(keys
            .filter((key) => key.startsWith('hubdigital-public-offline-') && key !== OFFLINE_CACHE)
            .map((key) => caches.delete(key))))
        .then(() => portalPwa.activate())
        .then(() => self.clients.claim()),
));

self.addEventListener('push', (event) => {
    const payload = readPushPayload(event.data);
    const notificationId = payload.notificationId ?? payload.data?.notificationId ?? payload.id ?? null;
    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        // Con una sesión abierta la campana durable entrega el aviso inferior;
        // así no se duplica una alerta nativa por el mismo evento.
        const curadorActivo = windows.find((client) => client.focused
            && client.visibilityState === 'visible'
            && new URL(client.url).origin === self.location.origin
            && /^\/(curador|prestamos|dashboard)(?:\/|$)/.test(new URL(client.url).pathname));
        if (curadorActivo) {
            curadorActivo.postMessage({ type: 'hubdigital-push-pendiente', notificationId });
            return undefined;
        }

        return self.registration.showNotification(payload.title ?? 'HubDigital', {
            body: payload.body ?? 'Tienes una nueva notificación.',
            icon: payload.icon ?? '/images/hub-icon.png',
            badge: payload.badge ?? '/images/hub-icon.png',
            tag: payload.tag ?? (notificationId ? `hubdigital-${notificationId}` : 'hubdigital'),
            renotify: false,
            actions: payload.actions ?? [{ action: 'open', title: 'Abrir expediente' }],
            data: { notificationId, url: payload.data?.url ?? payload.url ?? '/dashboard' },
        });
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const destino = new URL(event.notification.data?.url ?? '/dashboard', self.location.origin);
    const url = destino.origin === self.location.origin
        ? destino.href
        : new URL('/dashboard', self.location.origin).href;
    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        const existing = windows.find((client) => new URL(client.url).origin === destino.origin);
        if (!existing) return clients.openWindow(url);

        return existing.navigate(url).then((client) => client.focus());
    }));
});

self.addEventListener('fetch', (event) => {
    if (new URL(event.request.url).origin !== self.location.origin) return;
    if (portalPwa.policy(event.request) === 'network' && event.request.mode !== 'navigate') return;
    event.respondWith(portalPwa.serve(event));
});
