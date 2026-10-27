/* Cache Storage contiene únicamente recursos y respuestas públicas autorizadas. */
const portalPwa = (() => {
    const VERSION = 'hubdigital-public-v10';
    const names = {assets: VERSION + '-assets', home: VERSION + '-home', pages: VERSION + '-pages', data: VERSION + '-data'};
    const limits = {assets: 160, home: 1, pages: 8, data: 48};
    const ages = {assets: 7 * 86400000, home: 7 * 86400000, pages: 300000, data: 120000};
    let homeEpoch = 0;
    let homeBlocked = false;
    const logos = ['/images/logo-epn-lockup-v2.avif', '/images/logo-epn-v1.avif', '/images/hub-logo-v1.avif', '/images/logo-biologia-v1.avif', '/images/hub-brand-v1.svg', '/images/hub-icon.png', '/images/hub-icon-192.png', '/apple-touch-icon.png', '/favicon.svg', '/favicon.ico'];

    function policy(request) {
        const url = new URL(request.url);
        if (url.origin !== self.location.origin || request.method !== 'GET') return 'network';
        if (url.pathname.startsWith('/portal/imagenes/')) return 'network';
        if (logos.includes(url.pathname)) return 'assets';
        const script = /\.(?:css|m?js)$/.test(url.pathname) && (
            /^\/(?:build|vendor|livewire(?:-[a-f0-9]{8})?|flux)\//.test(url.pathname)
            || /^\/(?:css|js)\//.test(url.pathname) && url.searchParams.has('v')
        );
        const photo = /^\/images\/.*\.(?:avif|webp|png|jpe?g|gif|svg)$/i.test(url.pathname)
            || /^\/portal\/referencias-fotograficas\/[0-9]+\/(?:jpg|jpeg|png)\.webp$/.test(url.pathname);
        if (script || photo) return 'assets';
        const map = url.pathname === '/portal/catalogo' && (!url.search || url.searchParams.size === 1 && url.searchParams.get('vista') === 'mapa');
        if (url.pathname === '/' && !url.search) return 'home';
        if (map) return 'pages';
        if (url.pathname === '/portal/explorador') {
            const inicial = !url.search || (url.searchParams.size === 1 && url.searchParams.get('completo') === '1');
            return inicial ? 'initial-tree' : 'data';
        }
        if (url.pathname === '/portal/explorador/dibujo') {
            const inicial = url.searchParams.size === 2 && url.searchParams.get('vista') === 'semicircular' && url.searchParams.get('escala') === '120';
            return inicial ? 'initial-tree' : 'data';
        }
        if (url.pathname === '/portal/sugerencias' || url.pathname === '/portal/filos') return 'data';
        return 'network';
    }

    async function read(request, group) {
        const cache = await caches.open(names[group]).catch(() => null);
        if (!cache) return null;
        const response = await cache.match(request);
        if (!response) return null;
        if (Date.now() - Number(response.headers.get('X-Portal-Cached-At') || 0) > ages[group]) {
            await cache.delete(request); return null;
        }
        return response;
    }

    async function invalidateHome() {
        homeEpoch++;
        homeBlocked = true;
        const cache = await caches.open(names.home);
        await cache.delete(new Request(new URL('/', self.location.origin)));
    }

    function changesSession(request) {
        const url = new URL(request.url);
        return url.origin === self.location.origin && /^\/(?:login|logout|register|two-factor-challenge|roles\/activar|instalacion\/administrador)(?:\/|$)/.test(url.pathname);
    }

    async function write(request, response, group, epoch = homeEpoch) {
        if (!response.ok || response.redirected || response.type === 'opaque') return;
        const permission = response.headers.get('X-Portal-Public-Cache');
        const cache = await caches.open(names[group]);
        if (((group === 'pages' || group === 'home') && permission !== 'document') || (group === 'data' && permission !== 'data')) {
            await cache.delete(request); return;
        }
        if (group === 'assets' && /no-store|private/i.test(response.headers.get('Cache-Control') || '')) return;
        const headers = new Headers(response.headers);
        headers.delete('Content-Encoding');
        headers.delete('Content-Length');
        headers.set('X-Portal-Cached-At', String(Date.now()));
        // La copia de Inicio siempre es anónima. Su token se renueva antes de
        // arrancar Livewire; ninguna conversación se incluye en este almacén.
        const body = group === 'home'
            ? (await response.clone().text()).replace(/<head>/i, '<head><script>window.hubPortalCachedHome=true;</script>')
            : await response.clone().arrayBuffer();
        if (group === 'home' && epoch !== homeEpoch) return;
        await cache.put(request, new Response(body, {status: response.status, statusText: response.statusText, headers}));
        if (group === 'home' && epoch !== homeEpoch) await cache.delete(request);
        else if (group === 'home') homeBlocked = false;
        const keys = await cache.keys();
        for (const key of keys.slice(0, Math.max(0, keys.length - limits[group]))) await cache.delete(key);
    }

    async function network(request, group, event) {
        const epoch = homeEpoch;
        const response = await fetch(request);
        if (response.headers.get('X-Portal-Session') === 'authenticated') await invalidateHome().catch(() => {});
        // La copia se inicia antes de devolver el body; Cache Storage nunca
        // retrasa la entrega de CSS, JavaScript, HTML ni datos al visitante.
        if (group) event.waitUntil(write(request, response.clone(), group, epoch).catch(() => {}));
        return response;
    }

    async function serve(event) {
        const request = event.request;
        const type = policy(request);
        if (changesSession(request)) await invalidateHome().catch(() => {});
        if (type === 'assets') return await read(request, 'assets').catch(() => null) || network(request, 'assets', event);
        if (type === 'home') {
            const cached = homeBlocked ? null : await read(request, 'home').catch(() => null);
            if (cached && !request.headers.has('X-Portal-Revalidate') && !['reload', 'no-store'].includes(request.cache)) {
                // Actualizar únicamente la copia pública: omitir cookies evita
                // crear dos sesiones rivales mientras /pwa/sesion renueva el token.
                event.waitUntil(network(new Request(request, {credentials: 'omit', cache: 'no-store'}), 'home', event).catch(() => {}));
                return cached;
            }
            try { return await network(request, 'home', event); }
            catch { return cached || caches.match('/offline.html'); }
        }
        if (type === 'pages') {
            // El documento cambia según la sesión. Tras login/logout nunca
            // entregar primero una versión con los permisos de otra sesión.
            try { return await network(request, 'pages', event); }
            catch { return await read(request, 'pages').catch(() => null) || caches.match('/offline.html'); }
        }
        if (type === 'initial-tree') {
            const group = 'data';
            const cached = await read(request, group).catch(() => null);
            const refresh = network(request, group, event).then(async response => {
                if (type === 'initial-tree' && cached && response.ok
                    && response.headers.get('X-Portal-Revision') !== cached.headers.get('X-Portal-Revision')) {
                    const windows = await clients.matchAll({type: 'window'});
                    windows.forEach(client => client.postMessage({type: 'portal-taxonomy-updated'}));
                }
                return response;
            });
            if (cached && !request.headers.has('X-Portal-Revalidate')) {
                event.waitUntil(refresh.catch(() => {})); return cached;
            }
            try { return await refresh; }
            catch { if (cached) return cached; if (type === 'pages') return caches.match('/offline.html'); throw new Error('Sin conexión'); }
        }
        if (type === 'data') {
            try { return await network(request, 'data', event); }
            catch { const cached = await read(request, 'data'); if (cached) return cached; throw new Error('Sin conexión'); }
        }
        if (request.mode === 'navigate') {
            try { return await network(request, null, event); } catch { return caches.match('/offline.html'); }
        }
        return network(request, null, event);
    }

    async function warmHome(event) {
        const url = new URL(event.source.url);
        if (url.origin !== self.location.origin || url.pathname !== '/' || url.search) return;
        const request = new Request(url, {credentials: 'same-origin'});
        if (homeBlocked || !await read(request, 'home')) await network(request, 'home', event);
        const paths = Array.isArray(event.data.assets) ? [...new Set(event.data.assets)].slice(0, 32) : [];
        await Promise.all(paths.map(async path => {
            try {
                const request = new Request(new URL(path, self.location.origin));
                if (policy(request) === 'assets' && !await read(request, 'assets')) {
                    await network(request, 'assets', event);
                }
            } catch { /* La precarga no bloquea el uso ni la instalación. */ }
        }));
    }

    async function precacheLogos() {
        await Promise.all(logos.map(async path => {
            const request = new Request(new URL(path, self.location.origin), {credentials: 'omit'});
            try {
                if (!await read(request, 'assets')) await write(request, await fetch(request), 'assets');
            } catch {
                // Una imagen temporalmente no disponible no bloquea actualizar
                // la PWA. Su siguiente lectura conserva Cache First con red.
            }
        }));
    }

    async function activate() {
        const keys = await caches.keys();
        await Promise.all(keys.filter(key => key.startsWith('hubdigital-public-v') && !Object.values(names).includes(key)).map(key => caches.delete(key)));
    }
    return {policy, serve, activate, precacheLogos, warmHome};
})();
