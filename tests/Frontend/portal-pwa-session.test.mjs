import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/portal-pwa.js', import.meta.url), 'utf8')
    .replace('export const portalSessionReady', 'const portalSessionReady');

function page({cached = false, warmHome = false, authenticated = false, failed = false} = {}) {
    let release;
    const network = new Promise(resolve => {release = resolve;});
    const requests = [], listeners = {}, serviceListeners = {}, messages = [], attributes = {}, scriptConfig = {csrf: 'anterior'};
    const serviceWorker = {controller: {postMessage(message) {messages.push(message);}}, addEventListener(name, callback) {serviceListeners[name] = callback;}};
    const statusMessage = {}, buttons = [];
    const status = {hidden: true, dataset: {}, querySelector() {return statusMessage;}, append(button) {buttons.push(button);}};
    let reloads = 0;
    const context = vm.createContext({AbortSignal,
        window: {isSecureContext: true, hubPortalCachedHome: cached, hubPortalWarmHome: warmHome,
            livewireScriptConfig: scriptConfig, addEventListener(name, callback) {listeners[name] = callback;}},
        navigator: {serviceWorker},
        document: {readyState: 'complete',
            querySelector() {return {setAttribute(name, value) {attributes[name] = value;}};},
            querySelectorAll() {return [{href: 'https://portal.test/build/assets/app-a.css'}];},
            getElementById() {return status;},
            createElement() {return {style: {}, addEventListener(name, callback) {this[name] = callback;}};},
        },
        location: {pathname: '/', search: '', reload() {reloads++;}},
        async fetch(url, options) {
            requests.push({url, options}); await network;
            if (failed) throw new Error('Sin conexión');
            return {ok: true, async json() {return {csrf: 'vigente', authenticated};}};
        },
    });
    const ready = vm.runInContext(source + '\nportalSessionReady;', context);
    return {ready, release, requests, messages, attributes, scriptConfig, listeners, serviceListeners, serviceWorker, status, statusMessage, buttons, reloads: () => reloads};
}

test('la portada de red conserva su token y no agrega una segunda petición de sesión', async () => {
    const p = page({warmHome: true}); await p.ready;
    assert.equal(p.requests.length, 0);
    assert.equal(p.messages[0].type, 'portal-cache-home');
    assert.deepEqual([...p.messages[0].assets], ['https://portal.test/build/assets/app-a.css']);
    assert.equal(p.scriptConfig.csrf, 'anterior');
});

test('la copia PWA espera el token vigente antes de permitir arrancar Livewire', async () => {
    const p = page({cached: true, warmHome: true}); let ready = false; p.ready.then(() => {ready = true;});
    await Promise.resolve(); assert.equal(ready, false);
    assert.equal(p.requests[0].url, '/portal/pwa/sesion');
    assert.equal(p.requests[0].options.cache, 'no-store');
    assert.equal(p.requests[0].options.credentials, 'same-origin');
    p.release(); await p.ready;
    assert.equal(p.attributes.content, 'vigente'); assert.equal(p.scriptConfig.csrf, 'vigente');
    assert.equal(p.messages.length, 0, 'Una reapertura no repite la precarga inicial.');
});

test('actualizar una PWA instalada vuelve a preparar Inicio con el controlador nuevo una sola vez', async () => {
    const p = page({warmHome: true}); await p.ready;
    assert.equal(p.messages.length, 1, 'El controlador previo recibe la precarga inicial.');
    p.serviceWorker.controller = {postMessage(message) {p.messages.push(message);}};
    p.serviceListeners.controllerchange();
    assert.equal(p.messages.length, 2, 'La versión nueva también recibe la precarga aunque la anterior la haya ignorado.');
    p.serviceListeners.controllerchange();
    assert.equal(p.messages.length, 2, 'No repetir la precarga para el mismo controlador.');
});

test('una cuenta autenticada recarga el documento vigente sin habilitar la copia anónima', async () => {
    const p = page({cached: true, authenticated: true}); let ready = false; p.ready.then(() => {ready = true;});
    p.release(); await new Promise(resolve => setImmediate(resolve));
    assert.equal(p.reloads(), 1); assert.equal(ready, false); assert.equal(p.scriptConfig.csrf, 'anterior');
});

test('sin conexión conserva la portada, no habilita un token anterior y permite reintentar sin Livewire', async () => {
    const p = page({cached: true, failed: true}); let ready = false; p.ready.then(() => {ready = true;});
    p.release(); await new Promise(resolve => setImmediate(resolve));
    assert.equal(ready, false); assert.equal(p.status.hidden, false);
    assert.match(p.statusMessage.textContent, /No se pudo conectar/);
    assert.equal(p.buttons[0].textContent, 'Reintentar');
    p.buttons[0].click(); assert.equal(p.reloads(), 1);
    p.listeners.online(); assert.equal(p.reloads(), 2);
});
