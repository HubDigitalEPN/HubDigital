import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import {crearInstaladorPwa} from '../../resources/js/pwa-install.js';

function navegador(instalada = false) {
    const listeners = new Map();
    return {navigator: {}, matchMedia: () => ({matches: instalada}), Event,
        addEventListener(nombre, fn) { const handlers = listeners.get(nombre) ?? []; handlers.push(fn); listeners.set(nombre, handlers); },
        dispatchEvent(evento) { for (const fn of listeners.get(evento.type) ?? []) fn(evento); }};
}

function oferta(entorno, prompt, outcome = 'accepted') {
    const evento = new Event('beforeinstallprompt', {cancelable: true});
    evento.prompt = prompt; evento.userChoice = Promise.resolve({outcome});
    entorno.dispatchEvent(evento); return evento;
}

test('instalar invoca el diálogo nativo dentro del clic, una sola vez y sin diálogo de ayuda', async () => {
    const entorno = navegador(); let prompts = 0;
    const instalador = crearInstaladorPwa(entorno);
    assert.equal(instalador.disponible, false);
    const evento = oferta(entorno, async () => {prompts++;});
    assert.equal(evento.defaultPrevented, true); assert.equal(instalador.disponible, true);
    const solicitud = instalador.instalar();
    assert.equal(prompts, 1); // Antes de cualquier await: conserva la activación del usuario.
    assert.equal(instalador.disponible, false);
    assert.equal(await solicitud, ''); assert.equal(await instalador.instalar(), '');
    assert.equal(prompts, 1); assert.equal(instalador.instalada, false);
    entorno.dispatchEvent(new Event('appinstalled'));
    assert.equal(instalador.instalada, true); assert.equal(instalador.disponible, false);
});

test('el evento nativo recibido antes de Vite queda disponible para todos los botones', async () => {
    const entorno = navegador(); let prompts = 0;
    const head = readFileSync(new URL('../../resources/views/partials/head.blade.php', import.meta.url), 'utf8');
    const bootstrap = head.match(/<script id="hub-pwa-install-bootstrap"[^>]*>([\s\S]*?)<\/script>/)[1];
    vm.runInNewContext(bootstrap, {window: entorno, Event});
    oferta(entorno, async () => {prompts++;});
    const botones = Array.from({length: 3}, () => crearInstaladorPwa(entorno));
    assert.ok(botones.every(b => b.disponible));
    await botones[1].instalar(); assert.equal(prompts, 1);
    assert.ok(botones.every(b => !b.disponible));
    entorno.dispatchEvent(new Event('appinstalled'));
    assert.ok(botones.every(b => b.instalada));
});

test('sin oferta nativa o con PWA instalada no se ofrece un botón de instalación', async () => {
    for (const instalada of [false, true]) {
        const instalador = crearInstaladorPwa(navegador(instalada));
        assert.equal(instalador.disponible, false); assert.equal(await instalador.instalar(), '');
    }
});

test('cancelar consume la oferta y una oferta posterior permite instalar sin reutilizarla', async () => {
    const entorno = navegador(); let prompts = 0;
    const instalador = crearInstaladorPwa(entorno);
    oferta(entorno, async () => {prompts++;}, 'dismissed');
    assert.equal(await instalador.instalar(), ''); assert.equal(instalador.instalada, false);
    oferta(entorno, async () => {prompts++;});
    await instalador.instalar(); assert.equal(prompts, 2);
});

test('un fallo del instalador nativo no se anuncia como instalación completada', async () => {
    const entorno = navegador(); const instalador = crearInstaladorPwa(entorno);
    oferta(entorno, () => {throw new Error('rechazado');});
    assert.match(await instalador.instalar(), /no pudo abrir/);
    assert.equal(instalador.instalada, false); assert.equal(instalador.disponible, false);
});

function notificaciones(guardar) {
    const avisos = [], estados = [];
    const subscription = {endpoint: 'https://push.example.test/qa', toJSON: () => ({keys: {p256dh: 'sintetica', auth: 'sintetica'}})};
    const sw = {pushManager: {async getSubscription() {return subscription;}}};
    const window = {isSecureContext: true, Notification: {}, PushManager: {},
        atob: value => Buffer.from(value, 'base64').toString('binary'),
        dispatchEvent: event => estados.push(event.detail)};
    const contexto = {window, navigator: {serviceWorker: {async register() {}, ready: Promise.resolve(sw), addEventListener() {}}},
        Notification: {requestPermission: async () => 'granted'}, PushManager: {}, AbortSignal,
        document: {querySelector: () => null, addEventListener() {}}, setTimeout, clearTimeout,
        CustomEvent: class {constructor(type, options) {this.type = type; this.detail = options.detail;}},
        fetch: async url => url.endsWith('/configuracion') ? {ok: true, json: async () => ({enabled: true, publicKey: 'AQID'})} : guardar(),
        showNotification: aviso => avisos.push(aviso)};
    const source = readFileSync(new URL('../../resources/js/pwa-notifications.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/, '');
    vm.runInNewContext(source, contexto);
    return {enable: window.hubPwaNotifications.enable, avisos, estados};
}

test('la confirmación de notificaciones aparece solo después de guardar la suscripción', async () => {
    let finalizar, enviados = 0;
    const guardado = new Promise(resolve => finalizar = resolve);
    const n = notificaciones(() => {enviados++; return guardado;});
    const pendiente = n.enable();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(enviados, 1); assert.equal(n.avisos.length, 0);
    finalizar({ok: true, json: async () => ({ok: true})}); await pendiente;
    assert.equal(n.avisos.length, 1); assert.equal(n.avisos[0].variant, 'success');
    assert.equal(n.avisos[0].title, 'Notificaciones activadas');
    assert.equal(n.estados.at(-1).status, 'enabled');
    assert.match(n.estados.at(-1).message, /correctamente/);
});

test('si falla el guardado de push no se muestra una confirmación de éxito', async () => {
    const n = notificaciones(async () => ({ok: false, status: 500}));
    await n.enable(); assert.equal(n.avisos.length, 0); assert.equal(n.estados.at(-1).status, 'error');
});

test('web push se muestra con portal activo otra web o sin ventanas y no añade botones', async () => {
    for (const ventanas of [[], [{url: 'https://otra-web.test', focused: true, visibilityState: 'visible'}],
        [{url: 'https://labinvepn.org/dashboard', focused: true, visibilityState: 'visible', postMessage() {}}]]) {
        const listeners = {}; const mostradas = [];
        const clientes = {async matchAll() {return ventanas;}};
        const self = {location: {origin: 'https://labinvepn.org'}, clients: clientes,
            addEventListener: (nombre, fn) => listeners[nombre] = fn,
            registration: {async showNotification(...datos) {mostradas.push(datos);}}};
        vm.runInNewContext(readFileSync(new URL('../../public/service-worker.js', import.meta.url), 'utf8'),
            {self, clients: clientes, importScripts() {}, URL});
        let pendiente;
        listeners.push({data: {json: () => ({title: 'HubDigital · Nueva solicitud', body: 'Revisión pendiente.',
            actions: [{action: 'delete', title: 'Delete'}], data: {notificationId: 'evento-1', url: '/dashboard'}})}, waitUntil: p => pendiente = p});
        await pendiente;
        assert.equal(mostradas.length, 1); assert.equal(mostradas[0][1].actions.length, 0);
        assert.equal(mostradas[0][1].body, 'Revisión pendiente.');
        assert.equal(mostradas[0][1].data.url, '/dashboard');
    }
});
