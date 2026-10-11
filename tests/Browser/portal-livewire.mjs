/** Puerta obligatoria de crear-paquete-oci: navegador real, assets compilados y Livewire real. */
import assert from 'node:assert/strict';
import {spawn, execFileSync} from 'node:child_process';
import {existsSync, mkdtempSync, rmSync, writeFileSync} from 'node:fs';
import {tmpdir} from 'node:os';
import path from 'node:path';
import net from 'node:net';
import {randomBytes} from 'node:crypto';
import {fileURLToPath} from 'node:url';

const proyecto = path.resolve(fileURLToPath(new URL('../..', import.meta.url)));
const php = process.argv[2];
assert.ok(php, 'Falta el ejecutable PHP del paquete.');
assert.equal(process.env.APP_ENV, 'testing', 'El navegador solo se ejecuta en testing.');
assert.equal(typeof WebSocket, 'function', 'Se necesita Node con WebSocket nativo (Node 22 o posterior).');
assert.ok(!existsSync(path.join(proyecto, 'public', 'hot')), 'Retira public/hot: el navegador debe comprobar el build que se empaqueta.');
// Neon ofrece el mismo protocolo CDP que utiliza esta puerta del paquete.
// El perfil temporal inferior aísla el fixture del perfil personal y del QA visual.
const navegador = path.join(process.env.LOCALAPPDATA || '', 'Programs/Opera Neon/opera.exe');
assert.ok(existsSync(navegador), 'Falta Opera Neon: no se omite la prueba del tooltip/Livewire.');
const temporal = mkdtempSync(path.join(tmpdir(), 'hubdigital-browser-'));
const fixture = path.join(temporal, 'fixture.json');
const token = randomBytes(8).toString('hex');
const procesos = [];
let conexion;
const excepciones = [], erroresLivewire = [], peticiones = new Set();
const demora = ms => new Promise(resolve => setTimeout(resolve, ms));
async function puertoLibre() {
    const servidor = net.createServer();
    await new Promise(resolve => servidor.listen(0, '127.0.0.1', resolve));
    const puerto = servidor.address().port;
    await new Promise(resolve => servidor.close(resolve));
    return puerto;
}
async function esperar(calcular, descripcion, limite = 60000) {
    const inicio = Date.now();
    while (Date.now() - inicio < limite) {
        const valor = await calcular();
        if (valor) return valor;
        await demora(50);
    }
    throw new Error(`No se completó: ${descripcion}`);
}
function abrirProceso(programa, args, opciones = {}) {
    const proceso = spawn(programa, args, {stdio: 'ignore', windowsHide: true, ...opciones});
    let error;
    proceso.on('error', fallo => { error = fallo; });
    procesos.push(proceso);
    return {proceso, comprobar() { if (error) throw error; assert.equal(proceso.exitCode, null, 'El servidor o navegador terminó antes de validar.'); }};
}
async function cdp(url) {
    const socket = new WebSocket(url);
    await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, {once: true}); socket.addEventListener('error', reject, {once: true}); });
    let secuencia = 0;
    const pendientes = new Map(), eventos = new Map();
    socket.addEventListener('message', evento => {
        const mensaje = JSON.parse(evento.data);
        if (mensaje.id) {
            const llamada = pendientes.get(mensaje.id);
            if (!llamada) return;
            clearTimeout(llamada.temporizador); pendientes.delete(mensaje.id);
            if (mensaje.error) llamada.reject(new Error(mensaje.error.message));
            else llamada.resolve(mensaje.result);
        } else for (const callback of eventos.get(mensaje.method) || []) callback(mensaje.params);
    });
    return {
        on(nombre, callback) { eventos.set(nombre, [...(eventos.get(nombre) || []), callback]); },
        send(method, params = {}) {
            const id = ++secuencia;
            return new Promise((resolve, reject) => {
                const temporizador = setTimeout(() => { pendientes.delete(id); reject(new Error(`Tiempo agotado en ${method}`)); }, 60000);
                pendientes.set(id, {resolve, reject, temporizador}); socket.send(JSON.stringify({id, method, params}));
            });
        },
        close() { socket.close(); for (const llamada of pendientes.values()) { clearTimeout(llamada.temporizador); llamada.reject(new Error('Navegador cerrado.')); } pendientes.clear(); },
    };
}

