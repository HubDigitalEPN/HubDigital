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
const navegador = [
    path.join(process.env['PROGRAMFILES(X86)'] || 'C:/Program Files (x86)', 'Microsoft/Edge/Application/msedge.exe'),
    path.join(process.env.PROGRAMFILES || 'C:/Program Files', 'Microsoft/Edge/Application/msedge.exe'),
    path.join(process.env.PROGRAMFILES || 'C:/Program Files', 'Google/Chrome/Application/chrome.exe'),
    path.join(process.env.LOCALAPPDATA || '', 'Google/Chrome/Application/chrome.exe'),
].find(existsSync);
assert.ok(navegador, 'Falta Edge o Chrome: no se omite la prueba del tooltip/Livewire.');
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
    const puertoPhp = await puertoLibre(), puertoChrome = await puertoLibre();
    const origen = `http://127.0.0.1:${puertoPhp}`;
    const entorno = {...process.env, APP_URL: origen, APP_DEBUG: 'false', SESSION_DRIVER: 'file', SESSION_DOMAIN: '', SESSION_SECURE_COOKIE: 'false', CACHE_STORE: 'file',
        APP_CONFIG_CACHE: path.join(temporal, 'config.php'), APP_ROUTES_CACHE: path.join(temporal, 'routes.php')};
    const respuestaFixture = execFileSync(php, ['tests/Browser/portal-fixture.php', 'crear', token, fixture], {cwd: proyecto, env: entorno, encoding: 'utf8'});
    let datos;
    try { datos = JSON.parse(respuestaFixture); }
    catch (error) { throw new Error(`No se pudo preparar el fixture del navegador: ${respuestaFixture}`, {cause: error}); }
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
    const reposo = async () => { await demora(300); await esperar(async () => peticiones.size === 0 && await evaluar("!document.querySelector('.research-sidebar')?.inert"), 'finalización de Livewire'); sinErrores(); };
    const url = `${origen}/portal/catalogo?vista=mapa&ft=${encodeURIComponent(datos.taxonBusqueda)}`;
    // El GET entrega inmediatamente el estado de carga; los agregados se resuelven después.
    const primera = await esperar(async () => { try { const r = await fetch(url); return r.ok && await r.text(); } catch { return false; } }, 'primera respuesta del portal');
    assert.match(primera, /Cargando los registros públicos y sus filtros/);
    await conexion.send('Page.navigate', {url});
    await visible("document.querySelector('.atlas-dashboard .atlas-map .leaflet-pane') && document.querySelector('.research-check-list input') && window.Livewire");
    await reposo();
    assert.match(await evaluar('window.location.search'), /vista=mapa/);
    assert.match(await evaluar("document.querySelector('.atlas-collection-count').textContent"), /18 registros/);
    // Provincia y Localidad comparten LOV: abrir/cancelar no actualiza filtros; el botón principal conserva contraste.
    const peticionesAntesLov = totalPeticiones;
    for (const abrir of ['abrirProvincias()', 'abrirLocalidades()']) {
        await evaluar(`[...document.querySelectorAll('.research-sidebar button')].find(b => b.getAttribute('x-on:click') === ${JSON.stringify(abrir)}).click()`);
        await visible("document.querySelector('.research-locality-dialog[open] input[type=search]') === document.activeElement");
        assert.ok(await evaluar("(() => {const b=document.querySelector('.research-locality-dialog[open] .research-locality-apply'); const s=getComputedStyle(b); return s.color === 'rgb(255, 255, 255)' && s.backgroundColor !== 'rgba(0, 0, 0, 0)';})()"));
        await conexion.send('Input.dispatchKeyEvent', {type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27});
        await conexion.send('Input.dispatchKeyEvent', {type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27});
        await visible("!document.querySelector('.research-locality-dialog[open]')");
    }
    await reposo(); assert.equal(totalPeticiones, peticionesAntesLov);
    await evaluar("document.querySelector('.atlas-map-actions .atlas-panel-tools > button').click()");
    await evaluar("[...document.querySelectorAll('.atlas-map-actions [role=menuitem]')].find(b => b.textContent.includes('Alternar agrupaciones')).click()");
    await visible("document.querySelectorAll('.atlas-map [aria-label^=\"Ubicación original\"]').length === 3");
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

    // Una ubicación numerosa termina en familia; el popup busca más allá de su primera página.
    await evaluar("[...document.querySelectorAll('.atlas-map [aria-label^=\"Ubicación original\"]')].find(p=>p.getAttribute('aria-label').includes('12 registros')).dispatchEvent(new MouseEvent('click',{bubbles:true}))");
    await visible("document.querySelector('.atlas-cell-dialog[open] .atlas-record-lov-trigger')"); await reposo();
    assert.equal(await evaluar("document.querySelectorAll('.atlas-tree-section [data-rango=registro]').length"), 0);
    await visible("new Set([...document.querySelectorAll('.atlas-tree-section button')].map(n => Math.round(n.getBoundingClientRect().top))).size > 1");
    assert.ok(await evaluar("(() => {const region=document.querySelector('.atlas-tree-scroll'); return region.scrollHeight <= region.clientHeight + 2;})()"), 'La cadena hasta familia debe caber en el alto disponible.');
    await evaluar("document.querySelector('.atlas-record-lov-trigger').click()");
    await visible("document.querySelector('.atlas-record-lov[open] input') === document.activeElement");
    await evaluar(`(() => {const input=document.querySelector('.atlas-record-lov input'); input.value=${JSON.stringify(datos.codigos[11])}; input.dispatchEvent(new Event('input',{bubbles:true}));})()`);
    await visible(`document.querySelectorAll('.atlas-record-lov li button').length === 1 && document.querySelector('.atlas-record-lov ul')?.textContent.includes(${JSON.stringify(datos.codigos[11])})`); await reposo();
    assert.equal(await evaluar("document.querySelectorAll('.atlas-record-lov li button').length"), 1);
    await evaluar("document.querySelector('.atlas-record-lov li button').click()"); await reposo();
    await visible("!document.querySelector('.atlas-record-lov').open");
    assert.ok(await evaluar(`document.querySelector('.atlas-taxon-information').textContent.includes(${JSON.stringify(datos.codigos[11])})`));
    const antesDeCerrar = totalPeticiones;
    await evaluar("document.querySelector('.atlas-cell-header button[aria-label=\"Cerrar registros\"]').click()");
    await demora(700);
    assert.equal(totalPeticiones, antesDeCerrar, 'Cerrar el modal hizo una consulta innecesaria.');
    assert.equal(await evaluar("document.querySelector('.atlas-cell-dialog').open"), false);

    // Cinco ejemplares mantienen sus hojas y el linaje profundo sin apilarlas
    // en una única columna que obligue a desplazar verticalmente el modal.
    await evaluar("[...document.querySelectorAll('.atlas-map [aria-label^=\"Ubicación original\"]')].find(p=>p.getAttribute('aria-label').includes('5 registros')).dispatchEvent(new MouseEvent('click',{bubbles:true}))");
    await visible("document.querySelectorAll('.atlas-tree-section [data-rango=registro]').length === 5"); await reposo();
    assert.ok(await evaluar("(() => {const region=document.querySelector('.atlas-tree-scroll'); return region.scrollHeight <= region.clientHeight + 2;})()"), 'El linaje profundo con cinco ejemplares debe caber en el modal.');
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
        const ajuste = await evaluar(`(() => {const raiz=document.querySelector('.portal-records-viewport'); const nav=raiz.querySelector('.portal-records-pagination'); const r=nav.getBoundingClientRect(); return {filas:raiz.querySelectorAll('.portal-record-row').length, inferior:r.bottom, altura:window.innerHeight, posicion:getComputedStyle(nav).position};})()`);
        assert.ok(ajuste.inferior <= ajuste.altura + 2, 'La navegación quedó fuera de la pantalla.');
        assert.ok(['static', 'relative'].includes(ajuste.posicion), 'La navegación quedó flotante.'); cantidades.push(ajuste.filas);
        const acceso = await evaluar("(() => {const boton=[...document.querySelectorAll('.portal-records-viewport .portal-records-pagination button')].find(b=>b.textContent.includes('Siguiente')); const r=boton.getBoundingClientRect(); const puntos=[.2,.5,.9].map(f=>{const encima=document.elementFromPoint(r.left+r.width*f,r.top+r.height/2); return {libre:encima === boton || boton.contains(encima), elemento:encima?.className};}); return {puntos, boton:r.toJSON(), raiz:boton.closest('.portal-records-viewport').getBoundingClientRect().toJSON()};})()");
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
    console.log('OK navegador: tooltip durante morph Livewire, filtros bloqueados, LOV, cierre sin request, tres gráficos que filtran el mapa, filas adaptables, fotografía y PDF de siete figuras.');
} catch (error) {
    console.error('Diagnóstico del navegador:', JSON.stringify({excepciones, erroresLivewire, peticionesPendientes: peticiones.size}));
    if (conexion) {
        try {
            const estado = await conexion.send('Runtime.evaluate', {expression: `JSON.stringify({url: location.href, titulo: document.title, texto: document.body.innerText.slice(-1800), tooltip: document.querySelector('.atlas-floating-tooltip')?.outerHTML, marcadores: [...document.querySelectorAll('.atlas-map [aria-label^="Ubicación original"]')].map(p=>({etiqueta:p.getAttribute('aria-label'), rect:p.getBoundingClientRect().toJSON()}))})`, returnByValue: true});
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
