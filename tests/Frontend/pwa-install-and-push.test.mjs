import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import {crearInstaladorPwa} from '../../resources/js/pwa-install.js';

test('instalar usa el diálogo nativo y explica la alternativa de iOS', async () => {
    const listeners = {}; let prompts = 0;
    const entorno = {navigator: {userAgent: 'Desktop'}, matchMedia: () => ({matches: false}),
        Event, dispatchEvent() {}, addEventListener: (nombre, fn) => listeners[nombre] = fn};
    const instalador = crearInstaladorPwa(entorno);
    listeners.beforeinstallprompt({preventDefault() {}, async prompt() {prompts++;}, userChoice: Promise.resolve({outcome: 'accepted'})});
    assert.match(await instalador.instalar(), /aceptada/); assert.equal(prompts, 1);
    listeners.appinstalled(); assert.equal(instalador.instalada, true);
    assert.match(await instalador.instalar(), /ya está instalado/); assert.equal(prompts, 1);
    entorno.navigator.userAgent = 'iPhone';
    assert.match(await crearInstaladorPwa(entorno).instalar(), /pantalla de inicio/);
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