try {
    const puertoPhp = await puertoLibre(), puertoChrome = await puertoLibre(), puertoETE = await puertoLibre();
    const origen = `http://127.0.0.1:${puertoPhp}`;
    const entorno = {...process.env, PORTAL_ETE_URL: 'http://127.0.0.1:' + puertoETE, APP_URL: origen, APP_DEBUG: 'false', SESSION_DRIVER: 'file', SESSION_DOMAIN: '', SESSION_SECURE_COOKIE: 'false', CACHE_STORE: 'file',
        APP_CONFIG_CACHE: path.join(temporal, 'config.php'), APP_ROUTES_CACHE: path.join(temporal, 'routes.php')};
    const respuestaFixture = execFileSync(php, ['tests/Browser/portal-fixture.php', 'crear', token, fixture], {cwd: proyecto, env: entorno, encoding: 'utf8'});
    let datos;
    try { datos = JSON.parse(respuestaFixture); }
    catch (error) { throw new Error(`No se pudo preparar el fixture del navegador: ${respuestaFixture}`, {cause: error}); }
    const pythonETE = path.join(proyecto, '.local/ete-venv/Scripts/python.exe');
    assert.ok(existsSync(pythonETE), 'Falta el runtime ETE preparado por crear-paquete-oci.');
    const motorETE = abrirProceso(pythonETE, [path.join(proyecto, 'resources/python/portal_ete/server.py'), '--port', String(puertoETE)], {cwd: proyecto, env: {...entorno, QT_QPA_PLATFORM: 'offscreen'}});
    await esperar(async () => { motorETE.comprobar(); try { return (await fetch(`http://127.0.0.1:${puertoETE}/health`)).ok; } catch {return false;} }, 'Arranque de ETE');
    const servidor = abrirProceso(php, ['-S', `127.0.0.1:${puertoPhp}`, path.join(proyecto, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], {cwd: path.join(proyecto, 'public'), env: entorno});
    const chrome = abrirProceso(navegador, ['--headless=new', `--remote-debugging-port=${puertoChrome}`, '--remote-debugging-address=127.0.0.1', `--user-data-dir=${path.join(temporal, 'perfil')}`, '--no-first-run', '--no-default-browser-check', '--disable-gpu', 'about:blank']);
    const objetivos = await esperar(async () => {
        chrome.comprobar(); servidor.comprobar();
        try {
            const objetivos = await (await fetch(`http://127.0.0.1:${puertoChrome}/json/list`)).json();
            return objetivos.find(objetivo => objetivo.type === 'page') || null;
        } catch { return null; }
    }, 'arranque del navegador');
    conexion = await cdp(objetivos.webSocketDebuggerUrl);
    let totalPeticiones = 0;
    conexion.on('Runtime.exceptionThrown', ({exceptionDetails}) => excepciones.push(exceptionDetails.exception?.description || exceptionDetails.text));
    conexion.on('Runtime.consoleAPICalled', ({type, args}) => { if (type === 'error') excepciones.push(args.map(arg => arg.value ?? arg.description ?? '').join(' ')); });
    conexion.on('Network.requestWillBeSent', ({requestId, request}) => {
        if (request.method === 'POST' && request.url.startsWith(origen) && request.url.includes('livewire')) { peticiones.add(requestId); totalPeticiones++; }
    });
    conexion.on('Network.responseReceived', ({requestId, response}) => { if (peticiones.has(requestId) && response.status >= 400) erroresLivewire.push(response.status); });
    conexion.on('Network.loadingFinished', ({requestId}) => peticiones.delete(requestId));
    conexion.on('Network.loadingFailed', ({requestId, errorText}) => { if (peticiones.delete(requestId)) erroresLivewire.push(errorText); });
    await conexion.send('Page.enable'); await conexion.send('Runtime.enable'); await conexion.send('Network.enable');
    await conexion.send('Emulation.setDeviceMetricsOverride', {width: 1440, height: 900, deviceScaleFactor: 1, mobile: false});
    const evaluar = async expression => {
        const resultado = await conexion.send('Runtime.evaluate', {expression, returnByValue: true, awaitPromise: true});
        if (resultado.exceptionDetails) throw new Error(resultado.exceptionDetails.exception?.description || resultado.exceptionDetails.text);
        return resultado.result.value;
    };
    const visible = expresion => esperar(() => evaluar(expresion), expresion);
    const sinErrores = () => { assert.deepEqual(excepciones, [], 'Una excepción JavaScript interrumpió el recorrido.'); assert.deepEqual(erroresLivewire, [], 'Una actualización Livewire falló.'); };
    const reposo = async () => {
        await demora(300);
        // La respuesta de red puede terminar antes de que Livewire retire inert
        // o concluya el ajuste de filas iniciado por ResizeObserver.
        await esperar(async () => peticiones.size === 0 && await evaluar("!document.querySelector('.research-sidebar')?.inert && !document.querySelector('.collection-main')?.inert && !document.querySelector('.collection-workspace')?.classList.contains('is-updating') && [...document.querySelectorAll('.portal-records-viewport')].every(el => !window.Alpine?.$data(el)?.solicitando)"), 'finalización de Livewire y del ajuste de registros');
        sinErrores();
    };
    const url = `${origen}/portal/catalogo?vista=mapa&ft=${encodeURIComponent(datos.taxonBusqueda)}`;
    // El GET entrega inmediatamente el estado de carga; los agregados se resuelven después.
    const primera = await esperar(async () => { try { const r = await fetch(url); return r.ok && await r.text(); } catch { return false; } }, 'primera respuesta del portal');
    assert.match(primera, /Cargando los registros públicos y sus filtros/);
    await conexion.send('Page.navigate', {url});
    await visible("document.querySelector('.atlas-dashboard .atlas-map .leaflet-pane') && document.querySelector('.research-check-list input') && window.Livewire");
    await reposo();
    assert.match(await evaluar('window.location.search'), /vista=mapa/);
    assert.match(await evaluar("document.querySelector('.atlas-collection-count').textContent"), /18 registros/);
    // La provincia se selecciona directamente en el dashboard, sin buscador.
    assert.equal(await evaluar("document.querySelectorAll('.research-locality-dialog').length + [...document.querySelectorAll('.research-sidebar button')].filter(b => b.getAttribute('x-on:click') === 'abrirProvincias()').length"), 0);
    assert.equal(await evaluar("document.querySelectorAll('#buscar-provincia-panel,.atlas-chart-data-toggle').length"), 0);
    await evaluar("[...document.querySelectorAll('[aria-labelledby=\"titulo-riqueza\"] .atlas-ranked-row')].find(b=>b.textContent.includes('Orellana')).click()");
    await visible("document.querySelector('#titulo-riqueza')?.textContent === 'Registros por localidad'"); await reposo();
    assert.equal(await evaluar("document.activeElement?.classList.contains('atlas-territory-back')"), true);
    assert.match(await evaluar("document.querySelector('.atlas-collection-count').textContent"), /12 registros/);
    const peticionesAntesLocalidad = totalPeticiones;
    await evaluar("(() => {const input=document.querySelector('#buscar-localidad-panel'); input.value='browser uno'; input.dispatchEvent(new Event('input',{bubbles:true}));})()");
    await reposo(); assert.equal(totalPeticiones, peticionesAntesLocalidad);
    await evaluar("[...document.querySelectorAll('[aria-labelledby=\"titulo-riqueza\"] .atlas-ranked-row')].find(b=>b.textContent.includes('Lugar Browser Uno')).click()");
    await visible("document.querySelector('[aria-labelledby=\"titulo-riqueza\"] .atlas-ranked-row[aria-pressed=true]')"); await reposo();
    assert.ok((await evaluar('window.location.search')).includes('fg'));
    await evaluar("document.querySelector('.atlas-territory-back').click()");
    await visible("document.querySelector('#titulo-riqueza')?.textContent === 'Registros por provincia'"); await reposo();
    assert.equal(await evaluar("document.activeElement?.id"), 'titulo-riqueza');
    assert.match(await evaluar("document.querySelector('.atlas-collection-count').textContent"), /18 registros/);
    await evaluar("document.querySelector('[aria-labelledby=\"titulo-riqueza\"] .atlas-panel-tools > button').click(); [...document.querySelectorAll('#menu-riqueza button')].find(b => b.getAttribute('x-on:click') === 'alternarGrafico()').click()");
    await visible("window.HubDigitalChart.getChart(document.querySelector('[aria-labelledby=\"titulo-riqueza\"] canvas'))?.scales.y.ticks.some(t => t.label === 'Orellana')");
    assert.deepEqual(await evaluar("window.HubDigitalChart.getChart(document.querySelector('[aria-labelledby=\"titulo-riqueza\"] canvas')).scales.y.ticks.map(t=>t.label).sort()"), ['Orellana', 'Pichincha']);
    assert.equal(await evaluar("window.HubDigitalChart.getChart(document.querySelector('[aria-labelledby=\"titulo-riqueza\"] canvas')).scales.y.options.title.text"), 'Provincia');
    await evaluar("document.querySelector('[aria-labelledby=\"titulo-riqueza\"] .atlas-panel-tools > button').click(); [...document.querySelectorAll('#menu-riqueza button')].find(b => b.getAttribute('x-on:click') === 'alternarGrafico()').click()");

    // Ni escribir ni elegir una predicción filtra; Enter confirma el número de catálogo.
    const peticionesAntesTexto = totalPeticiones;
    await evaluar(`(() => {const input=document.querySelector('.research-search input'); input.focus(); input.value=${JSON.stringify(datos.codigos[17].toLowerCase())}; input.dispatchEvent(new Event('input',{bubbles:true}));})()`);
    await visible("document.querySelector('.research-search [role=listbox] [role=option]')?.textContent.length > 0");
    await evaluar("[...document.querySelectorAll('.research-search [role=option]')][0].dispatchEvent(new PointerEvent('pointerdown',{bubbles:true,cancelable:true}))");
    await reposo(); assert.equal(totalPeticiones, peticionesAntesTexto);
    assert.match(await evaluar("document.querySelector('.atlas-collection-count').textContent"), /18 registros/);
    await conexion.send('Input.dispatchKeyEvent', {type: 'keyDown', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13});
    await conexion.send('Input.dispatchKeyEvent', {type: 'keyUp', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13});
    await visible("document.querySelector('.atlas-collection-count')?.textContent.includes('1 registro)')"); await reposo();
    await evaluar("[...document.querySelectorAll('.collection-active-filters button')].find(b=>b.getAttribute('wire:click')?.includes('filtroCatalogo')).click()");
    await visible("document.querySelector('.atlas-collection-count')?.textContent.includes('18 registros')"); await reposo();
    await evaluar("document.querySelector('.atlas-map-actions .atlas-panel-tools > button').click()");
    await evaluar("[...document.querySelectorAll('.atlas-map-actions [role=menuitem]')].find(b => b.textContent.includes('Alternar agrupaciones')).click()");
    await visible("document.querySelectorAll('.atlas-map [aria-label^=\"Ubicación original\"]').length === 3");
    assert.deepEqual(await evaluar("[...document.querySelectorAll('.atlas-map-count-label strong')].map(n => Number(n.textContent)).sort((a,b) => a-b)"), [5, 12], 'Los registros que comparten una coordenada muestran su cantidad sin multiplicar ubicaciones.');
    async function mostrarTooltip() {
        await evaluar("document.querySelector('.atlas-map').scrollIntoView({block:'center'})");
        // Leaflet puede repintar al ajustar el tamaño. Elegir un marcador actual
        // y visible evita apuntar a un SVG retirado o cubierto por sus controles.
        await esperar(async () => {
            const rect = await evaluar(`(() => {for (const p of document.querySelectorAll('.atlas-map [aria-label^="Ubicación original"]')) {const r=p.getBoundingClientRect(); const x=r.x+r.width/2,y=r.y+r.height/2; const encima=document.elementFromPoint(x,y); if (encima === p || p.contains(encima)) return {x,y};} return null;})()`);
            if (!rect) return false;
            await conexion.send('Input.dispatchMouseEvent', {type: 'mouseMoved', x: 0, y: 0});
            await conexion.send('Input.dispatchMouseEvent', {type: 'mouseMoved', ...rect});
            return evaluar("document.querySelector('.atlas-floating-tooltip:not([hidden])')?.textContent.includes('Ubicación original')");
        }, 'hover real de un marcador visible y tooltip abierto');
    }
    // Hover abierto + morph real + bloqueo de filtros: reproduce la excepción original de x-ref/tooltip.
    await mostrarTooltip();
    await conexion.send('Network.emulateNetworkConditions', {offline: false, latency: 400, downloadThroughput: -1, uploadThroughput: -1});
    await evaluar(`document.querySelector('.research-check-list input[value=${JSON.stringify(datos.filos[0])}]').click()`);
    await visible("document.querySelector('.research-sidebar').inert && document.querySelector('.research-filter-lock').disabled");
    await reposo();
    assert.match(await evaluar("document.querySelector('.atlas-collection-count').textContent"), /17 registros/);
    await mostrarTooltip();
    await evaluar(`document.querySelector('.research-check-list input[value=${JSON.stringify(datos.filos[0])}]').click()`);
    await reposo();
    assert.match(await evaluar("document.querySelector('.atlas-collection-count').textContent"), /18 registros/);
    await mostrarTooltip();
    await conexion.send('Network.emulateNetworkConditions', {offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1});

    // TreeTips de MDD: tarjetas iniciales; cada selección abre sólo su rama 3D.
    await evaluar("document.querySelector('button[aria-label=\"Árbol taxonómico\"]').click()");
    await visible("document.querySelector('#explorador-taxonomico[open] .portal-mdd-tree-stage')?.checkVisibility()"); await reposo();
    const catalogoExplorador = await evaluar("({total: Alpine.$data(document.querySelector('#explorador-taxonomico')).total, texto: document.querySelector('.taxonomy-explorer-header p').textContent})");
    assert.ok(catalogoExplorador.total >= datos.codigos.length, 'El explorador conserva el catálogo público completo, incluidos todos los registros de la fixture.');
    assert.equal(Number(catalogoExplorador.texto.match(/[\d.]+/)[0].replaceAll('.', '')), catalogoExplorador.total, 'El encabezado refleja el total del catálogo propio del explorador.');
    assert.ok(await evaluar("(() => {const d=document.querySelector('#explorador-taxonomico'),r=d.getBoundingClientRect();return d.matches(':modal') && Math.abs(r.width-innerWidth*.9)<=2 && Math.abs(r.height-innerHeight*.9)<=2 && d.querySelector('#filo-explorador-taxonomico').checkVisibility() && !d.querySelector('footer,.taxonomy-explorer-map,.taxonomy-explorer-representation,.taxonomy-navigator-sidebar,.taxonomy-navigator-fold');})()"), 'El modal conserva sus márgenes, selector y buscador y libera el ancho del lateral retirado.');
    assert.equal(await evaluar("document.querySelector('.portal-ete-controls,.taxonomy-navigator-sidebar > h3,.taxonomy-navigator-caption,.taxonomy-navigator-breadcrumb,.taxonomy-navigator-heading,.taxonomy-expand-all,.taxonomy-branch-select')"), null);
    assert.ok(await evaluar("document.querySelector('.taxonomy-phylotree-search #busqueda-explorador-taxonomico').checkVisibility() && document.querySelector('.portal-ete-host').checkVisibility()"));
    async function buscarEnArbol(texto) {
        await evaluar("(() => {const b=document.querySelector('#busqueda-explorador-taxonomico');b.focus();b.value=" + JSON.stringify(texto) + ";b.dispatchEvent(new Event('input',{bubbles:true}));})()");
        await visible("document.querySelector('#sugerencias-explorador [role=option]')"); await reposo();
    }
    async function asegurarETEEstable() {
        const muestras = await evaluar(`new Promise(resolve => {
            const fin = performance.now() + 600, muestras = [];
            function medir() {
                const host = document.querySelector('.portal-ete-host'), stage = host.querySelector('.portal-ete-stage');
                const h = host.getBoundingClientRect(), s = stage.getBoundingClientRect();
                muestras.push([h.width,h.height,s.left,s.top,s.width,s.height,host.scrollLeft,host.scrollTop]);
                if (performance.now() < fin) requestAnimationFrame(medir); else resolve(muestras);
            }
            requestAnimationFrame(medir);
        })`);
        assert.ok(muestras.length >= 3);
        assert.ok(muestras.every(muestra => muestra.every((valor, i) => Math.abs(valor - muestras[0][i]) <= .5)), 'El árbol y sus barras permanecen estables tras terminar el dibujo.');
    }
    const raizETE = ".portal-ete-node[aria-label^=" + JSON.stringify('Browsersuborder' + token + '0,') + "]";
    await buscarEnArbol('FiloBrowser' + token);
    assert.match(await evaluar("document.querySelector('.atlas-collection-count').textContent"), /18 registros/);
    await evaluar("document.querySelector('#sugerencias-explorador button').click()");
    await visible("document.querySelector('.portal-taxonomy-branch-pane')?.dataset.nombre===" + JSON.stringify('FiloBrowser' + token));
    await visible("document.querySelector('.portal-three-scene')?.dataset.nombre===" + JSON.stringify('FiloBrowser' + token));
    assert.ok(await evaluar("(() => {const s=document.querySelector('.portal-three-scene');return s.dataset.target==='0.00,0.00,0.00' && s.dataset.zoom==='1.000' && s.dataset.pulsando==='true';})()"), 'Buscar convierte el resultado en raíz, lo centra y activa su pulso.');
    assert.equal(await evaluar("document.querySelectorAll('.taxonomy-explorer-pill').length"), 0, 'Buscar localiza un taxón sin convertirlo en filtro del catálogo.');
    assert.equal(await evaluar("document.querySelector('.taxonomy-search-token span:first-child').textContent"), 'FiloBrowser' + token, 'El ámbito permanece dentro del buscador, con su equis.');
    assert.equal(await evaluar("document.querySelector('.taxonomy-explorer-header p').textContent"), catalogoExplorador.texto, 'Buscar un taxón conserva el total del catálogo completo.');
    async function navegarInicial(nombre) {
        await evaluar("(() => {const d=Alpine.$data(document.querySelector('#explorador-taxonomico'));return d.irARama(d.nodos.find(n=>n.nombre===" + JSON.stringify(nombre) + "));})()");
        await visible(nombre === 'FiloBrowser' + token
            ? "document.querySelector('.portal-mdd-tree')?.dataset.taxonNombre===" + JSON.stringify(nombre)
            : "document.querySelector('.portal-three-scene')?.dataset.nombre===" + JSON.stringify(nombre));
        await visible("document.querySelector('.portal-ete-host').getAttribute('aria-busy')==='false'");
    }
    await navegarInicial('Browserclass' + token + '0');
    await evaluar("document.querySelector('.portal-three-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'PageDown',bubbles:true}))");
    await visible("document.querySelector('.portal-three-instructions').textContent.includes(" + JSON.stringify('Browserorder' + token + '0') + ")");
    await evaluar("document.querySelector('.portal-three-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}))");
    await visible("document.querySelector('.portal-three-scene')?.dataset.nombre===" + JSON.stringify('Browserorder' + token + '0'));
    assert.equal(await evaluar("document.querySelector(" + JSON.stringify(raizETE) + ")"), null, 'Abrir una rama 3D no crea sus hijos en la izquierda.');
    assert.ok(await evaluar("(() => {const d=Alpine.$data(document.querySelector('#explorador-taxonomico')),s=document.querySelector('.portal-ete-stage');return s.querySelector('.portal-mdd-tree').dataset.taxonClave===d.filoActual && [...s.querySelectorAll('[data-taxon-padre]')].every(n=>d.nodos.find(t=>t.clave===n.dataset.taxonClave)?.padre===n.dataset.taxonPadre);})()"), 'Abrir una rama conserva las tarjetas del filo y cada hijo permanece bajo su padre publicado.');
    assert.equal(await evaluar("document.querySelector('.taxonomy-navigator-tree')"), null);
    assert.ok(await evaluar("document.querySelectorAll('.portal-mdd-horizontal').length>0"));
    await evaluar("document.querySelector('.portal-three-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'PageDown',bubbles:true}))");
    await visible("document.querySelector('.portal-three-instructions').textContent.includes(" + JSON.stringify('Browsersuborder' + token + '0') + ")");
    await evaluar("document.querySelector('.portal-three-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}))");
    await visible("document.querySelector('.portal-taxonomy-branch-pane').dataset.nombre===" + JSON.stringify('Browsersuborder' + token + '0'));
    await visible("document.querySelector('.portal-three-scene').dataset.nombre===" + JSON.stringify('Browsersuborder' + token + '0'));
    assert.equal(await evaluar("document.querySelector('.portal-three-scene').dataset.nombre"), 'Browsersuborder' + token + '0', 'Seleccionar una esfera la convierte en raíz del grafo y de su ficha.');
    assert.equal(await evaluar("document.querySelector('.portal-branch-eyebrow,.portal-branch-title,.portal-branch-meta')"), null, 'La ficha no repite el encabezado del taxón.');
    assert.equal(await evaluar("[...document.querySelectorAll('.portal-taxonomy-branch-pane button')].some(b => /^(Expandir|Contraer)$/.test(b.textContent.trim()))"), false, 'La ficha no conserva los botones Expandir/Contraer.');
    assert.deepEqual(await evaluar("[...document.querySelectorAll('.portal-branch-tools button')].map(b => b.textContent.trim())"), ['Información'], 'La barra de la fotografía contiene únicamente el nuevo control Información junto a sus créditos.');
    assert.equal(await evaluar("document.querySelector(" + JSON.stringify(raizETE) + ")"), null);
    assert.ok(await evaluar("(() => {const d=Alpine.$data(document.querySelector('#explorador-taxonomico'));return [...document.querySelectorAll('.portal-mdd-horizontal')].every(e=>d.nodos.find(n=>n.clave===e.dataset.hijo)?.padre===e.dataset.padre);})()"), 'Cada conector sigue el padre publicado.');
    await navegarInicial('Browsersuborder' + token + '0'); await reposo();
    await visible("document.querySelector('.portal-mdd-tree-stage').dataset.motor==='MDD TreeTips (MIT)' && document.querySelector('.portal-ete-host').getAttribute('aria-busy')==='false'");
    assert.ok(await evaluar("document.querySelector('.portal-mdd-tree-stage')?.checkVisibility() && ![...document.querySelectorAll('.taxonomy-explorer-error')].some(e=>e.checkVisibility())"));
    assert.ok(await evaluar("[...document.querySelectorAll('.portal-mdd-name')].every(n=>parseFloat(getComputedStyle(n).fontSize)>=20)"), 'Los nombres horizontales usan al menos 20 px al zoom inicial.');
    const claseETE = '.portal-ete-node[aria-label^=' + JSON.stringify('Browserclass' + token + '0,') + ']';
    assert.ok(await evaluar("(() => {const h=document.querySelector('.portal-ete-host'),r=h.getBoundingClientRect(),n=h.querySelector('.portal-mdd-node').getBoundingClientRect(),v=h.querySelector('.portal-mdd-vertical').getBoundingClientRect(),j=h.querySelector('.portal-mdd-horizontal').getBoundingClientRect();return !h.querySelector('.portal-mdd-root') && Math.abs(v.top-r.top)<=2 && j.top>v.top && n.right>r.left && n.left<r.right && n.top>=r.top && n.bottom<=r.bottom;})()"), 'Sin título duplicado, el tronco parte del borde superior y la primera rama permanece visible.');
    await asegurarETEEstable();
    const anchoAjustado = await evaluar("document.querySelector('.portal-mdd-content').getBoundingClientRect().width");
    await navegarInicial('FiloBrowser' + token);
    assert.ok(await evaluar("document.querySelector('.portal-three-scene').dataset.raiz===document.querySelector('.portal-mdd-node').dataset.taxonClave"), 'Se abre automáticamente la esfera del primer nodo de la raíz elegida.');
    await evaluar("document.querySelector('.taxonomy-navigator-zoom [aria-label=\"Acercar árbol\"]').click()"); await reposo();
    assert.ok(Math.abs(Number(await evaluar("document.querySelector('.portal-three-scene').dataset.zoom")) - 1.25) < .01);
    assert.ok(Math.abs(await evaluar("document.querySelector('.portal-mdd-content').getBoundingClientRect().width") - anchoAjustado) < 1, 'El zoom de la esfera conserva el tamaño legible de las tarjetas.');
    await asegurarETEEstable();
    await evaluar("document.querySelector('.taxonomy-navigator-zoom [aria-label=\"Alejar árbol\"]').click()"); await reposo();
    assert.ok(Math.abs(await evaluar("document.querySelector('.portal-mdd-content').getBoundingClientRect().width") - anchoAjustado) < 1);
    for (const factor of [.8, .64]) {
        await evaluar("document.querySelector('[aria-label=\"Alejar árbol\"]').click()"); await reposo();
        assert.ok(Math.abs(Number(await evaluar("document.querySelector('.portal-three-scene').dataset.zoom")) - factor) < .001, 'Alejar funciona desde la vista inicial de la esfera.');
    }
    await evaluar("document.querySelector('.portal-three-fit').click()"); await reposo();
    for (let repeticion = 0; repeticion < 2; repeticion++) {
        await navegarInicial('Browsergenus' + token + '0'); await reposo();
        await evaluar("document.querySelector('.taxonomy-navigator-zoom [aria-label=\"Acercar árbol\"]').click()");
        await navegarInicial(datos.taxonBusqueda + ' alfa'); await reposo();
        assert.ok(await evaluar("document.querySelector('.portal-three-scene').dataset.nodos==='1' && document.querySelector('.portal-mdd-tree').dataset.taxonNombre===" + JSON.stringify('FiloBrowser' + token)), 'Una hoja elegida abre su única esfera y conserva las tarjetas del filo.');
        await navegarInicial('Browsersuborder' + token + '0'); await reposo();
        assert.ok(Math.abs(await evaluar("document.querySelector('.portal-mdd-content').getBoundingClientRect().width") - anchoAjustado) < 1, 'Volver a la rama restaura su vista inicial legible.');
        await asegurarETEEstable();
    }
    assert.ok(await evaluar("(() => {const h=document.querySelector('.portal-ete-host'),m=document.querySelector('.taxonomy-navigator');return h.clientWidth>=m.clientWidth-4 && !document.querySelector('.taxonomy-navigator-sidebar');})()"));
    await asegurarETEEstable();
    assert.equal(await evaluar("new URL(location.href).searchParams.has('fti')"), false);
    await evaluar("document.querySelector('.taxonomy-search-token').click()");
    await visible("document.querySelectorAll('.taxonomy-search-token').length===0");
    await buscarEnArbol(datos.taxonBusqueda + ' alfa');
    const antesEnterArbol = totalPeticiones;
    await conexion.send('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
    await conexion.send('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
    await visible("document.querySelector('.portal-taxonomy-branch-pane')?.dataset.nombre===" + JSON.stringify(datos.taxonBusqueda + ' alfa') + " && !document.querySelector('#sugerencias-explorador').checkVisibility()"); await reposo();
    assert.equal(await evaluar("document.querySelectorAll('.taxonomy-explorer-pill').length"), 0);
    assert.equal(totalPeticiones, antesEnterArbol, 'Localizar un taxón no modifica el mapa ni Livewire.');
    assert.match(await evaluar("document.querySelector('.atlas-collection-count').textContent"), /18 registros/);
    const cabeceraETE = await evaluar("(() => {const r=document.querySelector('.taxonomy-explorer-header').getBoundingClientRect();return {x:r.left+r.width/2,y:r.top+15};})()");
    await conexion.send('Input.dispatchMouseEvent', {type:'mousePressed',button:'left',clickCount:1,...cabeceraETE});
    await conexion.send('Input.dispatchMouseEvent', {type:'mouseMoved',button:'left',buttons:1,x:cabeceraETE.x+35,y:cabeceraETE.y-20});
    await conexion.send('Input.dispatchMouseEvent', {type:'mouseReleased',button:'left',clickCount:1,x:cabeceraETE.x+35,y:cabeceraETE.y-20});
    await reposo();
    assert.ok(await evaluar("(() => {const r=document.querySelector('.taxonomy-explorer-header').getBoundingClientRect();return Math.abs(r.left+ r.width/2-" + cabeceraETE.x + ")>1;})()"), 'El modal se mueve mediante arrastre de su cabecera.');
    const tamanoModal = await evaluar("(() => {const r=document.querySelector('#explorador-taxonomico').getBoundingClientRect();return {ancho:r.width,alto:r.height,x:r.right-12,y:r.bottom-12};})()");
    await conexion.send('Input.dispatchMouseEvent', {type:'mousePressed',button:'left',clickCount:1,x:tamanoModal.x,y:tamanoModal.y});
    await conexion.send('Input.dispatchMouseEvent', {type:'mouseMoved',button:'left',buttons:1,x:tamanoModal.x-160,y:tamanoModal.y-100});
    await conexion.send('Input.dispatchMouseEvent', {type:'mouseReleased',button:'left',clickCount:1,x:tamanoModal.x-160,y:tamanoModal.y-100}); await reposo();
    assert.ok(await evaluar("(() => {const r=document.querySelector('#explorador-taxonomico').getBoundingClientRect(),h=document.querySelector('.portal-ete-host');return r.width<" + (tamanoModal.ancho - 100) + " && r.height<" + (tamanoModal.alto - 60) + " && h.scrollWidth<=h.clientWidth+1 && h.scrollHeight<=h.clientHeight+1;})()"), 'Redimensionar reduce el modal y vuelve a encuadrar el dibujo completo.');
    await asegurarETEEstable();
    assert.ok(await evaluar("document.activeElement.classList.contains('taxonomy-explorer-resize')"), 'Arrastrar la esquina mantiene el control accesible para teclado.');
    assert.equal(await evaluar("document.querySelectorAll('.taxonomy-explorer-resize[data-esquina]').length"), 8, 'El modal permite redimensionar desde sus cuatro bordes y cuatro esquinas.');
    for (const [borde, cursor] of [['n','ns-resize'],['e','ew-resize'],['s','ns-resize'],['w','ew-resize']]) {
        await evaluar("document.querySelector('.taxonomy-explorer-resize[data-esquina=" + JSON.stringify(borde) + "]').focus()");
        await conexion.send('Input.dispatchKeyEvent',{type:'keyDown',key:'Home',code:'Home',windowsVirtualKeyCode:36});
        await conexion.send('Input.dispatchKeyEvent',{type:'keyUp',key:'Home',code:'Home',windowsVirtualKeyCode:36}); await reposo();
        const antesBorde = await evaluar("(() => {const d=document.querySelector('#explorador-taxonomico'),b=d.querySelector('.taxonomy-explorer-resize[data-esquina=" + JSON.stringify(borde) + "]'),r=d.getBoundingClientRect(),h=b.getBoundingClientRect();return {ancho:r.width,alto:r.height,izquierda:r.left,arriba:r.top,derecha:r.right,abajo:r.bottom,x:h.left+h.width/2,y:h.top+h.height/2,cursor:getComputedStyle(b).cursor};})()");
        assert.equal(antesBorde.cursor, cursor, 'El borde muestra su cursor de redimensionamiento.');
        const destinoBorde = {x:antesBorde.x+(borde==='w'?40:-40),y:antesBorde.y+(borde==='n'?40:-40)};
        await conexion.send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',clickCount:1,x:antesBorde.x,y:antesBorde.y});
        await conexion.send('Input.dispatchMouseEvent',{type:'mouseMoved',button:'left',buttons:1,...destinoBorde});
        await conexion.send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',clickCount:1,...destinoBorde}); await reposo();
        const despuesBorde = await evaluar("(() => {const r=document.querySelector('#explorador-taxonomico').getBoundingClientRect();return {ancho:r.width,alto:r.height,izquierda:r.left,arriba:r.top,derecha:r.right,abajo:r.bottom};})()");
        if (['e','w'].includes(borde)) {
            assert.ok(Math.abs(despuesBorde.ancho-(antesBorde.ancho-40))<2 && Math.abs(despuesBorde.alto-antesBorde.alto)<2 && Math.abs(despuesBorde.arriba-antesBorde.arriba)<2);
            assert.ok(Math.abs(despuesBorde[borde==='w'?'derecha':'izquierda']-antesBorde[borde==='w'?'derecha':'izquierda'])<2);
        } else {
            assert.ok(Math.abs(despuesBorde.alto-(antesBorde.alto-40))<2 && Math.abs(despuesBorde.ancho-antesBorde.ancho)<2 && Math.abs(despuesBorde.izquierda-antesBorde.izquierda)<2);
            assert.ok(Math.abs(despuesBorde[borde==='n'?'abajo':'arriba']-antesBorde[borde==='n'?'abajo':'arriba'])<2);
        }
    }
    await conexion.send('Input.dispatchKeyEvent',{type:'keyDown',key:'Home',code:'Home',windowsVirtualKeyCode:36});
    await conexion.send('Input.dispatchKeyEvent',{type:'keyUp',key:'Home',code:'Home',windowsVirtualKeyCode:36}); await reposo();
    await conexion.send('Emulation.setDeviceMetricsOverride',{width:390,height:700,deviceScaleFactor:1,mobile:false}); await reposo();
    assert.ok(await evaluar("(() => {const r=document.querySelector('#explorador-taxonomico').getBoundingClientRect();return r.left>=7 && r.top>=7 && r.right<=innerWidth-7 && r.bottom<=innerHeight-7;})()"), 'Un modal movido permanece completo tras reducir la pantalla.');
    await navegarInicial(datos.taxonBusqueda + ' alfa');
    const seleccionadoETE = '.portal-three-canvas';
    await evaluar("document.querySelector(" + JSON.stringify(seleccionadoETE) + ").scrollIntoView({block:'center',inline:'center'})");
    async function menuTaxon() {
        const posicion = await evaluar("(() => {const r=document.querySelector(" + JSON.stringify(seleccionadoETE) + ").getBoundingClientRect();return {x:r.left+r.width/2,y:r.top+r.height/2};})()");
        await conexion.send('Input.dispatchMouseEvent',{type:'mousePressed',button:'right',clickCount:1,...posicion});
        await conexion.send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'right',clickCount:1,...posicion});
        await visible("document.querySelector('.taxonomy-explorer-context-menu')?.checkVisibility()");
    }
    await menuTaxon();
    await evaluar("[...document.querySelectorAll('.taxonomy-explorer-context-menu button')].find(b=>b.textContent==='Ver registros').click()");
    await visible("document.querySelector('#registros-taxon-explorador[open] .portal-record-row')"); await reposo();
    assert.equal(await evaluar("document.querySelector('#explorador-taxonomico').open"), true);
    assert.deepEqual((await evaluar("[...document.querySelectorAll('#registros-taxon-explorador .portal-record-row th[scope=row]')].map(n=>n.textContent.trim())")).sort(), datos.codigos.slice(0,17).sort());
    await evaluar("document.querySelector('#registros-taxon-explorador .portal-record-row').click()");
    await visible("document.querySelector('#registros-taxon-explorador .atlas-record-card')");
    await evaluar("document.querySelector('.taxonomy-records-back').click()");
    await visible("document.querySelector('#registros-taxon-explorador .portal-record-row')");
    await evaluar("document.querySelector('#registros-taxon-explorador .taxonomy-explorer-close').click()");
    await visible("!document.querySelector('#registros-taxon-explorador').open && document.querySelector('#explorador-taxonomico').open && document.activeElement.classList.contains('portal-three-canvas')");
    await menuTaxon();
    await evaluar("[...document.querySelectorAll('.taxonomy-explorer-context-menu button')].find(b=>b.textContent==='Ver en el mapa').click()");
    await visible("!document.querySelector('#explorador-taxonomico').open && document.activeElement.classList.contains('atlas-map')"); await reposo();
    assert.equal(await evaluar("new URL(location.href).searchParams.get('fti')"), datos.especies[0]);
    assert.match(await evaluar("document.querySelector('.atlas-collection-count').textContent"), /17 registros/);
    await evaluar("[...document.querySelectorAll('.collection-active-filters button')].find(b=>b.getAttribute('wire:click')?.includes('filtroTaxonId')).click()"); await reposo();
    await evaluar("(() => {const b=document.querySelector('input[placeholder=\"Nombre científico en cualquier rango\"]');b.focus();b.value=" + JSON.stringify(datos.taxonBusqueda) + ";b.dispatchEvent(new Event('input',{bubbles:true}));b.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));})()"); await reposo();
    await visible("document.querySelector('.atlas-collection-count').textContent.includes('18 registros')");
    await evaluar("document.querySelector('button[aria-label=\"Árbol taxonómico\"]').click()");
    await visible("document.querySelector('#explorador-taxonomico[open] .portal-mdd-tree-stage')"); await reposo();
    assert.equal(await evaluar("document.querySelectorAll('.taxonomy-explorer-pill').length"), 0);
    assert.equal(await evaluar("document.querySelector('.taxonomy-explorer-header p').textContent"), catalogoExplorador.texto, 'Reabrir el explorador conserva su catálogo independiente del filtro del mapa.');
    await conexion.send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false}); await reposo();
    const antesDeAjustar = totalPeticiones;
    await evaluar("document.querySelector('.portal-three-fit').click()");
    await visible("Alpine.$data(document.querySelector('#explorador-taxonomico')).ajustado && document.querySelector('.portal-three-scene').dataset.zoom==='1.000'");
    assert.ok(await evaluar("(() => {const l=document.querySelector('.portal-ete-host');return l.scrollHeight<=l.clientHeight+1 && l.scrollWidth<=l.clientWidth+1;})()"));
    assert.equal(totalPeticiones, antesDeAjustar);
    assert.ok(await evaluar("(() => {const d=Alpine.$data(document.querySelector('#explorador-taxonomico'));return d.filtrosArbol.length===0 && d.nodos.some(n=>n.nombre==='FiloBrowser" + token + "');})()"), 'Cerrar y reabrir conserva el catálogo completo y su selección independiente del mapa.');
    const antesDeEscape = totalPeticiones;
    await conexion.send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await conexion.send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await visible("!document.querySelector('#explorador-taxonomico').open && document.activeElement.getAttribute('aria-label')==='Árbol taxonómico'");
    assert.equal(totalPeticiones, antesDeEscape);
    await evaluar("document.querySelector('button[aria-label=\"Árbol taxonómico\"]').click()");
    await visible("document.querySelector('#explorador-taxonomico[open] .portal-mdd-tree-stage')"); await reposo();
    const fondoExplorador = await evaluar("(() => {const r=document.querySelector('#explorador-taxonomico').getBoundingClientRect();return {x:r.left-8,y:r.top+20};})()");
    await conexion.send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',clickCount:1,...fondoExplorador});
    await conexion.send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',clickCount:1,...fondoExplorador});
    assert.equal(await evaluar("document.querySelector('#explorador-taxonomico').open"), true);
    const antesDePantallaYCerrar = totalPeticiones;
    await navegarInicial('FiloBrowser' + token);
    const raizAntesDePantalla = await evaluar("document.querySelector('.portal-three-scene').dataset.raiz");
    const controlPantalla = await evaluar("(() => {const r=document.querySelector('[aria-label=\"Ver octree en pantalla completa\"]').getBoundingClientRect();return {x:r.left+r.width/2,y:r.top+r.height/2};})()");
    await conexion.send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',clickCount:1,...controlPantalla});
    await conexion.send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',clickCount:1,...controlPantalla});
    await visible("document.fullscreenElement===document.querySelector('.portal-three-scene')");
    assert.equal(await evaluar("document.querySelector('.portal-three-scene').dataset.raiz"), raizAntesDePantalla);
    await conexion.send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await conexion.send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await visible("!document.fullscreenElement && document.querySelector('#explorador-taxonomico').open");
    await visible("document.querySelector('.portal-ete-host').checkVisibility() && document.querySelector('.portal-mdd-tree-stage') && !document.querySelector('.taxonomy-navigator-sidebar')");
    await evaluar("document.querySelector('#explorador-taxonomico .taxonomy-explorer-close').click()");
    await visible("!document.querySelector('#explorador-taxonomico').open && document.activeElement.getAttribute('aria-label')==='Árbol taxonómico'");
    assert.equal(totalPeticiones, antesDePantallaYCerrar);
    await conexion.send('Emulation.setDeviceMetricsOverride',{width:390,height:700,deviceScaleFactor:1,mobile:true});
    await evaluar("document.querySelector('button[aria-label=\"Árbol taxonómico\"]').click()");
    await visible("document.querySelector('#explorador-taxonomico[open] .portal-mdd-tree-stage').checkVisibility()"); await reposo();
    assert.ok(await evaluar("(() => {const d=document.querySelector('#explorador-taxonomico'),r=d.getBoundingClientRect(),cerrar=d.querySelector('.taxonomy-explorer-close').getBoundingClientRect(),pantalla=d.querySelector('[aria-label=\"Ver octree en pantalla completa\"]').getBoundingClientRect();return Math.abs(r.width-innerWidth*.9)<=2 && Math.abs(r.height-innerHeight*.9)<=2 && cerrar.bottom<=r.bottom && pantalla.left>=r.left && pantalla.right<=r.right && d.querySelector('.taxonomy-navigator-main').clientHeight>=150;})()"));
    await evaluar("document.querySelector('#explorador-taxonomico .taxonomy-explorer-close').click()");
    await visible("!document.querySelector('#explorador-taxonomico').open");
    await conexion.send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false}); await reposo();

    // Ramas masivas 3D: se conserva la raíz izquierda y el DOM, sin mutar mapa ni base de datos.
    await evaluar("document.querySelector('button[aria-label=\"Árbol taxonómico\"]').click()"); await reposo();
    await visible("(() => {const dialogo=document.querySelector('#explorador-taxonomico'),d=Alpine.$data(dialogo);return dialogo.open && d.listo && !d.buscando && !d.visorCargando && d.cargandoRamas.length===0 && d.nodos.length>0 && document.querySelector('.portal-three-scene')?.checkVisibility();})()");
    await evaluar(`(async () => {
        const d=Alpine.$data(document.querySelector('#explorador-taxonomico'));
        window.respaldoVisorMasivo={nodos:d.nodos,expandidos:d.expandidos,cargados:d.cargados,ramaActiva:d.ramaActiva,seleccionado:d.seleccionado};
        const n=(clave,padre,nombre,etiqueta,tieneHijos=false)=>({clave,id:clave,padre,nombre,etiqueta,total:1,tieneHijos,hoja:!tieneHijos});
        d.nodos=[n('masivo',null,'Filo masivo','Filo',true),n('familia-a','masivo','Familia A','Familia',true),n('familia-b','masivo','Familia B','Familia',true),
            ...Array.from({length:5},(_,i)=>n('directo-'+i,'masivo','Hijo directo '+i,'Clase')),
            ...['a','b'].flatMap(f=>Array.from({length:122},(_,i)=>n(f+'-'+i,'familia-'+f,'Especie '+f+' '+i,'Especie')))];
        d.cargados=['masivo','familia-a','familia-b']; d.expandidos=[];
        await d.irARama(d.nodos[0]);
    })()`);
    await visible("document.querySelector('.portal-mdd-tree')?.dataset.taxonClave==='masivo' && document.querySelector('.portal-mdd-tree-stage').dataset.nodos==='8'"); await reposo();
    await visible("document.querySelector('.portal-three-scene')?.dataset.raiz==='familia-a' && document.querySelector('.portal-three-scene').dataset.nodos==='123'");
    assert.equal(await evaluar("document.querySelectorAll('.portal-mdd-node').length"), 7, 'El filo muestra sus siete hijos, sin mezclar los 244 nietos en esa vista ni repetir su título.');
    for (const familia of ['familia-a', 'familia-b']) {
        await evaluar("document.querySelector('.portal-mdd-node[data-taxon-clave=" + JSON.stringify(familia) + "]').click()");
        await visible("document.querySelector('.portal-three-scene')?.dataset.raiz===" + JSON.stringify(familia) + " && document.querySelector('.portal-three-scene').dataset.nodos==='123'");
        assert.equal(await evaluar("document.querySelector('.portal-three-scene').dataset.relaciones"), '122');
        assert.equal(await evaluar("document.querySelector('.portal-mdd-tree-stage').dataset.nodos"), '8');
        assert.equal(await evaluar("document.querySelectorAll('.portal-mdd-node').length"), 7);
        assert.equal(await evaluar("document.querySelector('.portal-mdd-tree').dataset.taxonClave"), 'masivo');
        assert.equal(await evaluar("document.querySelector('.portal-mdd-node[data-taxon-clave=" + JSON.stringify(familia) + "] .portal-mdd-count').textContent"), '· 1', 'La cifra muestra los registros publicados, no la cantidad de hijos.');
    }
    assert.ok(await evaluar(`(() => {
        const d=Alpine.$data(document.querySelector('#explorador-taxonomico')),s=document.querySelector('.portal-mdd-tree-stage'),h=document.querySelector('.portal-ete-host');
        return [...s.querySelectorAll('.portal-mdd-name')].every(n=>parseFloat(getComputedStyle(n).fontSize)>=20)
            && [...s.querySelectorAll('.portal-mdd-horizontal')].every(e=>d.nodos.find(n=>n.clave===e.dataset.hijo)?.padre===e.dataset.padre)
            && !s.querySelector('canvas,svg') && document.querySelector('.portal-three-canvas').checkVisibility()
            && !document.querySelector('.portal-three-label,.portal-three-branches')
            && document.querySelector('.portal-three-context').closest('.taxonomy-navigator-zoom')
            && [...document.querySelectorAll('.portal-three-legend-item')].map(n=>n.textContent).join(',')==='Familia,Especie';
    })()`), 'La rama completa se representa a la derecha con leyenda por rango, sin expandir tarjetas ni superponer nombres.');
    const altoCanvasAntes = await evaluar("document.querySelector('.portal-three-canvas').getBoundingClientRect().height");
    await evaluar("document.querySelector('.portal-three-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'PageDown',bubbles:true}))"); await reposo();
    assert.equal(await evaluar("document.querySelector('.portal-three-canvas').getBoundingClientRect().height"), altoCanvasAntes, 'El linaje largo de una especie no desplaza las esferas bajo el puntero.');
    await evaluar("document.querySelector('.portal-three-legend-item[data-rango=\"especie\"]').dispatchEvent(new PointerEvent('pointerenter'))"); await reposo();
    assert.ok(await evaluar("document.querySelector('.portal-three-scene').dataset.nodosIluminados==='123' && document.querySelector('.portal-three-scene').dataset.ramasIluminadas==='122'"), 'La leyenda ilumina cada especie y su conexión completa hasta la raíz familiar.');
    await evaluar("document.querySelector('.portal-three-legend-item[data-rango=\"especie\"]').click()"); await reposo();
    await visible("document.querySelector('.portal-three-scene').dataset.rangoFiltrado==='especie' && document.querySelector('.portal-three-scene').dataset.indexados==='123'");
    assert.ok(await evaluar("(() => {const s=document.querySelector('.portal-three-scene');return s.dataset.raiz==='familia-b' && s.dataset.nodos==='123' && s.dataset.nodosTotales==='123' && s.dataset.rangosVisibles==='familia,especie' && s.dataset.seleccionVisible==='true' && document.querySelector('.portal-three-legend-item[data-rango=\"especie\"]').getAttribute('aria-pressed')==='true' && !document.querySelector('.portal-three-rank-picker');})()"), 'La leyenda conserva la esfera raíz seleccionable y todas las especies de la rama.');
    assert.equal(await evaluar("document.querySelectorAll('.portal-mdd-node').length"), 7, 'Filtrar un rango conserva las tarjetas del filo.');
    await evaluar("document.querySelector('.portal-three-legend-item[data-rango=\"familia\"]').click()");
    await visible("document.querySelector('.portal-three-scene').dataset.rangoFiltrado==='familia' && document.querySelector('.portal-three-scene').dataset.nodos==='1'");
    assert.equal(await evaluar("document.querySelector('.portal-three-scene').dataset.rangosVisibles"), 'familia');
    await evaluar("document.querySelector('.portal-three-legend-item[data-rango=\"familia\"]').click()");
    await visible("document.querySelector('.portal-three-scene').dataset.rangoFiltrado==='' && document.querySelector('.portal-three-scene').dataset.nodos==='123'");
    await evaluar("document.querySelector('.portal-mdd-node[data-taxon-clave=\"familia-b\"]').click()");
    await visible("document.querySelector('.portal-three-scene').dataset.raiz==='familia-b' && document.querySelector('.portal-three-scene').dataset.nodos==='123'");
    await evaluar("window.nodoMasivoOriginal=document.querySelector('.portal-mdd-node');document.querySelector('[aria-label=\"Acercar árbol\"]').click()");
    await reposo();
    assert.ok(await evaluar("document.querySelector('.portal-mdd-node')===window.nodoMasivoOriginal && Math.abs(Number(document.querySelector('.portal-three-scene').dataset.zoom)-1.25)<.01"), 'El zoom superior acerca el grafo y conserva las tarjetas iniciales.');
    await evaluar("document.querySelector('[aria-label=\"Alejar árbol\"]').click()");
    for (const factor of [.8, .64]) {
        await evaluar("document.querySelector('[aria-label=\"Alejar árbol\"]').click()"); await reposo();
        assert.ok(Math.abs(Number(await evaluar("document.querySelector('.portal-three-scene').dataset.zoom")) - factor) < .001);
    }
    await evaluar("document.querySelector('.portal-three-fit').click()"); await reposo();
    await asegurarETEEstable();
    await evaluar("document.querySelector('.portal-mdd-node[data-taxon-clave=\"familia-a\"]').click()");
    await visible("document.querySelector('.portal-three-scene').dataset.raiz==='familia-a' && document.querySelector('.portal-three-scene').dataset.nodos==='123'");
    await evaluar("document.querySelector('.portal-mdd-node[data-taxon-clave=\"familia-a\"]').click()");
    await reposo();
    assert.ok(await evaluar("document.querySelector('.portal-mdd-node')===window.nodoMasivoOriginal && document.querySelectorAll('.portal-mdd-node').length===7 && document.querySelector('.portal-three-scene').dataset.raiz==='familia-a' && document.querySelector('.portal-three-scene').dataset.nodos==='123'"), 'Elegir otra vez una rama conserva sus descendientes 3D y no despliega ni reconstruye las tarjetas.');
    await evaluar("document.querySelector('.portal-mdd-node[data-taxon-clave=\"familia-b\"]').click();document.querySelector('.portal-mdd-node[data-taxon-clave=\"familia-a\"]').click()");
    await reposo();
    await visible("document.querySelector('.portal-taxonomy-three-pane').getAttribute('aria-busy')==='false'");
    assert.ok(await evaluar("document.querySelector('.portal-three-scene').dataset.raiz==='familia-a' && document.querySelector('.portal-three-title').textContent==='Familia A' && !document.querySelector('.portal-three-status').checkVisibility() && !document.querySelector('.portal-ete-loading').checkVisibility() && [...document.querySelectorAll('.portal-three-legend button')].every(b=>!b.disabled)"), 'Una raíz pendiente no sustituye el dibujo ni el encabezado de la última rama elegida, ni deja el aviso de carga activo o la leyenda bloqueada.');
    for (const familia of ['familia-a','familia-b','familia-a']) {
        await evaluar("(() => {const d=Alpine.$data(document.querySelector('#explorador-taxonomico'));return d.irARama(d.nodos.find(n=>n.clave===" + JSON.stringify(familia) + "));})()");
        await visible("document.querySelector('.portal-three-scene')?.dataset.raiz===" + JSON.stringify(familia));
        assert.equal(await evaluar("document.querySelector('.portal-three-scene').dataset.nodos"), '123');
        assert.ok(await evaluar("document.querySelector('.portal-mdd-tree').dataset.taxonClave==='masivo' && document.querySelectorAll('.portal-mdd-node').length===7"), 'Cambiar la raíz 3D conserva las siete tarjetas del filo.');
        await evaluar("document.querySelector('.portal-three-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}))");
        await visible("document.querySelector('.portal-taxonomy-branch-pane').dataset.nodos==='123'");
        assert.ok(await evaluar("(() => {const h=document.querySelector('.portal-taxonomy-branch-pane');return h.dataset.filas==='123' && Number(h.dataset.dibujados)<20 && h.querySelector('.portal-branch-tree').scrollHeight>h.querySelector('.portal-branch-tree').clientHeight;})()"), 'La rama con 122 hijos se conserva completa en el explorador derecho, con filas virtuales y desplazamiento.');
        assert.equal(await evaluar("Alpine.$data(document.querySelector('#explorador-taxonomico')).visorError"), '', 'Cambiar la raíz conserva hijos compartidos sin reutilizar padres retirados.');
    }
    await evaluar("(() => {const d=Alpine.$data(document.querySelector('#explorador-taxonomico'));Object.assign(d,window.respaldoVisorMasivo);d.distribuir();delete window.respaldoVisorMasivo;delete window.nodoMasivoOriginal;delete window.hijoMasivoOriginal;})()"); await reposo();
    await evaluar("document.querySelector('#explorador-taxonomico .taxonomy-explorer-close').click()");

    // El modal abre los registros del punto directamente y permite recorrer todas sus páginas.
    async function recorrerRegistrosUbicacion() {
        const codigos = [];
        for (let pagina = 0; pagina < 12; pagina++) {
            await reposo();
            codigos.push(...await evaluar("[...document.querySelectorAll('.atlas-cell-dialog .portal-record-row th[scope=row]')].map(n => n.textContent.trim())"));
            const haySiguiente = await evaluar("[...document.querySelectorAll('.atlas-cell-dialog .atlas-cell-pagination button')].some(b => b.textContent.trim() === 'Siguiente' && !b.disabled)");
            if (!haySiguiente) return codigos;
            const etiqueta = await evaluar("document.querySelector('.atlas-cell-pagination span').textContent");
            await evaluar("[...document.querySelectorAll('.atlas-cell-dialog .atlas-cell-pagination button')].find(b => b.textContent.trim() === 'Siguiente').click()");
            await visible(`document.querySelector('.atlas-cell-pagination span').textContent !== ${JSON.stringify(etiqueta)}`);
        }
        throw new Error('La paginación de la ubicación no terminó.');
    }
    await evaluar("[...document.querySelectorAll('.atlas-map [aria-label^=\"Ubicación original\"]')].find(p=>p.getAttribute('aria-label').includes('12 registros')).dispatchEvent(new MouseEvent('click',{bubbles:true}))");
    await visible("document.querySelector('.atlas-cell-dialog[open] .portal-record-row')"); await reposo();
    assert.equal(await evaluar("document.querySelectorAll('.atlas-cell-dialog .atlas-tree-section, .atlas-cell-dialog .atlas-taxon-information, .atlas-cell-dialog .atlas-cell-toolbar, .atlas-cell-dialog .collection-view-switch, .atlas-cell-dialog .atlas-record-lov').length"), 0);
    const codigosUbicacion = await recorrerRegistrosUbicacion();
    assert.equal(codigosUbicacion.length, 12);
    assert.deepEqual([...codigosUbicacion].sort(), datos.codigos.slice(0, 12).sort());
    await evaluar(`(() => {const fila=[...document.querySelectorAll('.atlas-cell-dialog .portal-record-row')].find(n => n.querySelector('th[scope=row]').textContent.trim() === ${JSON.stringify(datos.codigos[11])}); fila.focus(); fila.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));})()`);
    await visible(`document.querySelector('.portal-record-dialog[open]')?.textContent.includes(${JSON.stringify(datos.codigos[11])})`); await reposo();
    await evaluar("document.querySelector('.portal-record-dialog button[aria-label=\"Cerrar ficha del registro\"]').click()"); await reposo();
    await visible("!document.querySelector('.portal-record-dialog').open && document.querySelector('.atlas-cell-dialog').open");
    const antesDeCerrar = totalPeticiones;
    await evaluar("document.querySelector('.atlas-cell-header button[aria-label=\"Cerrar registros\"]').click()");
    await demora(700);
    assert.equal(totalPeticiones, antesDeCerrar, 'Cerrar el modal hizo una consulta innecesaria.');
    assert.equal(await evaluar("document.querySelector('.atlas-cell-dialog').open"), false);

    // Otro punto reemplaza la selección anterior y presenta solo sus cinco ejemplares.
    await evaluar("[...document.querySelectorAll('.atlas-map [aria-label^=\"Ubicación original\"]')].find(p=>p.getAttribute('aria-label').includes('5 registros')).dispatchEvent(new MouseEvent('click',{bubbles:true}))");
    await visible(`document.querySelector('.atlas-cell-dialog[open] .portal-record-row')?.textContent.includes(${JSON.stringify(datos.codigos[12])})`); await reposo();
    const codigosOtroPunto = await recorrerRegistrosUbicacion();
    assert.equal(codigosOtroPunto.length, 5);
    assert.deepEqual([...codigosOtroPunto].sort(), datos.codigos.slice(12, 17).sort());
    assert.equal(codigosOtroPunto.some(codigo => codigosUbicacion.includes(codigo)), false);
    assert.ok(await evaluar("(() => {const region=document.querySelector('.atlas-cell-dialog .atlas-record-table-scroll'); return region.clientHeight > 0 && region.clientWidth > 0;})()"), 'La tabla debe disponer de espacio para desplazarse dentro del modal.');
    await evaluar("document.querySelector('.atlas-cell-header button[aria-label=\"Cerrar registros\"]').click()");

    // Los tres gráficos nuevos aplican sus filtros al mapa mediante clicks reales en sus puntos.
    async function pulsarGrafico(id, indice = 0) {
        const punto = await evaluar(`(() => {const c=document.querySelector('#${id}').closest('section').querySelector('canvas'); c.scrollIntoView({block:'center'}); const chart=window.HubDigitalChart.getChart(c); const p=chart.getDatasetMeta(0).data[${indice}].getCenterPoint(); const r=c.getBoundingClientRect(); return {x:r.left+p.x,y:r.top+p.y};})()`);
        await conexion.send('Input.dispatchMouseEvent', {type: 'mousePressed', button: 'left', clickCount: 1, ...punto});
        await conexion.send('Input.dispatchMouseEvent', {type: 'mouseReleased', button: 'left', clickCount: 1, ...punto});
        await reposo();
    }
    for (const [id, alias] of [['titulo-decadas', 'ffd'], ['titulo-estacionalidad', 'fmes'], ['titulo-altitud', 'fed']]) {
        await pulsarGrafico(id);
        await visible(`new URL(location.href).searchParams.has('${alias}')`);
        assert.ok(await evaluar("document.querySelector('.atlas-map [aria-label^=\"Ubicación original\"]')"));
        await evaluar("[...document.querySelectorAll('.collection-active-filters button')].find(b=>b.getAttribute('wire:click')?.includes(" + JSON.stringify(alias === 'ffd' ? 'periodo' : alias === 'fmes' ? 'filtroMes' : 'elevacion') + ")).click()");
        await reposo();
    }
    // Las leyendas no ocupan la vista normal; Indicador muestra la explicación y PDF agrega sus figuras.
    assert.equal(await evaluar("document.querySelectorAll('.atlas-dashboard .atlas-figure-caption').length"), 0);
    assert.equal(await evaluar("[...document.querySelectorAll('#menu-filos button')].filter(b=>b.getAttribute('x-on:click') === 'alternarGrafico()').length"), 0);
    await evaluar("document.querySelector('.atlas-map-actions .atlas-panel-tools > button').click(); [...document.querySelectorAll('.atlas-map-actions [role=menuitem]')].find(b=>b.textContent.trim()==='Indicador').click()");
    await visible("document.querySelector('.atlas-index-dialog[open] .atlas-indicator-explanation')");
    assert.equal(await evaluar("document.querySelector('.atlas-index-dialog[open] .atlas-indicator-explanation').textContent.trim().split(/\\s+/).length"), 80);
    await evaluar("document.querySelector('.atlas-index-dialog[open]').close()");
    // Solo los paneles pasan al documento imprimible; el navegador genera un PDF real.
    await evaluar(`window.__pdfPreparado=false; window.__observadorPdf=new MutationObserver(cambios=>{for(const cambio of cambios) for(const nodo of cambio.addedNodes) if(nodo.matches?.('.atlas-pdf-frame')) nodo.contentWindow.print=()=>{window.__pdfPreparado=true;};}); window.__observadorPdf.observe(document.body,{childList:true}); document.querySelector('.atlas-map-actions .atlas-panel-tools > button').click(); [...document.querySelectorAll('.atlas-map-actions [role=menuitem]')].find(b=>b.textContent.trim()==='Exportar a PDF').click();`);
    await visible('window.__pdfPreparado');
    assert.equal(await evaluar("document.querySelector('.atlas-pdf-frame').contentDocument.querySelectorAll('main > .atlas-panel').length"), 7);
    assert.equal(await evaluar("document.querySelector('.atlas-pdf-frame').contentDocument.querySelectorAll('button, .research-sidebar, .atlas-panel-tools, .atlas-map-actions').length"), 0);
    assert.equal(await evaluar("document.querySelector('.atlas-pdf-frame').contentDocument.querySelectorAll('.atlas-figure-caption').length"), 7);
    const imprimible = await evaluar("document.querySelector('.atlas-pdf-frame').contentDocument.documentElement.outerHTML");
    // Guardamos evidencias en el directorio temporal, sin reemplazar la página de la aplicación.
    writeFileSync(path.join(temporal, 'paneles.html'), imprimible);
    await conexion.send('Page.captureScreenshot').then(r => writeFileSync(path.join(temporal, 'portal.png'), Buffer.from(r.data, 'base64')));
    await evaluar("window.__observadorPdf.disconnect(); document.querySelector('.atlas-pdf-frame').remove()");

    // La tabla mantiene navegación visible al cambiar el tamaño y la escala de presentación.
    await evaluar("document.querySelector('button[aria-label=\"Vista de registros\"]').click()");
    await visible("document.querySelector('.portal-records-viewport .portal-record-row')"); await reposo();
    const cantidades = [];
    for (const [altura, escala] of [[900, 1], [620, 1], [900, .8], [900, 1.25], [620, 2]]) {
        await conexion.send('Emulation.setDeviceMetricsOverride', {width: 1440, height: altura, deviceScaleFactor: 1, mobile: false});
        await evaluar(`document.documentElement.style.zoom='${escala}'; window.dispatchEvent(new Event('resize'))`); await reposo();
        // Leer geometría y hit testing juntos impide que una nueva consulta del
        // ResizeObserver deje el contenido inert entre ambas comprobaciones.
        const {ajuste, acceso} = await esperar(async () => peticiones.size === 0 && await evaluar(`(() => {
            const raiz=document.querySelector('.portal-records-viewport');
            if (!raiz || raiz.closest('.collection-main')?.inert || raiz.closest('.collection-workspace')?.classList.contains('is-updating') || window.Alpine.$data(raiz).solicitando) return false;
            const nav=raiz.querySelector('.portal-records-pagination');
            const boton=[...nav.querySelectorAll('button')].find(b=>b.textContent.includes('Siguiente'));
            const navegacion=nav.getBoundingClientRect(), r=boton.getBoundingClientRect();
            const puntos=[.2,.5,.9].map(f=>{const encima=document.elementFromPoint(r.left+r.width*f,r.top+r.height/2); return {libre:encima === boton || boton.contains(encima), elemento:encima?.className};});
            return {ajuste:{filas:raiz.querySelectorAll('.portal-record-row').length, inferior:navegacion.bottom, altura:window.innerHeight, posicion:getComputedStyle(nav).position}, acceso:{puntos, boton:r.toJSON(), raiz:raiz.getBoundingClientRect().toJSON()}};
        })()`), `paginación disponible a escala ${escala}`);
        assert.ok(ajuste.inferior <= ajuste.altura + 2, 'La navegación quedó fuera de la pantalla.');
        assert.ok(['static', 'relative'].includes(ajuste.posicion), 'La navegación quedó flotante.'); cantidades.push(ajuste.filas);
        assert.ok(acceso.puntos.every(p=>p.libre), `Otro control cubrió Siguiente a ${escala}: ${JSON.stringify(acceso)}`);
    }
    assert.ok(new Set(cantidades).size > 1, 'El número de registros no se adaptó a la pantalla.');
    await evaluar("document.documentElement.style.zoom='1'");
    await conexion.send('Emulation.setDeviceMetricsOverride', {width: 1440, height: 900, deviceScaleFactor: 1, mobile: false});
    await evaluar("document.querySelector('button[aria-label=\"Vista de tarjetas\"]').click()");
    await visible("document.querySelector('.collection-taxon-help[aria-label=\"Resumen de Nematomorpha\"]')"); await reposo();
    await evaluar("document.querySelector('.collection-taxon-help[aria-label=\"Resumen de Nematomorpha\"]').click()");
    await visible("document.querySelector('.collection-taxon-dialog[open] img')?.complete && document.querySelector('.collection-taxon-dialog[open] img')?.naturalWidth > 0");
    await evaluar("document.querySelector('.collection-taxon-dialog[open] button[aria-label=\"Cerrar explicación\"]').click()");
    // Una referencia ya resuelta pertenece al taxón original: el morph de tarjetas
    // debe destruir ese estado, incluso si el siguiente nivel reutiliza su posición.
    // Se siembra una respuesta local reconocible para no depender de la API externa.
    const filoFotografia = `FiloBrowser${token}`;
    const claseFotografia = `Browserclass${token}0`;
    const ordenFotografia = `Browserorder${token}0`;
    await evaluar(`document.querySelector('[data-foto-tarjeta="${filoFotografia}"]').closest('article').querySelector('button[aria-label^="Explorar"]').click()`);
    await visible(`document.querySelector('[data-foto-tarjeta="${claseFotografia}"]')`); await reposo();
    await evaluar(`(() => {const el=document.querySelector('[data-foto-tarjeta="${claseFotografia}"] [x-data]'); const estado=window.Alpine.$data(el); estado.destroy(); estado.fotos=[{url:'/images/nematomorpha-reference-20261006.webp',alt:'QA FOTO DEL TAXON ANTERIOR',species:'QA FOTO DEL TAXON ANTERIOR',autor:'Fixture local',fuente:'https://example.org/referencia',licencia:'CC0',licencia_url:'https://creativecommons.org/publicdomain/zero/1.0/'}]; estado.fallo=false; estado.cargando=false;})()`);
    await visible("document.querySelector('img[alt=\"QA FOTO DEL TAXON ANTERIOR\"]')");
    await evaluar(`document.querySelector('[data-foto-tarjeta="${claseFotografia}"]').closest('article').querySelector('.collection-taxon-main').click()`);
    await visible(`document.querySelector('[data-foto-tarjeta="${ordenFotografia}"]')`); await reposo();
    assert.equal(await evaluar("document.querySelectorAll('img[alt=\"QA FOTO DEL TAXON ANTERIOR\"]').length"), 0, 'La tarjeta conservó la fotografía del taxón anterior.');
    assert.equal(await evaluar(`window.Alpine.$data(document.querySelector('[data-foto-tarjeta="${ordenFotografia}"] [x-data]')).fotos.some(f=>f.species==='QA FOTO DEL TAXON ANTERIOR')`), false);
    await evaluar("[...document.querySelectorAll('button')].find(b=>b.getAttribute('wire:click') === \"navegar('', '')\").click()");
    await visible("document.querySelector('.collection-taxon-help[aria-label=\"Resumen de Nematomorpha\"]')"); await reposo();
    await evaluar("document.querySelector('button[aria-label=\"Vista de mapa y análisis\"]').click()");
    await visible("document.querySelector('.atlas-dashboard .leaflet-pane')"); await reposo();
    // Verificación del PDF de siete figuras con el motor Chromium.
    const marco = (await conexion.send('Page.getFrameTree')).frameTree.frame.id;
    await conexion.send('Page.setDocumentContent', {frameId: marco, html: imprimible});
    await visible("[...document.querySelectorAll('link[rel=stylesheet]')].every(hoja => hoja.sheet)");
    await evaluar("(async () => { await document.fonts.ready; await Promise.all([...document.images].map(img => img.decode().catch(() => {}))); return true; })()");
    const pdf = Buffer.from((await conexion.send('Page.printToPDF', {landscape: true, printBackground: true, preferCSSPageSize: true})).data, 'base64');
    assert.equal(pdf.subarray(0, 5).toString(), '%PDF-');
    assert.equal((pdf.toString('latin1').match(/\/Type\s*\/Page\b/g) || []).length, 7, 'El PDF debe contener las siete figuras completas.');
    sinErrores();
    console.log('OK navegador: tooltip durante morph Livewire, filtros bloqueados, LOV, cierre sin request, tres gráficos que filtran el mapa, filas adaptables, fotografía renovada al cambiar de taxón y PDF de siete figuras.');
} catch (error) {
    console.error('Diagnóstico del navegador:', JSON.stringify({excepciones, erroresLivewire, peticionesPendientes: peticiones.size}));
    if (conexion) {
        try {
            const estado = await conexion.send('Runtime.evaluate', {expression: `JSON.stringify({url: location.href, titulo: document.title, foco: {id: document.activeElement?.id, elemento: document.activeElement?.tagName}, busqueda: document.querySelector('#busqueda-explorador-taxonomico')?.value, texto: document.body.innerText.slice(-1800), tooltip: document.querySelector('.atlas-floating-tooltip')?.outerHTML, marcadores: [...document.querySelectorAll('.atlas-map [aria-label^="Ubicación original"]')].map(p=>({etiqueta:p.getAttribute('aria-label'), rect:p.getBoundingClientRect().toJSON()}))})`, returnByValue: true});
            console.error('Estado de la página:', estado.result.value);
        } catch { /* Se conserva la causa original si el navegador ya terminó. */ }
    }
    throw error;
} finally {
    conexion?.close();
    for (const proceso of procesos.reverse()) { if (proceso.exitCode === null) { proceso.kill(); await Promise.race([new Promise(resolve => proceso.once('exit', resolve)), demora(3000)]); } }
    try {
        if (existsSync(fixture)) execFileSync(php, ['tests/Browser/portal-fixture.php', 'limpiar', token, fixture], {cwd: proyecto, env: {...process.env, APP_CONFIG_CACHE: path.join(temporal, 'config.php')}, stdio: 'pipe'});
    } finally {
        const absoluto = path.resolve(temporal), raizTemporal = path.resolve(tmpdir()) + path.sep;
        assert.ok(absoluto.startsWith(raizTemporal) && path.basename(absoluto).startsWith('hubdigital-browser-'), 'Ruta temporal de limpieza inválida.');
        rmSync(absoluto, {recursive: true, force: true, maxRetries: 5, retryDelay: 200});
    }
}
