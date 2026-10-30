import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const logosPublicos = ['/images/logo-epn-lockup-v2.avif', '/images/logo-epn-v1.avif', '/images/hub-logo-v1.avif', '/images/logo-biologia-v1.avif', '/images/hub-brand-v1.svg', '/images/hub-icon.png', '/images/hub-icon-192.png', '/apple-touch-icon.png', '/favicon.svg', '/favicon.ico'];
const source = readFileSync(new URL('../../public/pwa-cache.js', import.meta.url), 'utf8');
function worker({deferWrites = false, stores = new Map()} = {}) {
    let releaseWrites; const writes = new Promise(resolve => {releaseWrites = resolve;});
    const requests = [], credentials = [], messages = [];
    let offline = false, body = 'original', revision = '1', permission = 'data', authenticated = false;
    let releaseNetwork;
    let networkGate;
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
            const response = new Response(body, {headers: {'X-Portal-Public-Cache': permission, 'X-Portal-Revision': revision,
                ...(authenticated ? {'X-Portal-Session': 'authenticated'} : {}),
            }});
            if (networkGate) await networkGate;
            return response;
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
        blockNetwork() {networkGate = new Promise(resolve => {releaseNetwork = resolve;});},
        releaseNetwork() {releaseNetwork?.(); networkGate = null;},
        state(values) {if ('offline' in values) offline = values.offline; if ('body' in values) body = values.body; if ('revision' in values) revision = values.revision; if ('permission' in values) permission = values.permission; if ('authenticated' in values) authenticated = values.authenticated;}};
}

async function prime(w, request) {const result = await w.serve(request); await result.background; return result;}

test('rutas públicas reciben su estrategia y R2, sesiones, cuentas y mutaciones quedan fuera del caché', () => {
    const w = worker();
    for (const path of ['/build/assets/app-a.js', '/build/assets/app-a.css', '/js/interactive-grid.js?v=123', '/js/lov-origen.js?v=123', '/css/platform-information.css?v=123', '/vendor/flux/flux.js', '/livewire-1234abcd/livewire.min.js', '/images/foto.webp', ...logosPublicos, '/portal/referencias-fotograficas/12/jpg.webp']) assert.equal(w.pwa.policy(w.req(path)), 'assets', path);
    assert.equal(w.pwa.policy(w.req('/')), 'home');
    for (const path of ['/portal/catalogo', '/portal/catalogo?vista=mapa']) assert.equal(w.pwa.policy(w.req(path)), 'pages');
    for (const path of ['/portal/explorador', '/portal/explorador?completo=1', '/portal/explorador/dibujo?vista=semicircular&escala=120']) assert.equal(w.pwa.policy(w.req(path)), 'initial-tree');
    for (const path of ['/portal/explorador/dibujo?vista=circular&escala=120', '/portal/explorador/dibujo?vista=semicircular&escala=120&rama=filo']) assert.equal(w.pwa.policy(w.req(path)), 'data');
    for (const path of ['/portal/explorador?padre=taxon', '/portal/explorador?busqueda=Arthro', '/portal/explorador?completo=1&taxon=uuid', '/portal/explorador?completo=1&conservar%5B%5D=filo', '/portal/filos?padre=taxon&busquedaTaxon=Arthro', '/portal/sugerencias?campo=filtroTaxon&texto=Arthro']) assert.equal(w.pwa.policy(w.req(path)), 'data');
    for (const path of ['/portal/imagenes/objeto', '/informacion/citar', '/portal/chat', '/portal/pwa/arranque.js', '/portal/pwa/sesion', '/administracion/textos-portal', '/login', '/portal/catalogo?vista=mapa&fti=uuid']) assert.equal(w.pwa.policy(w.req(path)), 'network');
    assert.equal(w.pwa.policy(w.req('/livewire-1234abcd/update', 'POST')), 'network');
    assert.equal(w.pwa.policy(new Request('https://r2.example/foto.webp')), 'network');
});

test('Cache First reutiliza los recursos y expira las entradas antiguas', async () => {
    const w = worker(), request = w.req('/build/assets/app-a.js');
    assert.equal(await (await prime(w, request)).response.text(), 'original');
    w.state({body: 'nuevo'});
    assert.equal(await (await w.serve(request)).response.text(), 'original'); assert.equal(w.requests.length, 1);
    const store = w.stores.get('hubdigital-public-v10-assets'), headers = new Headers(store.get(request.url).headers);
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
    const w = worker(), request = w.req('/portal/catalogo'); w.state({permission: 'document'});
    await prime(w, request); w.state({permission: '', body: 'cuenta privada'});
    const stale = await w.serve(request); await stale.background;
    assert.equal(w.stores.get('hubdigital-public-v10-pages').size, 0);
    w.state({offline: true}); assert.equal(await (await w.serve(request)).response.text(), 'offline');
});

