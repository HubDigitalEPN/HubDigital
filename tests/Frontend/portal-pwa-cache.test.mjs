import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const logosPublicos = ['/images/logo-epn-lockup-v2.avif', '/images/logo-epn-v1.avif', '/images/hub-logo-v1.avif', '/images/logo-biologia-v1.avif', '/images/hub-brand-v1.svg', '/images/hub-icon.png', '/images/hub-icon-192.png', '/apple-touch-icon.png', '/favicon.svg', '/favicon.ico'];
const source = readFileSync(new URL('../../public/pwa-cache.js', import.meta.url), 'utf8');
function worker({deferWrites = false} = {}) {
    let releaseWrites; const writes = new Promise(resolve => {releaseWrites = resolve;});
    const stores = new Map(), requests = [], credentials = [], messages = [];
    let offline = false, body = 'original', revision = '1', permission = 'data';
    const caches = {
        async open(name) {
            if (!stores.has(name)) stores.set(name, new Map());
            const store = stores.get(name);
            return {
                async match(request) { return store.get(request.url)?.clone(); },
                async put(request, response) {if (deferWrites) await writes; store.set(request.url, response.clone());},
                async delete(request) {return store.delete(request.url);},
                async keys() {return [...store.keys()].map(url => new Request(url));},
            };
        },
        async keys() {return [...stores.keys()];},
        async delete(name) {return stores.delete(name);},
        async match() {return new Response('offline');},
    };
    const context = vm.createContext({URL, Headers, Request, Response, Date, caches,
        self: {location: {origin: 'https://portal.test'}},
        clients: {async matchAll() {return [{postMessage: message => messages.push(message)}];}},
        async fetch(request) {
            requests.push(request.url); credentials.push(request.credentials); if (offline) throw new Error('Sin conexión');
            return new Response(body, {headers: {'X-Portal-Public-Cache': permission, 'X-Portal-Revision': revision}});
        },
    });
    const pwa = vm.runInContext(source + '\nportalPwa;', context);
    const req = (path, method = 'GET', headers = {}) => new Request('https://portal.test' + path, {method, headers});
    async function serve(request) {
        const waiting = [];
        const response = await pwa.serve({request, waitUntil(task) {waiting.push(task);}});
        return {response, background: (async () => {while (waiting.length) await Promise.all(waiting.splice(0));})()};
    }
    return {pwa, req, serve, stores, requests, credentials, messages, releaseWrites,
        state(values) {if ('offline' in values) offline = values.offline; if ('body' in values) body = values.body; if ('revision' in values) revision = values.revision; if ('permission' in values) permission = values.permission;}};
}

async function prime(w, request) {const result = await w.serve(request); await result.background; return result;}

test('rutas públicas reciben su estrategia y R2, sesiones, cuentas y mutaciones quedan fuera del caché', () => {
    const w = worker();
    for (const path of ['/build/assets/app-a.js', '/build/assets/app-a.css', '/js/interactive-grid.js?v=123', '/js/lov-origen.js?v=123', '/css/platform-information.css?v=123', '/vendor/flux/flux.js', '/livewire-1234abcd/livewire.min.js', '/images/foto.webp', ...logosPublicos, '/portal/referencias-fotograficas/12/jpg.webp']) assert.equal(w.pwa.policy(w.req(path)), 'assets', path);
    for (const path of ['/', '/portal/catalogo', '/portal/catalogo?vista=mapa']) assert.equal(w.pwa.policy(w.req(path)), 'pages');
    for (const path of ['/portal/explorador', '/portal/explorador?completo=1', '/portal/explorador/dibujo?vista=semicircular&escala=120']) assert.equal(w.pwa.policy(w.req(path)), 'initial-tree');
    for (const path of ['/portal/explorador/dibujo?vista=circular&escala=120', '/portal/explorador/dibujo?vista=semicircular&escala=120&rama=filo']) assert.equal(w.pwa.policy(w.req(path)), 'data');
    for (const path of ['/portal/explorador?padre=taxon', '/portal/explorador?busqueda=Arthro', '/portal/explorador?completo=1&taxon=uuid', '/portal/explorador?completo=1&conservar%5B%5D=filo', '/portal/filos?padre=taxon&busquedaTaxon=Arthro', '/portal/sugerencias?campo=filtroTaxon&texto=Arthro']) assert.equal(w.pwa.policy(w.req(path)), 'data');
    for (const path of ['/portal/imagenes/objeto', '/informacion/citar', '/portal/chat', '/portal/pwa/arranque.js', '/administracion/textos-portal', '/login', '/portal/catalogo?vista=mapa&fti=uuid']) assert.equal(w.pwa.policy(w.req(path)), 'network');
    assert.equal(w.pwa.policy(w.req('/livewire-1234abcd/update', 'POST')), 'network');
    assert.equal(w.pwa.policy(new Request('https://r2.example/foto.webp')), 'network');
});

test('Cache First reutiliza los recursos y expira las entradas antiguas', async () => {
    const w = worker(), request = w.req('/build/assets/app-a.js');
    assert.equal(await (await prime(w, request)).response.text(), 'original');
    w.state({body: 'nuevo'});
    assert.equal(await (await w.serve(request)).response.text(), 'original'); assert.equal(w.requests.length, 1);
    const store = w.stores.get('hubdigital-public-v9-assets'), headers = new Headers(store.get(request.url).headers);
    headers.set('X-Portal-Cached-At', String(Date.now() - 8 * 86400000));
    store.set(request.url, new Response('caducado', {headers}));
    assert.equal(await (await w.serve(request)).response.text(), 'nuevo'); assert.equal(w.requests.length, 2);
});

