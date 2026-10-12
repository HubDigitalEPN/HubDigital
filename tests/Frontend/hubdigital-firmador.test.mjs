import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
import {File} from 'node:buffer';
import {webcrypto} from 'node:crypto';

const source = await readFile(new URL('../../resources/js/hubdigital-firmador.js', import.meta.url), 'utf8');
function signer(fetch, config = {}) {
    const events = [], credentials = {value: 'clave de prueba'}, certificate = {value: 'prueba.p12', files: [new File(['p12 temporal'], 'prueba.p12')]};
    const window = {setTimeout: () => 1, clearTimeout() {}, dispatchEvent: event => events.push(event), location: {href: 'https://dev.labinvepn.org/depositos/solicitud', reload() { throw new Error('No debe recargar si hay callback'); }}};
    const context = vm.createContext({window, document: {querySelector: selector => selector.startsWith('meta') ? {content: 'csrf'} : null}, fetch, FormData, AbortController, AbortSignal, URL, TypeError, crypto: webcrypto, CustomEvent: class {constructor(type, options) {this.type = type; this.detail = options.detail;}}});
    vm.runInContext(source, context);
    const state = window.hubDigitalFirmador({uploadUrl: '/firmar', documentVersion: 3, onSigned: async () => {}, ...config});
    state.$refs = {certificado: certificate, clave: credentials};
    state.$nextTick = callback => callback();
    return {state, credentials, certificate, events};
}
test('un 422 muestra el detalle, sale de Firmando y limpia las credenciales', async () => {
    const ui = signer(async () => ({ok: false, json: async () => ({message: 'Datos incorrectos', errors: {clave_certificado: ['Contraseña incorrecta']}})}));
    await ui.state.firmar();
    assert.equal(ui.state.estado, 'error');
    assert.equal(ui.state.error, 'Contraseña incorrecta');
    assert.equal(ui.state.progreso, '');
    assert.equal(ui.credentials.value, '');
    assert.equal(ui.certificate.value, '');
});
test('el éxito actualiza la solicitud inmediatamente y envía su versión', async () => {
    let notified = 0, submitted;
    const ui = signer(async (_url, request) => { submitted = request.body; return {ok: true, json: async () => ({message: 'Solicitud firmada'})}; }, {onSigned: async () => notified++});
    await ui.state.firmar();
    assert.equal(ui.state.estado, 'completado');
    assert.equal(ui.state.dialogo, false);
    assert.equal(notified, 1);
    assert.equal(submitted.get('documento_version'), '3');
    assert.equal(submitted.get('clave_certificado'), null);
    assert.equal(submitted.get('certificado'), null);
    assert.equal(ui.events[0].detail.message, 'Solicitud firmada');
});
test('una respuesta perdida recupera la firma persistida sin volver a firmar', async () => {
    let calls = 0, notified = 0;
    const ui = signer(async (url) => {
        calls++;
        if (String(url).includes('estado-firma')) return {ok: true, json: async () => ({firmada: true})};
        throw new TypeError('Conexión interrumpida');
    }, {statusUrl: '/estado-firma', onSigned: async () => notified++});
    await ui.state.firmar();
    assert.equal(calls, 2);
    assert.equal(ui.state.estado, 'completado');
    assert.equal(notified, 1);
    assert.equal(ui.credentials.value, '');
});
test('el token manda su PIN solo al conector local y el servidor recibe el PDF', async () => {
    let submitted, localPin;
    const ui = signer(async (url, request) => {
        const target = String(url);
        if (target.endsWith('/session')) return {ok: true, json: async () => ({token: 'sesion-efimera'})};
        if (target.endsWith('/certificates')) { localPin = request.headers['X-Token-Pin']; return {ok: true, json: async () => ({certificados: [{alias: 'firma', nombre: 'Firmante QA'}]})}; }
        if (target.endsWith('/original')) return {ok: true, headers: new Headers({'content-type': 'application/pdf'}), blob: async () => new Blob(['%PDF-original'])};
        if (target.endsWith('/sign')) return {ok: true, blob: async () => new Blob(['%PDF-firmado'])};
        submitted = request.body;
        return {ok: true, json: async () => ({message: 'Solicitud firmada'})};
    }, {originalUrl: '/original'});
    ui.state.metodo = 'token';
    await ui.state.firmar();
    assert.equal(ui.state.estado, 'completado');
    assert.equal(localPin, 'clave de prueba');
    assert.equal(submitted.get('clave_certificado'), null);
    assert.equal(submitted.get('certificado'), null);
    assert.equal(submitted.get('pdf_firmado').name, 'solicitud-token-firmada.pdf');
});