test('el caché acota recursos y borra sólo sus versiones anteriores durante la activación', async () => {
    const w = worker();
    for (let i = 0; i < 162; i++) await prime(w, w.req('/images/foto-' + i + '.webp'));
    assert.equal(w.stores.get('hubdigital-public-v10-assets').size, 160);
    w.stores.set('hubdigital-public-v1-assets', new Map()); w.stores.set('otra-aplicacion', new Map()); w.stores.set('hubdigital-public-offline-v1', new Map());
    await w.pwa.activate();
    assert.equal(w.stores.has('hubdigital-public-v1-assets'), false); assert.equal(w.stores.has('otra-aplicacion'), true); assert.equal(w.stores.has('hubdigital-public-offline-v1'), true);
});


test('guardar un recurso lento no bloquea entregar ni consumir la respuesta original', {timeout: 2000}, async () => {
    const w = worker({deferWrites: true}), request = w.req('/build/assets/app-a.js');
    const result = await w.serve(request);
    assert.equal(await result.response.text(), 'original');
    assert.equal(w.stores.get('hubdigital-public-v10-assets').size, 0);
    w.releaseWrites(); await result.background;
    assert.equal(await (await w.serve(request)).response.text(), 'original');
    assert.equal(w.requests.length, 1, 'La copia guardada sigue siendo consumible después de entregar el body.');
});

test('reabrir Inicio después de cinco minutos entrega la copia sin esperar al servidor y renueva en segundo plano', {timeout: 2000}, async () => {
    const w = worker(), request = w.req('/'); w.state({permission: 'document', body: '<head><meta name="hub-public-shell" content="2"></head><body>portada</body>'});
    await prime(w, request);
    const store = w.stores.get('hubdigital-public-v10-home'), headers = new Headers(store.get(request.url).headers);
    headers.set('X-Portal-Cached-At', String(Date.now() - 86400000));
    store.set(request.url, new Response('<head><meta name="hub-public-shell" content="2"><script>window.hubPortalCachedHome=true;</script></head><body>portada</body>', {headers}));
    w.state({body: '<head><meta name="hub-public-shell" content="2"></head><body>portada actualizada</body>'}); w.blockNetwork();
    try {
        const cached = await w.serve(request);
        assert.match(await cached.response.text(), /hubPortalCachedHome=true.*portada/s);
        assert.equal(w.credentials.at(-1), 'omit', 'Renovar la portada pública no cambia las cookies de la sesión actual.');
        w.releaseNetwork(); await cached.background;
        assert.match(await (await w.serve(request)).response.text(), /portada actualizada/);
    } finally {w.releaseNetwork();}
});

test('el arranque prepara el primer documento y solo sus recursos públicos sin esperar una segunda visita', async () => {
    const w = worker(), waiting = []; w.state({permission: 'document', body: '<head><meta name="hub-public-shell" content="2"></head><body>Inicio</body>'});
    await w.pwa.warmHome({source: {url: 'https://portal.test/'}, data: {assets: [
        '/build/assets/app-a.js', '/images/portal-laboratorio-hero.jpg', '/portal/pwa/sesion', '/portal/imagenes/privada', 'https://externo.test/foto.webp',
    ]}, waitUntil(task) {waiting.push(task);}});
    await Promise.all(waiting);
    const primed = ['https://portal.test/', 'https://portal.test/build/assets/app-a.js', 'https://portal.test/images/portal-laboratorio-hero.jpg'];
    assert.deepEqual(w.requests, primed, 'La primera visita prepara únicamente el documento y sus recursos públicos.');
    w.state({offline: true});
    const cached = await w.serve(w.req('/')); await cached.background;
    assert.match(await cached.response.text(), /hubPortalCachedHome=true/);
    assert.equal(await (await w.serve(w.req('/build/assets/app-a.js'))).response.text(), '<head><meta name="hub-public-shell" content="2"></head><body>Inicio</body>');
    assert.deepEqual(w.requests, [...primed, 'https://portal.test/'], 'Reabrir intenta renovar solo el documento; los recursos preparados no vuelven a la red.');
});

