/* Cache Storage contiene únicamente recursos y respuestas públicas autorizadas. */
const portalPwa = (() => {
    const VERSION = 'hubdigital-public-v4';
    const names = {assets: VERSION + '-assets', pages: VERSION + '-pages', data: VERSION + '-data'};
    const limits = {assets: 160, pages: 8, data: 48};
    const ages = {assets: 7 * 86400000, pages: 300000, data: 120000};

    function policy(request) {
        const url = new URL(request.url);
        if (url.origin !== self.location.origin || request.method !== 'GET') return 'network';
        if (url.pathname.startsWith('/portal/imagenes/')) return 'network';
        const script = /\.(?:css|m?js)$/.test(url.pathname) && /^\/(?:build|vendor|livewire(?:-[a-f0-9]{8})?|flux)\//.test(url.pathname);
        const photo = /^\/images\/.*\.(?:avif|webp|png|jpe?g|gif|svg)$/i.test(url.pathname)
            || /^\/portal\/referencias-fotograficas\/[0-9]+\/(?:jpg|jpeg|png)\.webp$/.test(url.pathname);
        if (script || photo) return 'assets';
        const map = url.pathname === '/portal/catalogo' && url.searchParams.size === 1 && url.searchParams.get('vista') === 'mapa';
        if ((url.pathname === '/' && !url.search) || map) return 'pages';
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

    async function write(request, response, group) {
        if (!response.ok || response.redirected || response.type === 'opaque') return;
        const permission = response.headers.get('X-Portal-Public-Cache');
        const cache = await caches.open(names[group]);
        if ((group === 'pages' && permission !== 'document') || (group === 'data' && permission !== 'data')) {
            await cache.delete(request); return;
        }
        if (group === 'assets' && /no-store|private/i.test(response.headers.get('Cache-Control') || '')) return;
        const headers = new Headers(response.headers);
        headers.delete('Content-Encoding');
        headers.delete('Content-Length');
        headers.set('X-Portal-Cached-At', String(Date.now()));
        await cache.put(request, new Response(await response.clone().arrayBuffer(), {status: response.status, statusText: response.statusText, headers}));
        const keys = await cache.keys();
        for (const key of keys.slice(0, Math.max(0, keys.length - limits[group]))) await cache.delete(key);
    }

    async function network(request, group) {
        const response = await fetch(request);
        if (group) await write(request, response, group).catch(() => {});
        return response;
    }

    async function serve(event) {
        const request = event.request;
        const type = policy(request);
        if (type === 'assets') return await read(request, 'assets').catch(() => null) || network(request, 'assets');
        if (type === 'pages' || type === 'initial-tree') {
            const group = type === 'pages' ? 'pages' : 'data';
            const cached = await read(request, group).catch(() => null);
            const refresh = network(request, group).then(async response => {
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
            try { return await network(request, 'data'); }
            catch { const cached = await read(request, 'data'); if (cached) return cached; throw new Error('Sin conexión'); }
        }
        if (request.mode === 'navigate') {
            try { return await fetch(request); } catch { return caches.match('/offline.html'); }
        }
        return fetch(request);
    }

    async function activate() {
        const keys = await caches.keys();
        await Promise.all(keys.filter(key => key.startsWith('hubdigital-public-v') && !Object.values(names).includes(key)).map(key => caches.delete(key)));
    }
    return {policy, serve, activate};
})();
