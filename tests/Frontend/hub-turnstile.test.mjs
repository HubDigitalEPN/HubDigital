import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';

const source = readFileSync(new URL('../../public/js/hub-turnstile.js', import.meta.url), 'utf8');
function fixture({registration = false, api = true, dark = false} = {}) {
    const events = [], listeners = new Map(), timers = new Map(), renders = [], removed = [];
    let next = 0;
    const field = {value: ''}, status = {textContent: ''};
    const retry = {hidden: true, dataset: {}, addEventListener(name, callback) { this[name] = callback; }};
    const form = {querySelector: () => field};
    const element = {
        isConnected: true, dataset: {sitekey: 'site-key', action: 'turnstile-spin-v2'},
        closest: selector => selector === 'form' || registration && selector === '#hub-register-form' ? form : null,
        parentElement: {querySelector: selector => selector.includes('status') ? status : retry},
        setAttribute() {},
    };
    const window = {dispatchEvent(event) {events.push(event.type);}};
    if (api) window.turnstile = {
        render(target, options) {assert.equal(target, element); renders.push(options); return 'widget-' + renders.length;},
        remove(id) {removed.push(id);},
    };
    const document = {documentElement: {classList: {contains: name => name === 'dark' && dark}},
        querySelectorAll: () => [element], querySelector: () => null,
        createElement: () => ({}), head: {append() {}}, addEventListener(name, callback) {listeners.set(name, callback);}};
    runInNewContext(source, {window, document,
        CustomEvent: class {constructor(type) {this.type = type;}},
        setTimeout(callback, delay) {const id = ++next; timers.set(id, {callback, delay}); return id;},
        clearTimeout(id) {timers.delete(id);},
    });
    return {window, element, field, status, retry, events, renders, removed, listeners,
        tick(delay) {for (const [id, timer] of [...timers]) if (timer.delay === delay) {timers.delete(id); timer.callback();}},
    };
}

test('el widget se crea una sola vez y transmite el token al POST correcto', () => {
    for (const registration of [false, true]) {
        const f = fixture({registration});
        f.window.hubTurnstileReady(); f.listeners.get('DOMContentLoaded')();
        assert.equal(f.renders.length, 1);
        assert.equal(f.renders[0]['response-field'], false);
        f.renders[0].callback('verified-token');
        assert.equal(f.field.value, 'verified-token');
        assert.equal(f.retry.hidden, true);
        assert.equal(f.events.at(-1), registration ? 'hub-register-turnstile-passed' : 'hub-turnstile-passed');
        f.tick(10000);
        assert.equal(f.field.value, 'verified-token');
    }
});

test('la verificación usa el tema de la aplicación sin recurrir al del sistema operativo', () => {
    for (const dark of [false, true]) {
        const f = fixture({dark});
        f.window.hubTurnstileReady();
        assert.equal(f.renders[0].theme, dark ? 'dark' : 'light');
    }
});

test('una verificación lenta ofrece reintento y nunca habilita el envío sin token', () => {
    const f = fixture(); f.window.hubTurnstileReady(); f.tick(10000);
    assert.match(f.status.textContent, /tardando/);
    assert.equal(f.retry.hidden, false);
    assert.equal(f.field.value, '');
    assert.equal(f.events.at(-1), 'hub-turnstile-reset');
    f.retry.click();
    assert.deepEqual(f.removed, ['widget-1']);
    assert.equal(f.renders.length, 2);
    f.renders[0].callback('obsolete-token');
    assert.equal(f.field.value, '');
    f.renders[1].callback('fresh-token');
    assert.equal(f.field.value, 'fresh-token');
});

test('caducidad error y timeout eliminan cualquier token anterior', () => {
    for (const callback of ['expired-callback', 'error-callback', 'timeout-callback']) {
        const f = fixture(); f.window.hubTurnstileReady(); f.renders[0].callback('token');
        f.renders[0][callback]();
        assert.equal(f.field.value, '');
        assert.equal(f.retry.hidden, false);
        assert.equal(f.events.at(-1), 'hub-turnstile-reset');
    }
});

test('una API bloqueada termina la espera con un mensaje y reintento', () => {
    const f = fixture({api: false}); f.window.hubTurnstileReady(); f.tick(12000);
    assert.match(f.status.textContent, /No se pudo cargar/);
    assert.equal(f.retry.hidden, false);
    assert.equal(f.field.value, '');
});

test('el reintento y el regreso al acceso vuelven a limitar la espera de una API bloqueada', () => {
    const f = fixture({api: false}); f.window.hubTurnstileReady(); f.tick(12000);
    f.retry.click();
    assert.equal(f.retry.hidden, true);
    f.tick(12000);
    assert.equal(f.retry.hidden, false);
    f.listeners.get('livewire:navigating')();
    f.listeners.get('livewire:navigated')();
    assert.equal(f.retry.hidden, true);
    f.tick(12000);
    assert.equal(f.retry.hidden, false);
    assert.equal(f.field.value, '');
});

test('la navegación limpia el widget y descarta callbacks de la página anterior', () => {
    const f = fixture(); f.window.hubTurnstileReady(); f.listeners.get('livewire:navigating')();
    assert.deepEqual(f.removed, ['widget-1']);
    f.renders[0].callback('obsolete-token');
    assert.equal(f.field.value, '');
    f.listeners.get('livewire:navigated')();
    assert.equal(f.renders.length, 2);
});