test('los logos fijos se precargan sin credenciales y se reutilizan sin red incluso sin conexión', async () => {
    const w = worker();
    await w.pwa.precacheLogos();
    assert.deepEqual([...w.requests].sort(), logosPublicos.map(path => 'https://portal.test'+path).sort());
    assert.ok(w.credentials.every(value => value === 'omit'));
    w.state({offline: true});
    for (const path of logosPublicos) {
        assert.equal(await (await w.serve(w.req(path))).response.text(), 'original');
    }
    assert.equal(w.requests.length, logosPublicos.length, 'Cache First no hace una actualización de red de fondo.');
    await w.pwa.precacheLogos();
    assert.equal(w.requests.length, logosPublicos.length, 'La instalación también reutiliza logos ya guardados.');
});

test('una precarga fallida no bloquea la PWA y los logos se pueden recuperar después', async () => {
    const w = worker(); w.state({offline: true});
    await w.pwa.precacheLogos();
    w.state({offline: false});
    assert.equal(await (await prime(w, w.req('/images/logo-epn-v1.avif'))).response.text(), 'original');
});

test('los archivos de portada cambian con su versión y las conversaciones nunca se guardan', async () => {
    const w = worker();
    await prime(w, w.req('/css/platform-information.css?v=1'));
    w.state({body: 'estilos nuevos'});
    assert.equal(await (await prime(w, w.req('/css/platform-information.css?v=2'))).response.text(), 'estilos nuevos');
    assert.equal(await (await w.serve(w.req('/css/platform-information.css?v=1'))).response.text(), 'original');
    w.state({body: 'conversación privada', permission: ''});
    await prime(w, w.req('/livewire-1234abcd/update', 'POST'));
    assert.ok([...w.stores.values()].every(store => [...store.keys()].every(url => !url.includes('/update'))));
});

test('el documento consulta la sesión vigente y conserva sólo el respaldo público para desconexión', async () => {
    const w = worker(), request = w.req('/portal/catalogo?vista=mapa'); w.state({permission: 'document'});
    await prime(w, request); w.state({body: 'actualizado'});
    const stale = await w.serve(request);
    assert.equal(await stale.response.text(), 'actualizado'); await stale.background;
    w.state({offline: true}); assert.equal(await (await w.serve(request)).response.text(), 'actualizado');
});

test('la apertura del árbol usa SWR, comunica nueva revisión y permite forzar la revalidación', async () => {
    const w = worker(); await prime(w, w.req('/portal/explorador')); w.state({body: 'nuevo árbol', revision: '2'});
    const stale = await w.serve(w.req('/portal/explorador'));
    assert.equal(await stale.response.text(), 'original'); await stale.background;
    assert.equal(w.messages[0].type, 'portal-taxonomy-updated');
    w.state({body: 'último árbol', revision: '3'});
    const fresh = await w.serve(w.req('/portal/explorador', 'GET', {'X-Portal-Revalidate': '1'}));
    assert.equal(await fresh.response.text(), 'último árbol');
});

test('Network First consulta datos vigentes y sólo usa respaldo público ante fallo de conexión', async () => {
    const w = worker(), request = w.req('/portal/explorador?busqueda=Arthro');
    await prime(w, request); w.state({body: 'vigente'});
    assert.equal(await (await prime(w, request)).response.text(), 'vigente'); assert.equal(w.requests.length, 2);
    w.state({offline: true}); assert.equal(await (await w.serve(request)).response.text(), 'vigente');
});

test('una respuesta sin autorización pública elimina el documento previo y nunca guarda contenido privado', async () => {
    const w = worker(), request = w.req('/'); w.state({permission: 'document'});
    await prime(w, request); w.state({permission: '', body: 'cuenta privada'});
    const stale = await w.serve(request); await stale.background;
    assert.equal(w.stores.get('hubdigital-public-v9-pages').size, 0);
    w.state({offline: true}); assert.equal(await (await w.serve(request)).response.text(), 'offline');
});

test('el caché acota recursos y borra sólo sus versiones anteriores durante la activación', async () => {
    const w = worker();
    for (let i = 0; i < 162; i++) await prime(w, w.req('/images/foto-' + i + '.webp'));
    assert.equal(w.stores.get('hubdigital-public-v9-assets').size, 160);
    w.stores.set('hubdigital-public-v1-assets', new Map()); w.stores.set('otra-aplicacion', new Map()); w.stores.set('hubdigital-public-offline-v1', new Map());
    await w.pwa.activate();
    assert.equal(w.stores.has('hubdigital-public-v1-assets'), false); assert.equal(w.stores.has('otra-aplicacion'), true); assert.equal(w.stores.has('hubdigital-public-offline-v1'), true);
});


test('guardar un recurso lento no bloquea entregar ni consumir la respuesta original', {timeout: 2000}, async () => {
    const w = worker({deferWrites: true}), request = w.req('/build/assets/app-a.js');
    const result = await w.serve(request);
    assert.equal(await result.response.text(), 'original');
    assert.equal(w.stores.get('hubdigital-public-v9-assets').size, 0);
    w.releaseWrites(); await result.background;
    assert.equal(await (await w.serve(request)).response.text(), 'original');
    assert.equal(w.requests.length, 1, 'La copia guardada sigue siendo consumible después de entregar el body.');
});