test('reiniciar el service worker al reabrir el navegador conserva Inicio y sus recursos sin red', async () => {
    const initial = worker(); initial.state({permission: 'document', body: '<head><meta name="hub-public-shell" content="2"></head><body>Inicio guardado</body>'});
    await prime(initial, initial.req('/')); await prime(initial, initial.req('/build/assets/app-a.css'));
    const reopened = worker({stores: initial.stores}); reopened.state({offline: true});
    const home = await reopened.serve(reopened.req('/')); await home.background;
    assert.match(await home.response.text(), /hubPortalCachedHome=true.*Inicio guardado/s);
    assert.equal(await (await reopened.serve(reopened.req('/build/assets/app-a.css'))).response.text(), '<head><meta name="hub-public-shell" content="2"></head><body>Inicio guardado</body>');
});

test('login, logout y cambios de rol invalidan Inicio antes de enviar la petición sin borrar recursos', async () => {
    for (const [path, method] of [['/login', 'POST'], ['/logout', 'POST'], ['/register', 'POST'], ['/two-factor-challenge', 'POST'], ['/roles/activar/curador', 'GET']]) {
        const w = worker(); w.state({permission: 'document'});
        await prime(w, w.req('/')); await prime(w, w.req('/build/assets/app-a.js'));
        await prime(w, w.req(path, method));
        assert.equal(w.stores.get('hubdigital-public-v10-home').size, 0, path);
        assert.equal(w.stores.get('hubdigital-public-v10-assets').size, 1, path);
        w.state({authenticated: true, permission: '', body: 'cuenta actual'});
        assert.equal(await (await prime(w, w.req('/'))).response.text(), 'cuenta actual');
        assert.equal(w.stores.get('hubdigital-public-v10-home').size, 0);
    }
});

test('una sesión autenticada observada desde otra pestaña elimina el respaldo y el token nunca se cachea', async () => {
    const w = worker(); w.state({permission: 'document'}); await prime(w, w.req('/'));
    w.state({authenticated: true, permission: '', body: 'token vigente'});
    await prime(w, w.req('/portal/pwa/sesion'));
    assert.equal(w.stores.get('hubdigital-public-v10-home').size, 0);
    assert.ok([...w.stores.values()].every(store => [...store.keys()].every(url => !url.includes('/pwa/sesion'))));
});

test('una recarga explícita puede pedir Inicio vigente en lugar de la copia', async () => {
    const w = worker(); w.state({permission: 'document'}); await prime(w, w.req('/'));
    w.state({body: 'vigente'});
    assert.equal(await (await prime(w, w.req('/', 'GET', {'X-Portal-Revalidate': '1'}))).response.text(), 'vigente');
});

test('Inicio renueva el HTML antiguo del asistente antes de ofrecerlo y conserva el respaldo si no hay red', async () => {
    const w = worker(), request = w.req('/');
    w.state({permission:'document',body:'<head></head><body>asistente anterior</body>'});
    await prime(w,request); await prime(w,w.req('/build/assets/app-a.js'));
    w.state({body:'<head><meta name="hub-public-shell" content="2"></head><body>asistente vigente</body>'});
    const fresh=await prime(w,request);
    assert.match(await fresh.response.text(),/asistente vigente/);
    assert.equal(w.stores.get('hubdigital-public-v10-assets').size,1,'Renovar HTML no elimina recursos compatibles.');
    w.state({offline:true});
    assert.match(await (await w.serve(request)).response.text(),/asistente vigente/);
    const old=worker();old.state({permission:'document',body:'<head></head><body>respaldo anterior</body>'});
    await prime(old,old.req('/'));old.state({offline:true});
    assert.match(await (await old.serve(old.req('/'))).response.text(),/respaldo anterior/);
});

test('una actualización anónima en vuelo no restaura Inicio después de iniciar sesión', async () => {
    const w = worker(); w.state({permission: 'document', body: '<head><meta name="hub-public-shell" content="2"></head>'}); await prime(w, w.req('/'));
    w.blockNetwork(); const cached = await w.serve(w.req('/'));
    w.state({permission: '', authenticated: true});
    const login = w.serve(w.req('/login', 'POST'));
    w.releaseNetwork(); await login; await cached.background;
    assert.equal(w.stores.get('hubdigital-public-v10-home').size, 0);
});
