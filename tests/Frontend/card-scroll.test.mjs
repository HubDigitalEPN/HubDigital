import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const source = readFileSync(new URL('../../public/js/card-scroll.js', import.meta.url), 'utf8');
function scroll(request) {
    let intersect, disconnected = 0;
    const window = {IntersectionObserver: class {constructor(fn) {intersect=fn;} observe() {} disconnect() {disconnected++;}}};
    vm.runInNewContext(source, {window, IntersectionObserver: window.IntersectionObserver, setTimeout, clearTimeout});
    const state = window.hubCardScroll('consulta-uno', 1);
    state.$el = {}; state.$wire = {cargarMasTarjetas: request}; state.init();
    return {state, enter: () => intersect([{isIntersecting:true}]), disconnected: () => disconnected};
}
test('el centinela realiza una sola petición aunque se intersecte varias veces', async () => {
    let finish, calls = [];
    const s = scroll((...args) => {calls.push(args);return new Promise(resolve => finish=resolve);});
    s.enter(); s.enter(); assert.equal(calls.length, 1); assert.deepEqual(calls[0], ['consulta-uno', 1]);
    finish(); await new Promise(resolve => setImmediate(resolve));
    s.enter(); assert.equal(calls.length, 1); assert.equal(s.state.error, false); s.state.destroy();
});
test('un error detiene la carga automática y permite un reintento explícito; desmontar libera el observador', async () => {
    let calls = 0;
    const s = scroll(async () => {calls++; if (calls===1) throw new Error('Sin conexión');});
    await s.state.load(); assert.equal(s.state.error, true); assert.equal(calls, 1);
    await s.state.load(); assert.equal(calls, 2); assert.equal(s.state.error, false);
    s.state.destroy(); await s.state.load(); assert.equal(calls, 2); assert.ok(s.disconnected()>=2);
});
