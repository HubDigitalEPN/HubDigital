/** Puerta obligatoria de crear-paquete-oci: navegador real, assets compilados y Livewire real. */
import assert from 'node:assert/strict';
import {spawn, execFileSync} from 'node:child_process';
import {existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync} from 'node:fs';
import {tmpdir} from 'node:os';
import path from 'node:path';
import net from 'node:net';
import {get} from 'node:http';
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
let procesoNavegador;
let entorno;
let respuestaInicial;
const excepciones = [], erroresLivewire = [], peticiones = new Set();
const demora = ms => new Promise(resolve => setTimeout(resolve, ms));
function obtenerLocal(url) {
    // Consumir toda la respuesta sin el parser fetch/Undici de Node 24:
    // https://github.com/nodejs/undici/issues/5360 aborta el proceso fuera de try/catch.
    return new Promise((resolve, reject) => {
        const peticion = get(url, {agent: false}, respuesta => {
            let texto = '';
            respuesta.setEncoding('utf8');
            respuesta.on('data', parte => { texto += parte; });
            respuesta.on('error', reject);
            respuesta.on('end', () => resolve({ok: respuesta.statusCode >= 200 && respuesta.statusCode < 300, status: respuesta.statusCode, tipo: respuesta.headers['content-type'], texto}));
        });
        peticion.setTimeout(60000, () => peticion.destroy(new Error('Tiempo agotado al consultar el servidor local.')));
        peticion.on('error', reject);
    });
}
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
    // La caché de archivos conserva agregados del catálogo. Compartir storage
    // con localhost o con otra ejecución mezcla taxones de bases distintas.
    const almacenamiento = path.join(temporal, 'storage');
    for (const directorio of ['app/private', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'document-processing']) {
        mkdirSync(path.join(almacenamiento, directorio), {recursive: true});
    }
    entorno = {...process.env, TURNSTILE_ENABLED: 'false', PORTAL_ETE_URL: 'http://127.0.0.1:' + puertoETE, APP_URL: origen, APP_DEBUG: 'false', SESSION_DRIVER: 'file', SESSION_DOMAIN: '', SESSION_SECURE_COOKIE: 'false', CACHE_STORE: 'file',
        LARAVEL_STORAGE_PATH: almacenamiento, VIEW_COMPILED_PATH: path.join(almacenamiento, 'framework/views'),
        HUBDIGITAL_TEMPORARY_DIRECTORY: path.join(almacenamiento, 'document-processing'),
        APP_CONFIG_CACHE: path.join(temporal, 'config.php'), APP_ROUTES_CACHE: path.join(temporal, 'routes.php')};
    const respuestaFixture = execFileSync(php, ['tests/Browser/portal-fixture.php', 'crear', token, fixture], {cwd: proyecto, env: entorno, encoding: 'utf8'});
    let datos;
    try { datos = JSON.parse(respuestaFixture); }
    catch (error) { throw new Error(`No se pudo preparar el fixture del navegador: ${respuestaFixture}`, {cause: error}); }
    const pythonETE = path.join(proyecto, '.local/ete-venv/Scripts/python.exe');
    assert.ok(existsSync(pythonETE), 'Falta el runtime ETE preparado por crear-paquete-oci.');
    const motorETE = abrirProceso(pythonETE, [path.join(proyecto, 'resources/python/portal_ete/server.py'), '--port', String(puertoETE)], {cwd: proyecto, env: {...entorno, QT_QPA_PLATFORM: 'offscreen'}});
    await esperar(async () => { motorETE.comprobar(); try { return (await obtenerLocal(`http://127.0.0.1:${puertoETE}/health`)).ok; } catch {return false;} }, 'Arranque de ETE');
    const servidor = abrirProceso(php, ['-S', `127.0.0.1:${puertoPhp}`, path.join(proyecto, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], {cwd: path.join(proyecto, 'public'), env: entorno});
    const chrome = abrirProceso(navegador, ['--headless=new', `--remote-debugging-port=${puertoChrome}`, '--remote-debugging-address=127.0.0.1', `--user-data-dir=${path.join(temporal, 'perfil')}`, '--no-first-run', '--no-default-browser-check', '--disable-gpu', 'about:blank']);
    procesoNavegador = chrome.proceso;
    const objetivos = await esperar(async () => {
        chrome.comprobar(); servidor.comprobar();
        try {
            const respuesta = await obtenerLocal(`http://127.0.0.1:${puertoChrome}/json/list`);
            if (!respuesta.ok) return null;
            const objetivos = JSON.parse(respuesta.texto);
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
    const respuestasFallidas = new Map();
    conexion.on('Network.responseReceived', ({requestId, response}) => {
        if (peticiones.has(requestId) && response.status >= 400) {
            erroresLivewire.push(response.status);
            respuestasFallidas.set(requestId, new URL(response.url).pathname);
        }
    });
    conexion.on('Network.loadingFinished', async ({requestId}) => {
        if (!respuestasFallidas.has(requestId)) return;
        try {
            const {body} = await conexion.send('Network.getResponseBody', {requestId});
            const datos = JSON.parse(body);
            console.error('Error de validación Livewire:', respuestasFallidas.get(requestId), JSON.stringify({message:datos.message, errors:datos.errors}));
        } catch { console.error('Respuesta Livewire fallida:', respuestasFallidas.get(requestId)); }
    });
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
        // o concluya el ajuste de filas iniciado por ResizeObserver. La cámara
        // también debe terminar antes de comprobar el rango que quedó visible.
        await esperar(async () => peticiones.size === 0 && await evaluar("!document.querySelector('.research-sidebar')?.inert && !document.querySelector('.collection-main')?.inert && !document.querySelector('.collection-workspace')?.classList.contains('is-updating') && [...document.querySelectorAll('.portal-records-viewport')].every(el => !window.Alpine?.$data(el)?.solicitando) && [...document.querySelectorAll('.portal-diversity-scene')].every(el => !el.checkVisibility() || el.dataset.animando !== 'true')"), 'finalización de Livewire, del ajuste de registros y de la cámara');
        sinErrores();
    };
    const url = `${origen}/portal/catalogo?vista=mapa&ft=${encodeURIComponent(datos.taxonBusqueda)}`;
    // El GET entrega el catálogo completo para que la PWA conserve contenido,
    // sin repetir una petición Livewire para resolver un placeholder.
    const primera = await esperar(async () => { try { const r = await obtenerLocal(url); respuestaInicial = {estado: r.status, tipo: r.tipo, bytes: Buffer.byteLength(r.texto), mapa: /atlas-dashboard/.test(r.texto), filtros: /research-phylum-tree/.test(r.texto), errorMapa: /mapa-error-titulo/.test(r.texto)}; return r.ok && r.texto; } catch { return false; } }, 'primera respuesta del portal');
    assert.ok(/atlas-dashboard/.test(primera) && /research-phylum-tree/.test(primera), 'La primera respuesta incluye mapa, paneles y filtros ya renderizados.');
    assert.ok(!/Cargando los registros públicos y sus filtros|wire:lazy/.test(primera), 'La apertura no depende de un placeholder ni una segunda petición de carga.');
    // Una descarga lenta del bundle no debe adelantar Alpine a sus componentes.
    await conexion.send('Fetch.enable', {patterns: [{urlPattern: '*build/assets/app-*.js', requestStage: 'Request'}]});
    conexion.on('Fetch.requestPaused', ({requestId}) => {
        setTimeout(() => conexion.send('Fetch.continueRequest', {requestId}).catch(() => {}), 300);
    });
    await conexion.send('Page.navigate', {url});
    await visible("document.querySelector('.atlas-dashboard .atlas-map .leaflet-pane') && document.querySelector('.research-check-list input') && window.Livewire");
    await conexion.send('Fetch.disable');
    await reposo();
    assert.match(await evaluar('window.location.search'), /vista=mapa/);
    assert.match(await evaluar("document.querySelector('.atlas-collection-count').textContent"), /^\(18\)$/);
    // El pie se alcanza completo y sus enlaces abren información sin perder el mapa.
    await evaluar('window.scrollTo(0, document.scrollingElement.scrollHeight)'); await reposo();
    assert.ok(await evaluar(`(() => {
        const footer=document.querySelector('.platform-footer'), r=footer.getBoundingClientRect();
        const actions=document.querySelector('.research-filter-actions').getBoundingClientRect();
        return r.bottom<=innerHeight+1 && actions.bottom<=r.top+1 && !footer.querySelector('time') &&
            [...footer.querySelectorAll('[data-platform-information]')].every(a=>{const b=a.getBoundingClientRect();return document.elementFromPoint(b.x+b.width/2,b.y+b.height/2)===a;});
    })()`), 'Limpiar filtros no tapa ninguno de los cinco enlaces al llegar al final.');
    const informacionPie = [
        ['privacidad', 'Política de privacidad', 'datos personales'],
        ['terminos', 'Términos de uso', 'CC BY 4.0'],
        ['licencia', 'Licencia', 'materiales de terceros'],
        ['citar', 'Cómo citar', 'MEPNINV00001'],
        ['creditos', 'Créditos', 'Hernán Troya'],
    ];
    // Livewire normaliza la URL inicial (%20 a +) antes de abrir el pie.
    const urlAntesInformacionPie = await evaluar('location.href');
    for (const [seccion,titulo,contenido] of informacionPie) {
        await evaluar(`document.querySelector('.platform-footer a[href$="/informacion/${seccion}"]').click()`);
        await visible(`document.querySelector('#platform-information-dialog:modal .platform-information-content').textContent.includes(${JSON.stringify(contenido)})`);
        assert.equal(await evaluar('location.href'),urlAntesInformacionPie,'Consultar información no abandona el catálogo ni sus filtros.');
        assert.equal(await evaluar("document.querySelector('#platform-information-title').textContent"),seccion==='creditos'?'Créditos y financiamiento':titulo);
        if(seccion==='creditos')assert.ok(await evaluar("(() => {const c=document.querySelector('.platform-information-content');return !c.querySelector('h2')&&c.textContent.includes('Financiamiento: Adrian Troya, Laboratorio de Invertebrados.');})()"),'Los créditos usan un solo título y el financiamiento solicitado.');
        assert.ok(await evaluar(`(() => {const r=document.querySelector('#platform-information-dialog').getBoundingClientRect();return r.width>=500&&r.width<=641&&r.height<=innerHeight*.76+1;})()`),'El modal tiene un tamaño medio y cabe en la pantalla.');
        await conexion.send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
        await conexion.send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
        await visible("!document.querySelector('#platform-information-dialog').open");
        assert.equal(await evaluar('document.activeElement?.textContent.trim()'),titulo,'Cerrar devuelve el foco al enlace invocador.');
    }
    await conexion.send('Emulation.setDeviceMetricsOverride', {width:390,height:844,deviceScaleFactor:1,mobile:true});
    await evaluar('window.scrollTo(0, document.scrollingElement.scrollHeight)'); await reposo();
    assert.ok(await evaluar(`document.documentElement.scrollWidth<=innerWidth+1 && [...document.querySelectorAll('.platform-footer-links a')].every(a=>{const r=a.getBoundingClientRect();return document.elementFromPoint(r.x+r.width/2,r.y+r.height/2)===a;})`),'En móvil tampoco tapa los enlaces el botón flotante del asistente.');
    await evaluar(`document.querySelector('.platform-footer a[href$="/informacion/terminos"]').click()`);
    await visible("document.querySelector('#platform-information-dialog:modal .platform-information-content').textContent.includes('CC BY 4.0')");
    assert.ok(await evaluar(`(() => {const d=document.querySelector('#platform-information-dialog'),r=d.getBoundingClientRect(),b=d.querySelector('.platform-information-content');b.scrollTop=b.scrollHeight;return r.width<=innerWidth-31&&r.height<=innerHeight*.76+1&&b.scrollTop>0;})()`),'El modal móvil mantiene márgenes y permite leer el texto hasta el final.');
    await evaluar("document.querySelector('[data-platform-information-close]').click()");
    await visible("!document.querySelector('#platform-information-dialog').open");
    await conexion.send('Emulation.setDeviceMetricsOverride', {width:1440,height:900,deviceScaleFactor:1,mobile:false});
    await evaluar('window.scrollTo(0, 0)'); await reposo();
    assert.ok(await evaluar(`!document.querySelector('.portal-curator-access') && ![...document.querySelectorAll('button')].some(b=>b.getAttribute('wire:click')?.startsWith('descargar') || b.getAttribute('x-on:click')==='pdf()')`),'El visitante consulta el catálogo sin controles de exportación ni acceso al panel.');
    assert.ok(await evaluar(`[...document.querySelectorAll('button')].some(b=>b.getAttribute('x-on:click')==='verRegistros(menuNodo)')`),'El visitante puede consultar los registros del árbol.');
    await conexion.send('Page.navigate',{url:origen+'/login'});
    await visible("document.querySelector('#hub-login-form') && window.Alpine");
    await evaluar(`(() => {const f=document.querySelector('#hub-login-form');f.elements.email.value=${JSON.stringify(datos.email)};f.elements.password.value=${JSON.stringify(datos.password)};f.requestSubmit();})()`);
    await visible("location.pathname==='/portal/catalogo' && document.querySelector('.portal-curator-access') && window.Livewire");
    assert.equal(await evaluar('location.search'),'?vista=mapa','El login curatorial vuelve al mapa.');
    await conexion.send('Page.navigate',{url});
    await visible("document.querySelector('.atlas-dashboard .atlas-map .leaflet-pane') && window.Livewire");await reposo();
    assert.ok(await evaluar(`Boolean(document.querySelector('.portal-curator-access') && document.querySelector('button[data-catalogo-descarga]'))`),'La sesión autorizada habilita exportaciones HTTP y el acceso al panel.');
    // La provincia se selecciona directamente en el dashboard, sin buscador.
    assert.equal(await evaluar("document.querySelectorAll('.research-locality-dialog').length + [...document.querySelectorAll('.research-sidebar button')].filter(b => b.getAttribute('x-on:click') === 'abrirProvincias()').length"), 0);
    assert.equal(await evaluar("document.querySelectorAll('#buscar-provincia-panel,.atlas-chart-data-toggle').length"), 0);
    await evaluar("[...document.querySelectorAll('[aria-labelledby=\"titulo-riqueza\"] .atlas-ranked-row')].find(b=>b.textContent.includes('Orellana')).click()");
    await visible("document.querySelector('#titulo-riqueza')?.textContent === 'Registros por localidad'"); await reposo();
    assert.equal(await evaluar("document.activeElement?.classList.contains('atlas-territory-back')"), true);
    assert.match(await evaluar("document.querySelector('.atlas-collection-count').textContent"), /^\(12\)$/);
    const peticionesAntesLocalidad = totalPeticiones;
    await evaluar("(() => {const input=document.querySelector('#buscar-localidad-panel'); input.value='browser uno'; input.dispatchEvent(new Event('input',{bubbles:true}));})()");
    await reposo(); assert.equal(totalPeticiones, peticionesAntesLocalidad);
    await evaluar("[...document.querySelectorAll('[aria-labelledby=\"titulo-riqueza\"] .atlas-ranked-row')].find(b=>b.textContent.includes('Lugar Browser Uno')).click()");
    await visible("document.querySelector('[aria-labelledby=\"titulo-riqueza\"] .atlas-ranked-row[aria-pressed=true]')"); await reposo();
    assert.ok((await evaluar('window.location.search')).includes('fg'));
    await evaluar("document.querySelector('.atlas-territory-back').click()");
    await visible("document.querySelector('#titulo-riqueza')?.textContent === 'Registros por provincia'"); await reposo();
    assert.equal(await evaluar("document.activeElement?.id"), 'titulo-riqueza');
    assert.match(await evaluar("document.querySelector('.atlas-collection-count').textContent"), /^\(18\)$/);
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
    assert.match(await evaluar("document.querySelector('.atlas-collection-count').textContent"), /^\(18\)$/);
    await conexion.send('Input.dispatchKeyEvent', {type: 'keyDown', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13});
    await conexion.send('Input.dispatchKeyEvent', {type: 'keyUp', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13});
    await visible("document.querySelector('.atlas-collection-count')?.textContent.trim()==='(1)'"); await reposo();
    await evaluar("[...document.querySelectorAll('.collection-active-filters button')].find(b=>b.getAttribute('wire:click')?.includes('filtroCatalogo')).click()");
    await visible("document.querySelector('.atlas-collection-count')?.textContent.trim()==='(18)'"); await reposo();
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
    assert.match(await evaluar("document.querySelector('.atlas-collection-count').textContent"), /^\(17\)$/);
    await mostrarTooltip();
    await evaluar(`document.querySelector('.research-check-list input[value=${JSON.stringify(datos.filos[0])}]').click()`);
    await reposo();
    assert.match(await evaluar("document.querySelector('.atlas-collection-count').textContent"), /^\(18\)$/);
    await mostrarTooltip();
    await conexion.send('Network.emulateNetworkConditions', {offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1});

    // Explorador izquierdo único: una raíz compartida con el grafo y su ficha.
    await evaluar("document.querySelector('button[aria-label=\"Árbol taxonómico\"]').click()");
    await visible("document.querySelector('#explorador-taxonomico[open] .portal-taxonomy-tree-pane')?.checkVisibility()"); await reposo();
    const catalogoExplorador = await evaluar("({total: Alpine.$data(document.querySelector('#explorador-taxonomico')).total})");
    assert.ok(catalogoExplorador.total >= datos.codigos.length, 'El explorador conserva las ramas del conjunto filtrado que contiene los registros de la fixture.');
    assert.equal(await evaluar("document.querySelector('#explorador-taxonomico > .taxonomy-explorer-header p')"), null, 'La cabecera conserva el título sin el subtítulo que consumía espacio vertical.');
    assert.ok(await evaluar("(() => {const d=document.querySelector('#explorador-taxonomico'),r=d.getBoundingClientRect();return d.matches(':modal') && Math.abs(r.width-innerWidth*.9)<=2 && Math.abs(r.height-innerHeight*.9)<=2 && d.querySelector('#filo-explorador-taxonomico').checkVisibility() && !d.querySelector('footer,.taxonomy-explorer-map,.taxonomy-explorer-representation,.taxonomy-navigator-sidebar,.taxonomy-navigator-fold');})()"), 'El modal conserva sus márgenes, selector y buscador y libera el ancho del lateral retirado.');
    assert.equal(await evaluar("document.querySelector('.portal-ete-controls,.taxonomy-navigator-sidebar > h3,.taxonomy-navigator-caption,.taxonomy-navigator-breadcrumb,.taxonomy-navigator-heading,.taxonomy-expand-all,.taxonomy-branch-select')"), null);
    assert.ok(await evaluar("document.querySelector('.taxonomy-phylotree-search #busqueda-explorador-taxonomico').checkVisibility() && document.querySelector('.portal-ete-host').checkVisibility()"));
    // La nueva presentación es la inicial. La extensa regresión de esferas que
    // sigue se ejecuta sobre la alternativa explícita, con sus aserciones intactas.
    await visible("document.querySelector('.portal-diversity-scene')?.dataset.frames > 0");
    assert.equal(await evaluar("document.querySelector('.portal-taxonomy-split').dataset.visualizacion"),'diversidad');
    assert.equal(await evaluar("document.querySelector('.portal-taxonomy-visualization').getAttribute('aria-pressed')"),'false');
    const raizDiversidad = await evaluar("document.querySelector('.portal-diversity-scene').dataset.raiz");
    assert.equal(await evaluar("document.querySelector('.portal-diversity-scene').dataset.camara"),'perspectiva','La vista inicial tiene perspectiva y geometría con volumen.');
    assert.equal(await evaluar("document.querySelector('.portal-diversity-scene').dataset.rangoDetalle"),'Orden');
    assert.deepEqual(await evaluar("[...document.querySelectorAll('.portal-diversity-legend button')].map(b=>b.textContent)"),['Filo','Clase','Orden','Suborden','Familia','Subfamilia','Tribu','Género','Especie'],'Todos los accesos de rango están visibles desde el inicio.');
    assert.ok(await evaluar("Number(document.querySelector('.portal-diversity-scene').dataset.ramasDibujadas)<160"),'La vista inicial no dibuja ramas de niveles cerrados.');
    await evaluar(`(() => {const etiqueta=document.querySelector('.portal-diversity-label.is-selected'),canvas=document.querySelector('.portal-diversity-canvas'),r=canvas.getBoundingClientRect();canvas.dispatchEvent(new PointerEvent('pointermove',{clientX:r.left+Number(etiqueta.dataset.puntoX)*r.width/canvas.clientWidth,clientY:r.top+Number(etiqueta.dataset.puntoY)*r.height/canvas.clientHeight,bubbles:true}));})()`);
    assert.ok(await evaluar("document.querySelector('.portal-diversity-tooltip').checkVisibility() && document.querySelector('.portal-diversity-tooltip strong').textContent===document.querySelector('.portal-three-title').textContent"),'Se puede identificar un nodo al señalarlo sin cambiar la raíz ni la ficha seleccionada.');
    const claseAbierta=await evaluar("(() => {const b=[...document.querySelectorAll('.portal-diversity-label')].find(e=>e.querySelector('small').textContent.startsWith('Clase'));const nombre=b.querySelector('strong').textContent;b.click();return nombre;})()");
    await visible("document.querySelector('.portal-diversity-scene')?.dataset.nombre==="+JSON.stringify(claseAbierta));await reposo();
    assert.equal(await evaluar("document.querySelector('.portal-diversity-label.is-selected strong').textContent"),claseAbierta,'Cambiar de raíz renueva el nombre científico del marcador y no conserva la etiqueta anterior.');
    assert.equal(await evaluar("document.querySelector('.portal-diversity-scene').dataset.rangoDetalle"),'Suborden','Pulsar una Clase abre automáticamente Orden y Suborden.');
    assert.ok(await evaluar("[...document.querySelectorAll('.portal-diversity-label small')].some(e=>e.textContent.startsWith('Suborden ('))"),'Los dos niveles descendientes contienen yemas visibles.');
    assert.ok(await evaluar("(() => {const c=document.querySelector('.portal-diversity-canvas'),e=document.querySelector('.portal-diversity-label.is-selected');return Math.abs(Number(e.dataset.puntoX)-c.clientWidth/2)<1&&Number(e.dataset.puntoY)>c.clientHeight*.65&&Number(e.dataset.puntoY)<c.clientHeight-45;})()"),'Al pulsar Insecta la raíz queda centrada horizontalmente, abajo y con espacio para su etiqueta.');
    assert.ok(await evaluar("[...document.querySelectorAll('.portal-diversity-label small')].every(e=>/^[^()]+ \\(\\d[\\d.]*\\)$/.test(e.textContent))"),'Raíces y yemas usan únicamente rango y cantidad entre paréntesis.');
    await evaluar("(() => {const d=Alpine.$data(document.querySelector('#explorador-taxonomico'));d.desplazamientoX=20;d.desplazamientoY=12;})()");
    await evaluar("document.querySelector('[aria-label=\"Acercar árbol\"]').click()");
    await visible("document.querySelector('.portal-diversity-scene').dataset.animando==='false'");
    await reposo();
    assert.ok(await evaluar("(() => {const d=document.querySelector('.portal-diversity-scene').dataset;return Math.abs(Number(d.baseCamara)/Number(d.distanciaCamara)-.8)<.001&&Number(d.enEncuadre)>1;})()"),'El acercamiento desplaza físicamente la cámara y conserva una rama visible.');
    assert.equal(await evaluar("document.querySelector('.portal-diversity-pages')"),null,'La exploración no divide el árbol en páginas.');
    assert.ok(await evaluar("document.querySelector('.portal-diversity-scene').classList.contains('is-expanded')"),'El primer zoom amplía el árbol automáticamente.');
    assert.ok(await evaluar(`(() => {const bs=[...document.querySelectorAll('.portal-diversity-fullscreen-controls button')],rs=bs.map(b=>b.getBoundingClientRect());return bs.length===4&&bs.every(b=>b.checkVisibility())&&!bs[0].disabled&&bs[0].getAttribute('aria-label').startsWith('Volver al padre')&&bs[1].getAttribute('aria-label').startsWith('Alejar')&&bs[2].getAttribute('aria-label').startsWith('Acercar')&&bs[3].getAttribute('aria-label').startsWith('Salir')&&rs.slice(1).every((r,i)=>rs[i].right<=r.left)&&rs.every(r=>r.top===rs[0].top);})()`),'Padre, menos, más y salir están accesibles y alineados en pantalla completa.');
    const posicionRaiz=()=>evaluar("(() => {const s=document.querySelector('.portal-diversity-scene');return {x:Number(s.dataset.raizX),y:Number(s.dataset.raizY),clave:s.dataset.raiz};})()");
    const raizAmpliada=await posicionRaiz();
    const comprobarAncla=async(antes)=>{const despues=await posicionRaiz();assert.equal(despues.clave,antes.clave);assert.ok(Math.hypot(despues.x-antes.x,despues.y-antes.y)<1,'La raíz conserva exactamente su posición mientras crecen las ramas. '+JSON.stringify({antes,despues}));};
    await evaluar("document.querySelector('[aria-label=\"Acercar árbol en pantalla completa\"]').click()");await visible("document.querySelector('.portal-diversity-scene').dataset.animando==='false'");
    assert.ok(Math.abs(Number(await evaluar("document.querySelector('.portal-diversity-scene').dataset.zoom"))-1.28)<.001);
    assert.ok(await evaluar("Number(document.querySelector('.portal-diversity-scene').dataset.enEncuadre)>0"),'El zoom de detalle conserva taxones en el encuadre.');
    await evaluar("document.querySelector('[aria-label=\"Alejar árbol en pantalla completa\"]').click()");await visible("document.querySelector('.portal-diversity-scene').dataset.animando==='false'");
    assert.ok(Math.abs(Number(await evaluar("document.querySelector('.portal-diversity-scene').dataset.zoom"))-.8)<.001,'Los dos controles de pantalla completa modifican el mismo zoom.');
    await comprobarAncla(raizAmpliada);
    await evaluar("document.querySelector('[aria-label=\"Volver al padre de la rama en pantalla completa\"]').click()");
    await visible("document.querySelector('.portal-diversity-scene')?.dataset.raiz==="+JSON.stringify(raizDiversidad));await reposo();
    assert.ok(await evaluar("document.querySelector('.portal-diversity-scene.is-expanded') && document.querySelector('[aria-label=\"Volver al padre de la rama en pantalla completa\"]').disabled"),'Volver al padre publicado conserva pantalla completa y se deshabilita al llegar al filo sin padre.');
    await evaluar("document.querySelector('.portal-diversity-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'Home',bubbles:true}))");await visible("document.querySelector('.portal-diversity-scene').dataset.animando==='false'");
    assert.equal(await evaluar("document.querySelector('.portal-diversity-scene').dataset.zoom"),'0.500');
    const raizGeneral=await posicionRaiz();
    for(const [desde,hasta]of[[.5,.6],[.6,.7],[.7,.8],[.8,.9],[.9,1]]){
        await evaluar(`document.querySelector('.portal-diversity-canvas').dispatchEvent(new WheelEvent('wheel',{deltaY:${-120*Math.log(hasta/desde)/Math.log(1.6)},bubbles:true,cancelable:true}))`);
        await visible("document.querySelector('.portal-diversity-scene').dataset.animando==='false'");await comprobarAncla(raizGeneral);
        assert.ok(Math.abs(Number(await evaluar("document.querySelector('.portal-diversity-scene').dataset.zoom"))-hasta)<.001);
        assert.ok(await evaluar("(() => {const d=document.querySelector('.portal-diversity-scene').dataset;return Math.abs(Number(d.baseCamara)/Number(d.distanciaCamara)-Number(d.zoom))<.001&&Number(d.ramasDibujadas)<160;})()"),'Cada valor fraccionario es el aumento físico de cámara y mantiene el presupuesto gráfico.');
    }
    await evaluar(`document.querySelector('.portal-diversity-canvas').dispatchEvent(new WheelEvent('wheel',{deltaY:${-120*Math.log(1.28)/Math.log(1.6)},bubbles:true,cancelable:true}))`);
    await visible("document.querySelector('.portal-diversity-scene').dataset.animando==='false'");
    assert.ok(await evaluar("Number(document.querySelector('.portal-diversity-scene').dataset.enEncuadre)>0"),'El acercamiento profundo sigue una yema real y no amplía espacio vacío.');
    await evaluar("document.querySelector('.portal-diversity-fullscreen-exit').click()");
    await visible("!document.querySelector('.portal-diversity-scene').classList.contains('is-expanded') && !document.fullscreenElement");await reposo();
    const raizNormal=await posicionRaiz();
    for (let i=0;i<9;i++) {await evaluar("document.querySelector('[aria-label=\"Acercar árbol\"]').click()");await reposo();await visible("document.querySelector('.portal-diversity-scene').dataset.animando==='false'");assert.ok(Number(await evaluar("document.querySelector('.portal-diversity-scene').dataset.enEncuadre"))>0,'El acercamiento conserva taxones en pantalla, también al llegar a especies.');}
    assert.equal(await evaluar("document.querySelector('.portal-diversity-scene').dataset.rangoDetalle"),'Especie','El zoom máximo conserva acceso a especies en el árbol original.');
    assert.equal((await posicionRaiz()).clave,raizNormal.clave);
    assert.ok(await evaluar("(() => {const d=document.querySelector('.portal-diversity-scene').dataset;return !document.querySelector('.portal-diversity-pages')&&Math.abs(Number(d.zoom)-Number(d.zoomMax))<.001&&Number(d.enEncuadre)>0&&[...document.querySelectorAll('.portal-diversity-label small')].some(e=>e.textContent.startsWith('Especie ('));})()"),'El límite mantiene especies visibles sin paginado ni reacomodo del árbol.');
    assert.ok(await evaluar("document.querySelector('[aria-label=\"Acercar árbol\"]').disabled"));
    await evaluar("(() => {const c=document.querySelector('.portal-diversity-canvas');for(let i=0;i<9;i++)c.dispatchEvent(new KeyboardEvent('keydown',{key:'+',bubbles:true}));})()");await reposo();
    await visible("document.querySelector('.portal-diversity-scene').dataset.animando==='false'");
    assert.equal((await posicionRaiz()).clave,raizNormal.clave);
    assert.ok(await evaluar("(() => {const d=document.querySelector('.portal-diversity-scene').dataset;return Math.abs(Number(d.zoom)-Number(d.zoomMax))<.001&&d.rangoDetalle==='Especie'&&Number(d.ramasDibujadas)<160&&Number(d.cacheTroncos)<=256&&Number(d.drawCalls)<=2;})()"),'Los intentos de acercamiento adicionales conservan el encuadre útil y el presupuesto gráfico.');
    await evaluar("document.querySelector('.portal-diversity-legend button[data-rango=\"familia\"]').click()");await reposo();
    assert.ok(await evaluar("Boolean(document.querySelector('.portal-diversity-scene.is-expanded')) && document.querySelector('.portal-diversity-scene').dataset.rangoDetalle==='Suborden'"),'Pulsar un rango abierto oculta ese rango y sus descendientes y entra en pantalla completa.');
    await evaluar("document.querySelector('.portal-diversity-legend button[data-rango=\"familia\"]').click()");await reposo();
    assert.equal(await evaluar("document.querySelector('.portal-diversity-scene').dataset.rangoDetalle"),'Familia');
    assert.ok(await evaluar("[...document.querySelectorAll('.portal-diversity-label small')].some(e=>e.textContent.startsWith('Familia ('))"),'El acceso de rango abre yemas reales de ese nivel.');
    assert.ok(await evaluar("document.querySelector('.portal-diversity-slider-panel').hidden"),'El deslizador permanece oculto mientras no se usa.');
    const indicadorZoom=await evaluar("(() => {const r=document.querySelector('.portal-diversity-zoom-value').getBoundingClientRect();return {x:r.left+r.width/2,y:r.top+r.height/2};})()");
    await conexion.send('Input.dispatchMouseEvent',{type:'mouseMoved',...indicadorZoom});
    await visible("!document.querySelector('.portal-diversity-slider-panel').hidden");
    await conexion.send('Input.dispatchMouseEvent',{type:'mouseMoved',x:2,y:2});
    await visible("document.querySelector('.portal-diversity-slider-panel').hidden");
    await evaluar("document.querySelector('.portal-diversity-zoom-value').click()");
    assert.ok(await evaluar("(() => {const s=document.querySelector('.portal-diversity-slider'),r=s.getBoundingClientRect();return s.checkVisibility()&&s.getAttribute('aria-orientation')==='vertical'&&r.height>r.width*3;})()"),'El indicador abre un control vertical accesible.');
    await conexion.send('Input.dispatchKeyEvent',{type:'keyDown',key:'End',code:'End',windowsVirtualKeyCode:35});await conexion.send('Input.dispatchKeyEvent',{type:'keyUp',key:'End',code:'End',windowsVirtualKeyCode:35});await reposo();
    await visible("document.querySelector('.portal-diversity-scene').dataset.animando==='false'");
    assert.equal(await evaluar("document.querySelector('.portal-diversity-scene').dataset.rangoDetalle"),'Especie','El deslizador llega al máximo físico y mantiene una especie visible.');
    assert.equal(await evaluar("document.activeElement.classList.contains('portal-diversity-slider')"),true,'El deslizador conserva el foco al ampliar.');
    await conexion.send('Input.dispatchKeyEvent',{type:'keyDown',key:'Home',code:'Home',windowsVirtualKeyCode:36});await conexion.send('Input.dispatchKeyEvent',{type:'keyUp',key:'Home',code:'Home',windowsVirtualKeyCode:36});await reposo();
    await visible("document.querySelector('.portal-diversity-scene').dataset.animando==='false'");
    assert.ok(await evaluar("(() => {const d=document.querySelector('.portal-diversity-scene').dataset;return Math.abs(Number(d.zoom)-Number(d.zoomMin))<.001&&Number(d.enEncuadre)>0;})()"),'El mínimo restaura un encuadre general visible.');
    await conexion.send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await conexion.send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    assert.ok(await evaluar("document.querySelector('.portal-diversity-slider-panel').hidden&&document.activeElement.classList.contains('portal-diversity-zoom-value')&&Boolean(document.querySelector('.portal-diversity-scene.is-expanded'))"),'Escape oculta el control, devuelve el foco al valor y conserva pantalla completa.');
    assert.ok(await evaluar("document.querySelector('[aria-label=\"Alejar árbol en pantalla completa\"]').disabled"));
    await evaluar("document.querySelector('.portal-diversity-legend button[data-rango=\"clase\"]').click()");await reposo();
    assert.equal(await evaluar("document.querySelector('.portal-diversity-legend button[data-rango=\"clase\"]').getAttribute('aria-pressed')"),'true','Clase conserva su acción de resaltar.');
    await evaluar("document.querySelector('.portal-diversity-legend button[data-rango=\"clase\"]').click();document.querySelector('.portal-diversity-fullscreen-exit').click()");
    await visible("!document.querySelector('.portal-diversity-scene').classList.contains('is-expanded') && !document.fullscreenElement");
    await evaluar("document.querySelector('.portal-diversity-canvas,.portal-three-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'Home',bubbles:true}))");await reposo();
    assert.equal(await evaluar("document.querySelector('.portal-diversity-scene').dataset.zoom"),'0.500');
    assert.equal(await evaluar("document.querySelector('.portal-diversity-scene').dataset.rangoDetalle"),'Orden','Alejar vuelve a ocultar los rangos posteriores.');
    const raizAntesRueda=await posicionRaiz();
    await evaluar(`(() => {const el=[...document.querySelectorAll('.portal-diversity-label')].find(e=>!e.classList.contains('is-selected')),canvas=document.querySelector('.portal-diversity-canvas'),r=canvas.getBoundingClientRect();canvas.dispatchEvent(new WheelEvent('wheel',{deltaY:-120,clientX:r.left+Number(el.dataset.puntoX)*r.width/canvas.clientWidth,clientY:r.top+Number(el.dataset.puntoY)*r.height/canvas.clientHeight,bubbles:true,cancelable:true}));})()`);
    await visible("document.querySelector('.portal-diversity-scene').dataset.animando==='false'");
    await comprobarAncla(raizAntesRueda);
    assert.ok(await evaluar("(() => {const d=document.querySelector('.portal-diversity-scene').dataset;return Math.abs(Number(d.zoom)-.8)<.001&&Math.abs(Number(d.baseCamara)/Number(d.distanciaCamara)-.8)<.001;})()"),'La rueda mueve realmente la cámara y conserva la raíz en la vista general.');
    assert.ok(await evaluar("!document.querySelector('.portal-diversity-scene').classList.contains('is-expanded')"),'La rueda respeta la salida voluntaria de pantalla completa.');
    await evaluar("document.querySelector('[aria-label=\"Alejar árbol\"]').click()");
    await visible("document.querySelector('.portal-diversity-scene').classList.contains('is-expanded') && document.querySelector('.portal-diversity-scene').dataset.animando==='false'");
    assert.equal(await evaluar("document.querySelector('.portal-diversity-scene').dataset.zoom"),'0.500','El botón menos abre pantalla completa y aplica su zoom físico.');
    await evaluar("document.querySelector('.portal-diversity-fullscreen-exit').click()");
    await visible("!document.querySelector('.portal-diversity-scene').classList.contains('is-expanded') && !document.fullscreenElement");
    await evaluar("document.querySelector('.portal-diversity-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'Home',bubbles:true}))");await reposo();
    assert.equal(await evaluar("document.querySelector('.portal-three-fit,.portal-branch-retry,.portal-diversity-guide')"),null,'Los controles y la ayuda visual retirados no ocupan espacio.');
    const estadoAntesDeGirar=await evaluar("({raiz:document.querySelector('.portal-diversity-scene').dataset.raiz,centro:document.querySelector('.portal-diversity-scene').dataset.centro,orientacion:document.querySelector('.portal-diversity-scene').dataset.orientacion})");
    const centroGiro=await evaluar("(() => {const r=document.querySelector('.portal-diversity-canvas').getBoundingClientRect();return {x:r.left+r.width*.5,y:r.top+r.height*.5};})()");
    await conexion.send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',buttons:1,clickCount:1,modifiers:2,...centroGiro});
    await conexion.send('Input.dispatchMouseEvent',{type:'mouseMoved',button:'left',buttons:1,modifiers:2,x:centroGiro.x+55,y:centroGiro.y+30});
    await conexion.send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',buttons:0,clickCount:1,modifiers:2,x:centroGiro.x+55,y:centroGiro.y+30});await reposo();
    const girado=await evaluar("({raiz:document.querySelector('.portal-diversity-scene').dataset.raiz,centro:document.querySelector('.portal-diversity-scene').dataset.centro,orientacion:document.querySelector('.portal-diversity-scene').dataset.orientacion,rotando:document.querySelector('.portal-diversity-scene').dataset.rotando})");
    assert.notEqual(girado.orientacion,estadoAntesDeGirar.orientacion,'Ctrl y arrastrar modifica los ejes espaciales.');
    assert.equal(girado.raiz,estadoAntesDeGirar.raiz,'El gesto de giro no selecciona otra rama.');
    assert.equal(girado.centro,estadoAntesDeGirar.centro,'El giro no se interpreta como desplazamiento.');assert.equal(girado.rotando,'false');
    await evaluar("document.querySelector('.portal-diversity-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowRight',ctrlKey:true,bubbles:true}))");await reposo();
    assert.notEqual(await evaluar("document.querySelector('.portal-diversity-scene').dataset.orientacion"),girado.orientacion,'El giro también es accesible con Ctrl y flechas.');
    await evaluar("document.querySelector('.portal-diversity-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'Home',bubbles:true}))");await reposo();
    assert.equal(await evaluar("document.querySelector('.portal-diversity-scene').dataset.orientacion"),estadoAntesDeGirar.orientacion,'Inicio restaura la perspectiva inicial y el zoom.');
    await evaluar("document.querySelector('.portal-taxonomy-visualization').click()");
    await visible("document.querySelector('.portal-three-scene')?.dataset.raiz==="+JSON.stringify(raizDiversidad));
    assert.equal(await evaluar("document.querySelector('.portal-diversity-scene')"),null,'Cambiar de presentación libera el renderizador anterior.');
    await evaluar("document.querySelector('.portal-taxonomy-visualization').click()");
    await visible("document.querySelector('.portal-diversity-scene')?.dataset.raiz==="+JSON.stringify(raizDiversidad));
    assert.equal(await evaluar("document.querySelector('.portal-three-scene')"),null);
    await evaluar("document.querySelector('.portal-taxonomy-visualization').click()");
    await visible("document.querySelector('.portal-three-scene')?.dataset.raiz==="+JSON.stringify(raizDiversidad));
    async function buscarEnArbol(texto) {
        // La apertura del modal y el dibujo ETE terminan después del morph de
        // Livewire. Esperar el estado real evita interactuar durante la carga.
        await visible("(() => {const d=Alpine.$data(document.querySelector('#explorador-taxonomico'));return d.abierto && d.listo && !d.buscando && !d.visorCargando && !d.aplicando;})()");
        await evaluar("(() => {const b=document.querySelector('#busqueda-explorador-taxonomico');b.focus();b.value=" + JSON.stringify(texto) + ";b.dispatchEvent(new Event('input',{bubbles:true}));})()");
        await visible("document.querySelector('#sugerencias-explorador [role=option]')"); await reposo();
    }
    async function asegurarETEEstable() {
        const muestras = await evaluar(`new Promise(resolve => {
            const fin = performance.now() + 600, muestras = [];
            function medir() {
                const host = document.querySelector('.portal-ete-host'), stage = host.querySelector('.portal-branch-tree');
                const h = host.getBoundingClientRect(), s = stage.getBoundingClientRect();
                muestras.push([h.width,h.height,s.left,s.top,s.width,s.height,host.scrollLeft,host.scrollTop]);
                if (performance.now() < fin) requestAnimationFrame(medir); else resolve(muestras);
            }
            requestAnimationFrame(medir);
        })`);
        assert.ok(muestras.length >= 3);
        assert.ok(muestras.every(muestra => muestra.every((valor, i) => Math.abs(valor - muestras[0][i]) <= .5)), 'El árbol y sus barras permanecen estables tras terminar el dibujo.');
    }
    const raizETE = ".portal-branch-row[aria-label^=" + JSON.stringify('Browsersuborder' + token + '0,') + "]";
    await buscarEnArbol('FiloBrowser' + token);
    assert.match(await evaluar("document.querySelector('.atlas-collection-count').textContent"), /^\(18\)$/);
    await evaluar("document.querySelector('#sugerencias-explorador button').click()");
    await visible("document.querySelector('.portal-taxonomy-branch-pane')?.dataset.nombre===" + JSON.stringify('FiloBrowser' + token));
    await visible("document.querySelector('.portal-three-scene')?.dataset.nombre===" + JSON.stringify('FiloBrowser' + token));
    assert.ok(await evaluar("(() => {const s=document.querySelector('.portal-three-scene');return s.dataset.target==='0.00,0.00,0.00' && s.dataset.zoom==='1.000' && s.dataset.pulsando==='true';})()"), 'Buscar convierte el resultado en raíz, lo centra y activa su pulso.');
    assert.equal(await evaluar("document.querySelectorAll('.taxonomy-explorer-pill').length"), 0, 'Buscar localiza un taxón sin convertirlo en filtro del catálogo.');
    assert.equal(await evaluar("document.querySelector('.taxonomy-search-token span:first-child').textContent"), 'FiloBrowser' + token, 'El ámbito permanece dentro del buscador, con su equis.');
    assert.equal(await evaluar("Alpine.$data(document.querySelector('#explorador-taxonomico')).total"), catalogoExplorador.total, 'Buscar en el árbol conserva el total del conjunto filtrado del portal.');
    async function navegarInicial(nombre) {
        await evaluar("(() => {const d=Alpine.$data(document.querySelector('#explorador-taxonomico'));return d.irARama(d.nodos.find(n=>n.nombre===" + JSON.stringify(nombre) + "));})()");
        await visible(nombre === 'FiloBrowser' + token
            ? "document.querySelector('.portal-three-scene')?.dataset.raiz===document.querySelector('.portal-taxonomy-tree-pane')?.dataset.raiz"
            : "document.querySelector('.portal-three-scene')?.dataset.nombre===" + JSON.stringify(nombre));
        await visible("document.querySelector('.portal-ete-host').getAttribute('aria-busy')==='false'");
    }
    await navegarInicial('Browserclass' + token + '0');
    await evaluar("document.querySelector('.portal-three-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'PageDown',bubbles:true}))");
    await visible("document.querySelector('.portal-three-instructions').textContent.includes(" + JSON.stringify('Browserorder' + token + '0') + ")");
    await evaluar("document.querySelector('.portal-three-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}))");
    await visible("document.querySelector('.portal-three-scene')?.dataset.nombre===" + JSON.stringify('Browserorder' + token + '0'));
    assert.notEqual(await evaluar("document.querySelector(" + JSON.stringify(raizETE) + ")"), null, 'La raíz 3D despliega sus descendientes en el explorador izquierdo.');
    assert.ok(await evaluar("(() => {const d=Alpine.$data(document.querySelector('#explorador-taxonomico')),s=document.querySelector('.portal-taxonomy-tree-pane');return s.dataset.raiz===document.querySelector('.portal-three-scene').dataset.raiz && [...s.querySelectorAll('[data-taxon-padre]')].every(n=>d.nodos.find(t=>t.clave===n.dataset.taxonClave)?.padre===n.dataset.taxonPadre);})()"), 'La raíz y los padres del explorador izquierdo corresponden al grafo y al catálogo publicado.');
    assert.equal(await evaluar("document.querySelector('.taxonomy-navigator-tree')"), null);
    assert.ok(await evaluar("document.querySelectorAll('.portal-taxonomy-tree-pane .portal-branch-row[aria-expanded=true]').length>0"), 'La rama abre sus carpetas desde el inicio.');
    await evaluar("document.querySelector('.portal-three-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'PageDown',bubbles:true}))");
    await visible("document.querySelector('.portal-three-instructions').textContent.includes(" + JSON.stringify('Browsersuborder' + token + '0') + ")");
    await evaluar("document.querySelector('.portal-three-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}))");
    await visible("document.querySelector('.portal-taxonomy-branch-pane').dataset.nombre===" + JSON.stringify('Browsersuborder' + token + '0'));
    await visible("document.querySelector('.portal-three-scene').dataset.nombre===" + JSON.stringify('Browsersuborder' + token + '0'));
    assert.equal(await evaluar("document.querySelector('.portal-three-scene').dataset.nombre"), 'Browsersuborder' + token + '0', 'Seleccionar una esfera la convierte en raíz del grafo y de su ficha.');
    assert.equal(await evaluar("document.querySelector('.portal-branch-eyebrow,.portal-branch-title,.portal-branch-meta')"), null, 'La ficha no repite el encabezado del taxón.');
    assert.equal(await evaluar("[...document.querySelectorAll('.portal-taxonomy-branch-pane button')].some(b => /^(Expandir|Contraer)$/.test(b.textContent.trim()))"), false, 'La ficha no conserva los botones Expandir/Contraer.');
    assert.equal(await evaluar("document.querySelector('.portal-branch-info,.portal-taxonomy-branch-pane [role=tree]')"), null, 'La ficha muestra texto y foto, sin botón Información ni un segundo árbol.');
    assert.notEqual(await evaluar("document.querySelector(" + JSON.stringify(raizETE) + ")"), null);
    assert.ok(await evaluar("(() => {const d=Alpine.$data(document.querySelector('#explorador-taxonomico'));return [...document.querySelectorAll('.portal-branch-row[data-taxon-padre]')].every(e=>d.nodos.find(n=>n.clave===e.dataset.taxonClave)?.padre===e.dataset.taxonPadre);})()"), 'Cada fila sigue su padre publicado.');
    await navegarInicial('Browsersuborder' + token + '0'); await reposo();
    await visible("document.querySelector('.portal-taxonomy-tree-pane').dataset.filas===document.querySelector('.portal-taxonomy-tree-pane').dataset.nodos && document.querySelector('.portal-ete-host').getAttribute('aria-busy')==='false'");
    assert.ok(await evaluar("document.querySelector('.portal-taxonomy-tree-pane')?.checkVisibility() && ![...document.querySelectorAll('.taxonomy-explorer-error')].some(e=>e.checkVisibility())"));
    assert.ok(await evaluar("[...document.querySelectorAll('.portal-branch-name')].every(n=>parseFloat(getComputedStyle(n).fontSize)>=12)"), 'Los nombres del explorador permanecen legibles.');
    assert.ok(await evaluar("(() => {const h=document.querySelector('.portal-taxonomy-tree-pane'),r=h.getBoundingClientRect(),n=h.querySelector('.portal-branch-row').getBoundingClientRect();return n.left>=r.left && n.right<=r.right && n.top>=r.top && n.bottom<=r.bottom && h.dataset.filas===h.dataset.nodos;})()"), 'La raíz queda visible, sin recortes horizontales y con todos los niveles abiertos.');
    await asegurarETEEstable();
    const anchoAjustado = await evaluar("document.querySelector('.portal-branch-tree').getBoundingClientRect().width");
    await navegarInicial('FiloBrowser' + token);
    assert.ok(await evaluar("document.querySelector('.portal-three-scene').dataset.raiz===document.querySelector('.portal-taxonomy-tree-pane').dataset.raiz"), 'El primer nodo elegido es la raíz tanto del grafo como del explorador.');
    await evaluar("document.querySelector('.taxonomy-navigator-zoom [aria-label=\"Acercar árbol\"]').click()"); await reposo();
    assert.ok(Math.abs(Number(await evaluar("document.querySelector('.portal-three-scene').dataset.zoom")) - 1.25) < .01);
    assert.ok(Math.abs(await evaluar("document.querySelector('.portal-branch-tree').getBoundingClientRect().width") - anchoAjustado) < 1, 'El zoom de la esfera conserva el tamaño legible del explorador.');
    await asegurarETEEstable();
    await evaluar("document.querySelector('.taxonomy-navigator-zoom [aria-label=\"Alejar árbol\"]').click()"); await reposo();
    assert.ok(Math.abs(await evaluar("document.querySelector('.portal-branch-tree').getBoundingClientRect().width") - anchoAjustado) < 1);
    for (const factor of [.8, .64]) {
        await evaluar("document.querySelector('[aria-label=\"Alejar árbol\"]').click()"); await reposo();
        assert.ok(Math.abs(Number(await evaluar("document.querySelector('.portal-three-scene').dataset.zoom")) - factor) < .001, 'Alejar funciona desde la vista inicial de la esfera.');
    }
    await evaluar("document.querySelector('.portal-diversity-canvas,.portal-three-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'Home',bubbles:true}))"); await reposo();
    for (let repeticion = 0; repeticion < 2; repeticion++) {
        await navegarInicial('Browsergenus' + token + '0'); await reposo();
        await evaluar("document.querySelector('.taxonomy-navigator-zoom [aria-label=\"Acercar árbol\"]').click()");
        await navegarInicial(datos.taxonBusqueda + ' alfa'); await reposo();
        assert.ok(await evaluar("document.querySelector('.portal-three-scene').dataset.nodos==='1' && document.querySelector('.portal-taxonomy-tree-pane').dataset.nodos==='1'"), 'Una hoja elegida muestra su única esfera y su fila raíz, sin hijos de otra rama.');
        await navegarInicial('Browsersuborder' + token + '0'); await reposo();
        assert.ok(Math.abs(await evaluar("document.querySelector('.portal-branch-tree').getBoundingClientRect().width") - anchoAjustado) < 1, 'Volver a la rama restaura su vista inicial legible.');
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
    assert.match(await evaluar("document.querySelector('.atlas-collection-count').textContent"), /^\(18\)$/);
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
    async function menuTaxon(selector = seleccionadoETE) {
        await evaluar("document.querySelector(" + JSON.stringify(selector) + ").scrollIntoView({block:'center',inline:'nearest'})");
        const posicion = await evaluar("(() => {const n=document.querySelector(" + JSON.stringify(selector) + "),r=n.getBoundingClientRect(),d=n.closest('.portal-diversity-scene')?.dataset;return d?{x:r.left+Number(d.raizX)*r.width/n.clientWidth,y:r.top+Number(d.raizY)*r.height/n.clientHeight}:{x:r.left+r.width/2,y:r.top+r.height/2};})()");
        assert.ok(await evaluar("(() => {const n=document.querySelector(" + JSON.stringify(selector) + "),p=document.elementFromPoint(" + posicion.x + "," + posicion.y + ");return n===p || n.contains(p);})()"), 'El menú se abre mediante un clic real sobre el nodo visible.');
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
    const filaSeleccionadaETE = '.portal-taxonomy-tree-pane .portal-branch-row[aria-selected=true]';
    await menuTaxon(filaSeleccionadaETE);
    await evaluar("[...document.querySelectorAll('.taxonomy-explorer-context-menu button')].find(b=>b.textContent==='Ver registros').click()");
    await visible("document.querySelector('#registros-taxon-explorador[open] .portal-record-row')");
    await evaluar("document.querySelector('#registros-taxon-explorador .taxonomy-explorer-close').click()");
    await visible("!document.querySelector('#registros-taxon-explorador').open && document.activeElement.matches(" + JSON.stringify(filaSeleccionadaETE) + ")");
    await menuTaxon();
    await evaluar("[...document.querySelectorAll('.taxonomy-explorer-context-menu button')].find(b=>b.textContent==='Ver en el mapa').click()");
    await visible("!document.querySelector('#explorador-taxonomico').open && document.activeElement.classList.contains('atlas-map')"); await reposo();
    assert.equal(await evaluar("new URL(location.href).searchParams.get('fti')"), datos.especies[0]);
    assert.match(await evaluar("document.querySelector('.atlas-collection-count').textContent"), /^\(17\)$/);
    await evaluar("[...document.querySelectorAll('.collection-active-filters button')].find(b=>b.getAttribute('wire:click')?.includes('filtroTaxonId')).click()"); await reposo();
    await evaluar("(() => {const b=document.querySelector('input[placeholder=\"Nombre científico en cualquier rango\"]');b.focus();b.value=" + JSON.stringify(datos.taxonBusqueda) + ";b.dispatchEvent(new Event('input',{bubbles:true}));b.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));})()"); await reposo();
    await visible("document.querySelector('.atlas-collection-count').textContent.trim()==='(18)'");
    await evaluar("document.querySelector('button[aria-label=\"Árbol taxonómico\"]').click()");
    await visible("document.querySelector('#explorador-taxonomico[open] .portal-taxonomy-tree-pane')"); await reposo();
    assert.equal(await evaluar("document.querySelectorAll('.taxonomy-explorer-pill').length"), 0);
    assert.equal(await evaluar("Alpine.$data(document.querySelector('#explorador-taxonomico')).total"), catalogoExplorador.total, 'Reabrir el explorador conserva el mismo conjunto filtrado que el mapa.');
    await conexion.send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false}); await reposo();
    const antesDeAjustar = totalPeticiones;
    await evaluar("document.querySelector('.portal-diversity-canvas,.portal-three-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'Home',bubbles:true}))");
    await visible("Alpine.$data(document.querySelector('#explorador-taxonomico')).ajustado && document.querySelector('.portal-three-scene').dataset.zoom==='1.000'");
    assert.ok(await evaluar("(() => {const l=document.querySelector('.portal-ete-host');return l.scrollHeight<=l.clientHeight+1 && l.scrollWidth<=l.clientWidth+1;})()"));
    assert.equal(totalPeticiones, antesDeAjustar);
    assert.ok(await evaluar("(() => {const d=Alpine.$data(document.querySelector('#explorador-taxonomico'));return d.filtrosArbol.length===0 && d.nodos.some(n=>n.nombre==='FiloBrowser" + token + "');})()"), 'Cerrar y reabrir conserva las ramas del conjunto filtrado sin añadir filtros internos del árbol.');
    const antesDeEscape = totalPeticiones;
    await conexion.send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await conexion.send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await visible("!document.querySelector('#explorador-taxonomico').open && document.activeElement.getAttribute('aria-label')==='Árbol taxonómico'");
    assert.equal(totalPeticiones, antesDeEscape);
    await evaluar("document.querySelector('button[aria-label=\"Árbol taxonómico\"]').click()");
    await visible("document.querySelector('#explorador-taxonomico[open] .portal-taxonomy-tree-pane')"); await reposo();
    const fondoExplorador = await evaluar("(() => {const r=document.querySelector('#explorador-taxonomico').getBoundingClientRect();return {x:r.left-8,y:r.top+20};})()");
    await conexion.send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',clickCount:1,...fondoExplorador});
    await conexion.send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',clickCount:1,...fondoExplorador});
    await visible("!document.querySelector('#explorador-taxonomico').open && document.activeElement.getAttribute('aria-label')==='Árbol taxonómico'");
    await evaluar("document.querySelector('button[aria-label=\"Árbol taxonómico\"]').click()");
    await visible("document.querySelector('#explorador-taxonomico[open] .portal-taxonomy-tree-pane')"); await reposo();
    assert.ok(await evaluar(`(() => {
        const barra=document.querySelector('.taxonomy-navigator-zoom'),botones=[...barra.querySelectorAll('button')],rects=botones.map(b=>b.getBoundingClientRect()),visualizacion=barra.querySelector('.portal-taxonomy-visualization');
        return botones.length===5 && botones.at(-2).classList.contains('portal-three-maximize') && botones.at(-1).classList.contains('portal-taxonomy-visualization') && !barra.querySelector('.portal-three-fit')
            && rects.every(r=>r.width===40 && r.height===40 && Math.abs(r.top-rects[0].top)<1)
            && visualizacion.getBoundingClientRect().height===40 && visualizacion.textContent.trim()==='' && visualizacion.getAttribute('aria-label').includes('Visualización') && visualizacion.hasAttribute('aria-pressed')
            && !document.querySelector('.taxonomy-explorer-header .portal-three-maximize');
    })()`), 'Pantalla completa y cambio de visualización usan sólo iconos; los cinco controles tienen medidas y alineación iguales.');
    assert.ok(await evaluar("new Set([...document.querySelector('.portal-three-legend').children].map(n=>Math.round(n.getBoundingClientRect().top))).size===1"), 'La leyenda utiliza una sola fila para reservar altura al grafo.');
    const antesDePantallaYCerrar = totalPeticiones;
    await navegarInicial('FiloBrowser' + token);
    const raizAntesDePantalla = await evaluar("document.querySelector('.portal-three-scene').dataset.raiz");
    const controlPantalla = await evaluar("(() => {const r=document.querySelector('[aria-label=\"Ver visualizador en pantalla completa\"]').getBoundingClientRect();return {x:r.left+r.width/2,y:r.top+r.height/2};})()");
    await conexion.send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',clickCount:1,...controlPantalla});
    await conexion.send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',clickCount:1,...controlPantalla});
    await visible("document.fullscreenElement===document.querySelector('.portal-three-scene')");
    assert.equal(await evaluar("document.querySelector('.portal-three-scene').dataset.raiz"), raizAntesDePantalla);
    await conexion.send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await conexion.send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await visible("!document.fullscreenElement && document.querySelector('#explorador-taxonomico').open");
    await visible("document.querySelector('.portal-ete-host').checkVisibility() && document.querySelector('.portal-taxonomy-tree-pane') && !document.querySelector('.taxonomy-navigator-sidebar')");
    await evaluar("document.querySelector('#explorador-taxonomico .taxonomy-explorer-close').click()");
    await visible("!document.querySelector('#explorador-taxonomico').open && document.activeElement.getAttribute('aria-label')==='Árbol taxonómico'");
    assert.equal(totalPeticiones, antesDePantallaYCerrar);
    await conexion.send('Emulation.setDeviceMetricsOverride',{width:390,height:700,deviceScaleFactor:1,mobile:true});
    await evaluar("document.querySelector('button[aria-label=\"Árbol taxonómico\"]').click()");
    await visible("document.querySelector('#explorador-taxonomico[open] .portal-taxonomy-tree-pane').checkVisibility()"); await reposo();
    assert.ok(await evaluar("(() => {const d=document.querySelector('#explorador-taxonomico'),r=d.getBoundingClientRect(),cerrar=d.querySelector('.taxonomy-explorer-close').getBoundingClientRect(),pantalla=d.querySelector('[aria-label=\"Ver visualizador en pantalla completa\"]').getBoundingClientRect();return Math.abs(r.width-innerWidth*.9)<=2 && Math.abs(r.height-innerHeight*.9)<=2 && cerrar.bottom<=r.bottom && pantalla.left>=r.left && pantalla.right<=r.right && d.querySelector('.taxonomy-navigator-main').clientHeight>=150;})()"));
    await evaluar("document.querySelector('#explorador-taxonomico .taxonomy-explorer-close').click()");
    await visible("!document.querySelector('#explorador-taxonomico').open");
    await conexion.send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false}); await reposo();

    // Ramas masivas: jerarquía completa a la izquierda con DOM acotado; sin mutar la base.
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
    await visible("document.querySelector('.portal-three-scene')?.dataset.raiz==='masivo' && document.querySelector('.portal-three-scene').dataset.nodos==='252'");
    await navegarInicial('Familia A');
    await visible("document.querySelector('.portal-three-scene')?.dataset.raiz==='familia-a' && document.querySelector('.portal-three-scene').dataset.nodos==='123'");
    for (const familia of ['Familia A', 'Familia B']) {
        await navegarInicial(familia); await reposo();
        assert.equal(await evaluar("document.querySelector('.portal-three-scene').dataset.relaciones"), '122');
        assert.ok(await evaluar("(() => {const h=document.querySelector('.portal-taxonomy-tree-pane');return h.dataset.raiz===document.querySelector('.portal-three-scene').dataset.raiz && h.dataset.nodos==='123' && h.dataset.filas==='123' && Number(h.dataset.dibujados)<30;})()"), 'Los 122 hijos quedan abiertos en el único explorador, sin crear 123 elementos del DOM.');
        assert.equal(await evaluar("document.querySelector('.portal-branch-count').textContent"), '1', 'La cifra muestra registros publicados, no cantidad de hijos.');
    }
    assert.ok(await evaluar(`(() => {
        const d=Alpine.$data(document.querySelector('#explorador-taxonomico')),s=document.querySelector('.portal-taxonomy-tree-pane');
        return [...s.querySelectorAll('.portal-branch-row[data-taxon-padre]')].every(e=>d.nodos.find(n=>n.clave===e.dataset.taxonClave)?.padre===e.dataset.taxonPadre)
            && document.querySelector('.portal-three-canvas').checkVisibility()
            && !document.querySelector('.portal-three-label,.portal-three-branches,.portal-taxonomy-branch-pane [role=tree]')
            && document.querySelector('.portal-three-context').closest('.taxonomy-navigator-zoom')
            && [...document.querySelectorAll('.portal-three-legend-item')].map(n=>n.textContent).join(',')==='Familia,Especie';
    })()`), 'La rama respeta sus padres, muestra una leyenda de rangos y no superpone nombres ni repite el árbol a la derecha.');
    await evaluar("document.querySelector('.portal-tree-maximize').click()"); await reposo();
    assert.ok(await evaluar("document.querySelector('.portal-taxonomy-split').classList.contains('has-expanded-tree') && !document.querySelector('.portal-taxonomy-three-pane').checkVisibility() && document.querySelector('.portal-three-scene').dataset.rotando==='false'"), 'Ampliar el árbol oculta el grafo y detiene su animación.');
    const framesOcultos = await evaluar("document.querySelector('.portal-three-scene').dataset.frames");
    await demora(450);
    assert.equal(await evaluar("document.querySelector('.portal-three-scene').dataset.frames"), framesOcultos, 'El renderer no consume fotogramas mientras se navega sólo el árbol.');
    await evaluar("document.querySelector('.portal-branch-row[data-taxon-clave=\"b-0\"]').click()"); await reposo();
    assert.ok(await evaluar("document.querySelector('.portal-taxonomy-tree-pane').dataset.raiz==='b-0' && document.querySelector('.portal-taxonomy-tree-pane').dataset.nodos==='1' && document.querySelector('.portal-taxonomy-branch-pane').dataset.raiz==='b-0' && !document.querySelector('.portal-taxonomy-three-pane').checkVisibility()"), 'Elegir una hoja en el árbol ampliado actualiza raíz y ficha sin salir del modo ampliado.');
    await evaluar("document.querySelector('.portal-tree-maximize').click()");
    await visible("document.querySelector('.portal-three-scene').dataset.raiz==='b-0' && document.querySelector('.portal-three-scene').dataset.nodos==='1' && document.querySelector('.portal-taxonomy-three-pane').checkVisibility()");
    await navegarInicial('Familia B'); await reposo();
    const altoCanvasAntes = await evaluar("document.querySelector('.portal-three-canvas').getBoundingClientRect().height");
    await evaluar("document.querySelector('.portal-three-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'PageDown',bubbles:true}))"); await reposo();
    assert.equal(await evaluar("document.querySelector('.portal-three-canvas').getBoundingClientRect().height"), altoCanvasAntes, 'El linaje largo de una especie no desplaza las esferas bajo el puntero.');
    await evaluar("document.querySelector('.portal-three-legend-item[data-rango=\"especie\"]').dispatchEvent(new PointerEvent('pointerenter'))"); await reposo();
    assert.ok(await evaluar("document.querySelector('.portal-three-scene').dataset.nodosIluminados==='123' && document.querySelector('.portal-three-scene').dataset.ramasIluminadas==='122'"), 'La leyenda ilumina cada especie y su conexión completa hasta la raíz familiar.');
    await evaluar("document.querySelector('.portal-three-legend-item[data-rango=\"especie\"]').click()"); await reposo();
    await visible("document.querySelector('.portal-three-scene').dataset.rangoFiltrado==='especie' && document.querySelector('.portal-three-scene').dataset.indexados==='123'");
    assert.ok(await evaluar("(() => {const s=document.querySelector('.portal-three-scene');return s.dataset.raiz==='familia-b' && s.dataset.nodos==='123' && s.dataset.nodosTotales==='123' && s.dataset.rangosVisibles==='familia,especie' && s.dataset.seleccionVisible==='true' && document.querySelector('.portal-three-legend-item[data-rango=\"especie\"]').getAttribute('aria-pressed')==='true' && !document.querySelector('.portal-three-rank-picker');})()"), 'La leyenda conserva la esfera raíz seleccionable y todas las especies de la rama.');
    assert.equal(await evaluar("document.querySelector('.portal-taxonomy-tree-pane').dataset.filas"), '123', 'Filtrar un rango conserva todos los hijos del explorador izquierdo.');
    await evaluar("document.querySelector('.portal-three-legend-item[data-rango=\"familia\"]').click()");
    await visible("document.querySelector('.portal-three-scene').dataset.rangoFiltrado==='familia' && document.querySelector('.portal-three-scene').dataset.nodos==='1'");
    assert.equal(await evaluar("document.querySelector('.portal-three-scene').dataset.rangosVisibles"), 'familia');
    await evaluar("document.querySelector('.portal-three-legend-item[data-rango=\"familia\"]').click()");
    await visible("document.querySelector('.portal-three-scene').dataset.rangoFiltrado==='' && document.querySelector('.portal-three-scene').dataset.nodos==='123'");
    await navegarInicial('Familia B');
    await visible("document.querySelector('.portal-three-scene').dataset.raiz==='familia-b' && document.querySelector('.portal-three-scene').dataset.nodos==='123'");
    await evaluar("window.nodoMasivoOriginal=document.querySelector('.portal-branch-row');document.querySelector('[aria-label=\"Acercar árbol\"]').click()");
    await reposo();
    assert.ok(await evaluar("document.querySelector('.portal-branch-row')===window.nodoMasivoOriginal && Math.abs(Number(document.querySelector('.portal-three-scene').dataset.zoom)-1.25)<.01"), 'El zoom superior acerca el grafo y conserva las filas del explorador.');
    await evaluar("document.querySelector('[aria-label=\"Alejar árbol\"]').click()");
    for (const factor of [.8, .64]) {
        await evaluar("document.querySelector('[aria-label=\"Alejar árbol\"]').click()"); await reposo();
        assert.ok(Math.abs(Number(await evaluar("document.querySelector('.portal-three-scene').dataset.zoom")) - factor) < .001);
    }
    await evaluar("document.querySelector('.portal-diversity-canvas,.portal-three-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'Home',bubbles:true}))"); await reposo();
    await asegurarETEEstable();
    await navegarInicial('Familia A');
    await visible("document.querySelector('.portal-three-scene').dataset.raiz==='familia-a' && document.querySelector('.portal-three-scene').dataset.nodos==='123'");
    await evaluar("window.nodoMasivoOriginal=document.querySelector('.portal-branch-row');document.querySelector('.portal-branch-row').click()");
    await reposo();
    assert.ok(await evaluar("document.querySelector('.portal-branch-row')===window.nodoMasivoOriginal && document.querySelector('.portal-taxonomy-tree-pane').dataset.filas==='123' && document.querySelector('.portal-three-scene').dataset.raiz==='familia-a' && document.querySelector('.portal-three-scene').dataset.nodos==='123'"), 'Elegir otra vez una rama conserva sus descendientes 3D y mantiene todas sus filas abiertas sin reconstruirlas.');
    await evaluar("(() => {const d=Alpine.$data(document.querySelector('#explorador-taxonomico'));d.irARama(d.nodos.find(n=>n.clave==='familia-b'));d.irARama(d.nodos.find(n=>n.clave==='familia-a'));})()");
    await reposo();
    await visible("document.querySelector('.portal-taxonomy-three-pane').getAttribute('aria-busy')==='false'");
    assert.ok(await evaluar("document.querySelector('.portal-three-scene').dataset.raiz==='familia-a' && document.querySelector('.portal-three-title').textContent==='Familia A' && !document.querySelector('.portal-three-status').checkVisibility() && !document.querySelector('.portal-ete-loading').checkVisibility() && [...document.querySelectorAll('.portal-three-legend button')].every(b=>!b.disabled)"), 'Una raíz pendiente no sustituye el dibujo ni el encabezado de la última rama elegida, ni deja el aviso de carga activo o la leyenda bloqueada.');
    for (const familia of ['familia-a','familia-b','familia-a']) {
        await evaluar("(() => {const d=Alpine.$data(document.querySelector('#explorador-taxonomico'));return d.irARama(d.nodos.find(n=>n.clave===" + JSON.stringify(familia) + "));})()");
        await visible("document.querySelector('.portal-three-scene')?.dataset.raiz===" + JSON.stringify(familia));
        assert.equal(await evaluar("document.querySelector('.portal-three-scene').dataset.nodos"), '123');
        assert.ok(await evaluar("document.querySelector('.portal-taxonomy-tree-pane').dataset.raiz===document.querySelector('.portal-three-scene').dataset.raiz && document.querySelector('.portal-taxonomy-tree-pane').dataset.filas==='123'"), 'Cambiar la raíz 3D conserva los hijos de la raíz actual.');
        await evaluar("document.querySelector('.portal-three-canvas').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}))");
        await visible("document.querySelector('.portal-taxonomy-tree-pane').dataset.nodos==='123'");
        assert.ok(await evaluar("(() => {const h=document.querySelector('.portal-taxonomy-tree-pane');return h.dataset.filas==='123' && Number(h.dataset.dibujados)<30 && h.querySelector('.portal-branch-tree').scrollHeight>h.querySelector('.portal-branch-tree').clientHeight;})()"), 'La rama con 122 hijos se conserva completa en el explorador izquierdo, con filas virtuales y desplazamiento.');
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
            const haySiguiente = await evaluar("(() => {const b=document.querySelector('.atlas-cell-dialog .hub-ig-footer [aria-label=\"Página siguiente\"]'); return Boolean(b && !b.disabled);})()");
            if (!haySiguiente) return codigos;
            const etiqueta = await evaluar("document.querySelector('.atlas-cell-dialog .hub-ig-range').textContent");
            await evaluar("document.querySelector('.atlas-cell-dialog .hub-ig-footer [aria-label=\"Página siguiente\"]').click()");
            await visible(`document.querySelector('.atlas-cell-dialog .hub-ig-range').textContent !== ${JSON.stringify(etiqueta)}`);
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
    await evaluar("document.querySelector('.atlas-map-actions .atlas-panel-tools > button').click(); [...document.querySelectorAll('.atlas-map-actions [role=menuitem]')].find(b=>b.textContent.trim()==='Explicar Métrica…').click()");
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

    // La grilla paginada conserva sus filas y controles accesibles al cambiar de ventana.
    await evaluar(`(() => {const b=document.querySelector('input[placeholder="Nombre científico en cualquier rango"]');b.focus();b.value=${JSON.stringify(datos.taxonScroll)};b.dispatchEvent(new Event('input',{bubbles:true}));b.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));})()`); await reposo();
    await evaluar("document.querySelector('button[aria-label=\"Vista de registros\"]').click()");
    await visible("document.querySelector('.portal-records-paged .portal-record-row')"); await reposo();
    const cantidades = [];
    for (const [altura, escala] of [[900, 1], [620, 1], [900, .8], [900, 1.25], [620, 2]]) {
        await conexion.send('Emulation.setDeviceMetricsOverride', {width: 1440, height: altura, deviceScaleFactor: 1, mobile: false});
        await evaluar(`document.documentElement.style.zoom='${escala}'; window.dispatchEvent(new Event('resize'))`); await reposo();
        await evaluar("document.querySelector('.portal-records-paged .hub-ig-footer').scrollIntoView({block:'center'})");await reposo();
        // Leer geometría y hit testing juntos impide que una nueva consulta del
        // ResizeObserver deje el contenido inert entre ambas comprobaciones.
        const {ajuste, acceso} = await esperar(async () => peticiones.size === 0 && await evaluar(`(() => {
            const raiz=document.querySelector('.portal-records-paged');
            if (!raiz || raiz.closest('.collection-main')?.inert || raiz.closest('.collection-workspace')?.classList.contains('is-updating')) return false;
            const nav=raiz.querySelector('.hub-ig-footer');
            const boton=nav?.querySelector('[aria-label="Página siguiente"]');
            if (!boton) return false;
            const navegacion=nav.getBoundingClientRect(), r=boton.getBoundingClientRect();
            const tabla=raiz.querySelector('.atlas-record-table-scroll'), ventana=tabla.getBoundingClientRect();
            const visibles=[...raiz.querySelectorAll('.portal-record-row')].filter(fila=>{const f=fila.getBoundingClientRect();return f.bottom>ventana.top && f.top<ventana.bottom;}).length;
            const puntos=[.2,.5,.9].map(f=>{const encima=document.elementFromPoint(r.left+r.width*f,r.top+r.height/2); return {libre:encima === boton || boton.contains(encima), elemento:encima?.className};});
            return {ajuste:{filas:raiz.querySelectorAll('.portal-record-row').length, visibles, inferior:navegacion.bottom, altura:window.innerHeight, posicion:getComputedStyle(nav).position}, acceso:{puntos, boton:r.toJSON(), raiz:raiz.getBoundingClientRect().toJSON()}};
        })()`), `paginación disponible a escala ${escala}`);
        assert.ok(ajuste.inferior <= ajuste.altura + 2, 'La navegación quedó fuera de la pantalla.');
        assert.ok(['static', 'relative'].includes(ajuste.posicion), 'La navegación quedó flotante.'); cantidades.push(ajuste.visibles);
        assert.equal(ajuste.filas, 50, 'Cambiar el tamaño reemplazó los registros cargados.');
        assert.ok(acceso.puntos.every(p=>p.libre), `Otro control cubrió el paginado a ${escala}: ${JSON.stringify(acceso)}`);
    }
    assert.ok(new Set(cantidades).size > 1, 'La ventana de registros no se adaptó a la pantalla.');
    await evaluar("document.documentElement.style.zoom='1'");
    await conexion.send('Emulation.setDeviceMetricsOverride', {width: 1440, height: 900, deviceScaleFactor: 1, mobile: false});
    await reposo();
    const posicionScroll = await evaluar(`(() => {const t=document.querySelector('.portal-records-paged[aria-label] .atlas-record-table-scroll'); t.scrollLeft=100; t.scrollTop=t.scrollHeight; return {top:t.scrollTop,left:t.scrollLeft,ids:[...document.querySelectorAll('.portal-records-paged[aria-label] .portal-record-row')].map(f=>f.getAttribute('wire:key'))};})()`);
    await reposo();
    const despuesScroll = await evaluar(`(() => {const t=document.querySelector('.portal-records-paged[aria-label] .atlas-record-table-scroll'); return {top:t.scrollTop,left:t.scrollLeft,ids:[...document.querySelectorAll('.portal-records-paged[aria-label] .portal-record-row')].map(f=>f.getAttribute('wire:key'))};})()`);
    assert.deepEqual(despuesScroll.ids, posicionScroll.ids, 'El scroll no acumula ni sustituye filas de otra pagina.');
    assert.ok(Math.abs(despuesScroll.top-posicionScroll.top)<2 && Math.abs(despuesScroll.left-posicionScroll.left)<2, 'El scroll conserva ambas posiciones.');
    await evaluar("document.querySelector('.portal-records-paged [aria-label=\"Página siguiente\"]').click()");await reposo();
    const segundaPagina=await evaluar("[...document.querySelectorAll('.portal-records-paged[aria-label] .portal-record-row')].map(f=>f.getAttribute('wire:key'))");
    assert.equal(segundaPagina.length,50);assert.equal(new Set(segundaPagina).size,50);
    assert.ok(segundaPagina.every(id=>!posicionScroll.ids.includes(id)), 'La siguiente pagina reemplaza las filas sin repetir ejemplares.');
    await evaluar("document.querySelector('.portal-records-paged [aria-label=\"Página anterior\"]').click()");await reposo();
    assert.deepEqual(await evaluar("[...document.querySelectorAll('.portal-records-paged[aria-label] .portal-record-row')].map(f=>f.getAttribute('wire:key'))"),posicionScroll.ids);
    await evaluar(`(() => {const b=document.querySelector('input[placeholder="Nombre científico en cualquier rango"]');b.focus();b.value=${JSON.stringify(datos.taxonBusqueda)};b.dispatchEvent(new Event('input',{bubbles:true}));b.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));})()`); await reposo();
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
    // Visitante sin sesión: mapa público desde ambos motores, también dentro
    // de la expansión CSS y del top layer nativo. Los registros siguen ausentes.
    await conexion.send('Page.navigate',{url:origen+'/prestamos/curador/matriz-columnas'});
    await visible("document.querySelector('[data-matriz-config] .hub-ig tbody tr')");await reposo();
    assert.equal(await evaluar("document.querySelectorAll('[data-matriz-config] .hub-ig tbody tr').length"),106);
    await evaluar("(() => {const i=document.querySelector('input[placeholder=\"Nombre, cabecera, alias o requisito\"]');i.value='dateDetermined';i.dispatchEvent(new Event('input',{bubbles:true}));})()");
    await visible("document.querySelectorAll('[data-matriz-config] .hub-ig tbody tr').length===1");await reposo();
    await evaluar("document.querySelector('[data-matriz-config] .hub-ig tbody button').click()");
    await visible("Boolean(document.querySelector('[data-matriz-config] dialog[open]'))");await reposo();
    assert.ok(await evaluar("document.querySelector('[data-matriz-config] dialog[open]').contains(document.activeElement)"),'Editar una columna lleva el foco al formulario modal del curador.');
    await conexion.send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await conexion.send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await visible("!document.querySelector('[data-matriz-config] dialog[open]')");
    await reposo();
    // La pantalla real de carga muestra el conflicto de columnas sin una
    // navegación de error y conserva el botón de guardar deshabilitado.
    await conexion.send('Page.navigate',{url:origen+'/inventario/taxonomia/importar'});
    await visible("document.querySelector('input[aria-label=\"Matriz del catálogo\"]') && window.Livewire");await reposo();
    assert.ok(await evaluar("[...document.querySelectorAll('button')].find(b=>b.textContent.trim()==='Importar al catálogo').disabled"));
    assert.ok(await evaluar("Boolean(document.querySelector('[aria-label=\"Historial de matrices\"].hub-ig'))"));
    const matrizConflicto = path.join(temporal, 'matriz-columnas-conflicto.csv');
    writeFileSync(matrizConflicto,'occurrenceID,catalogNumber,catalogNumber\nQA-BROWSER-MATRIZ,CAT-UNO,CAT-DOS\n','utf8');
    const docMatriz = await conexion.send('DOM.getDocument');
    const inputMatriz = await conexion.send('DOM.querySelector',{nodeId:docMatriz.root.nodeId,selector:'input[type=file]'});
    await conexion.send('DOM.setFileInputFiles',{nodeId:inputMatriz.nodeId,files:[matrizConflicto]});await reposo();
    await visible("!([...document.querySelectorAll('button')].find(b=>b.textContent.trim()==='Validar matriz').disabled)");
    await evaluar("[...document.querySelectorAll('button')].find(b=>b.textContent.trim()==='Validar matriz').click()");
    await visible("[...document.querySelectorAll('[role=alert]')].some(e=>e.textContent.includes('Fila 2') && e.textContent.includes('catalogNumber'))");await reposo();
    assert.ok(await evaluar("[...document.querySelectorAll('button')].find(b=>b.textContent.trim()==='Importar al catálogo').disabled"),'El conflicto visible impide guardar y conserva la pantalla de carga.');
    // Alternar cargas con celdas opcionales distintas y paginar debe renovar
    // todas las filas del modal, incluso tras abrir una de la carga anterior.
    for (const carga of [datos.cargas[1], datos.cargas[0], datos.cargas[1]]) {
        await evaluar(`[...document.querySelectorAll('button')].find(b=>b.getAttribute('wire:click')===${JSON.stringify(`verDetalle('${carga.id}')`)}).click()`);
        await visible(`document.querySelector('dialog[open] h2')?.textContent===${JSON.stringify(carga.archivo)}`);await reposo();
        const resumenes = carga.codigos.slice(0,20).map((codigo,i)=>`Fila ${i+2} · ${codigo} · actualizado`);
        assert.deepEqual(await evaluar("[...document.querySelectorAll('dialog[open] summary')].map(e=>e.textContent.trim())"),resumenes,'El detalle no debe mezclar filas de la carga anterior.');
        await evaluar("document.querySelector('dialog[open] summary').click()");
        assert.ok((await evaluar("document.querySelector('dialog[open] details[open]').textContent")).includes(carga.codigos[0]));
        await evaluar("[...document.querySelectorAll('dialog[open] button')].find(b=>b.textContent.trim()==='Siguiente').click()");
        await visible("document.querySelector('dialog[open]').textContent.includes('Página 2')");await reposo();
        assert.deepEqual(await evaluar("[...document.querySelectorAll('dialog[open] summary')].map(e=>e.textContent.trim())"),carga.codigos.slice(20).map((codigo,i)=>`Fila ${i+22} · ${codigo} · actualizado`));
        await evaluar("document.querySelector('button[aria-label=\"Cerrar detalle de importación\"]').click()");
        await visible("!document.querySelector('dialog[open]')");await reposo();
    }
    await conexion.send('Network.clearBrowserCookies');
    await conexion.send('Page.navigate',{url});
    await visible("document.querySelector('a[href$=\"/login\"]') && document.querySelector('.atlas-map .leaflet-pane')");await reposo();
    await evaluar("document.querySelector('button[aria-label=\"Árbol taxonómico\"]').click()");
    await visible("document.querySelector('.portal-diversity-scene')?.dataset.frames>0");await reposo();
    await evaluar("document.querySelector('[aria-label=\"Acercar árbol\"]').click()");
    await visible("document.querySelector('.portal-diversity-scene').dataset.animando==='false'");await reposo();
    await menuTaxon('.portal-diversity-canvas');
    assert.ok(await evaluar("document.querySelector('.taxonomy-explorer-context-menu').parentElement===document.querySelector('.portal-diversity-scene.is-expanded')"),'El menú del árbol pertenece a la escena expandida.');
    assert.deepEqual(await evaluar("[...document.querySelectorAll('.taxonomy-explorer-context-menu button')].map(b=>b.textContent.trim())"),['Ver en el mapa','Ver registros']);
    assert.ok(await evaluar("(() => {const m=document.querySelector('.taxonomy-explorer-context-menu'),r=m.querySelector('button').getBoundingClientRect();return m.contains(document.elementFromPoint(r.left+r.width/2,r.top+r.height/2));})()"),'El visitante puede pulsar el menú, que no queda tapado por el canvas.');
    const taxonMapaPublico=await evaluar("Alpine.$data(document.querySelector('#explorador-taxonomico')).menuNodo.nombre");
    await evaluar("document.querySelector('.taxonomy-explorer-context-menu button').click()");
    await visible("!document.querySelector('#explorador-taxonomico').open && document.querySelector('.atlas-map .leaflet-pane')");await reposo();
    assert.ok((await evaluar("document.querySelector('.collection-active-filters').textContent")).includes(taxonMapaPublico),'El mapa público conserva el taxón elegido con clic derecho.');
    await evaluar("document.querySelector('button[aria-label=\"Árbol taxonómico\"]').click()");
    await visible("document.querySelector('.portal-diversity-scene')?.checkVisibility()");await reposo();
    await evaluar("document.querySelector('.portal-taxonomy-visualization').click()");
    await visible("(() => {const s=document.querySelector('.portal-three-scene');return s&&Number(s.dataset.indexados)>0&&s.dataset.indexados===s.dataset.nodos;})()");await reposo();
    // Esta sesión recién abierta aún no ha descubierto las ramas del fixture.
    // Cargar la especie con el mismo buscador público que utiliza el visitante.
    await buscarEnArbol(datos.taxonBusqueda+' alfa');
    await evaluar("[...document.querySelectorAll('#sugerencias-explorador button')].find(b=>b.querySelector('strong')?.textContent==="+JSON.stringify(datos.taxonBusqueda+' alfa')+").click()");
    await visible("document.querySelector('.portal-three-scene')?.dataset.nombre==="+JSON.stringify(datos.taxonBusqueda+' alfa'));await reposo();
    const pantallaPublica=await evaluar("(() => {const r=document.querySelector('[aria-label=\"Ver visualizador en pantalla completa\"]').getBoundingClientRect();return {x:r.left+r.width/2,y:r.top+r.height/2};})()");
    await conexion.send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',clickCount:1,...pantallaPublica});
    await conexion.send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',clickCount:1,...pantallaPublica});
    await visible("document.fullscreenElement===document.querySelector('.portal-three-scene')");
    await menuTaxon('.portal-three-canvas');
    assert.ok(await evaluar("document.querySelector('.taxonomy-explorer-context-menu').parentElement===document.fullscreenElement"),'El menú de esferas está dentro del top layer nativo.');
    assert.deepEqual(await evaluar("[...document.querySelectorAll('.taxonomy-explorer-context-menu button')].map(b=>b.textContent.trim())"),['Ver en el mapa','Ver registros']);
    assert.ok(await evaluar("(() => {const m=document.querySelector('.taxonomy-explorer-context-menu'),r=m.querySelector('button').getBoundingClientRect();return m.contains(document.elementFromPoint(r.left+r.width/2,r.top+r.height/2));})()"));
    await evaluar("document.querySelector('.taxonomy-explorer-context-menu button').click()");
    await visible("!document.querySelector('#explorador-taxonomico').open && !document.fullscreenElement && document.querySelector('.atlas-map .leaflet-pane')");await reposo();
    assert.equal(await evaluar("document.querySelector('.taxonomy-explorer-context-menu').parentElement.id"),'explorador-taxonomico','Al cerrar, el mismo menú vuelve al diálogo.');

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
    console.error('Diagnóstico de respuesta inicial:', JSON.stringify(respuestaInicial ?? null));
    const log = entorno && path.join(entorno.LARAVEL_STORAGE_PATH, 'logs/laravel.log');
    try {
        if (log && existsSync(log)) {
            const contenido = readFileSync(log, 'utf8');
            // Sólo códigos y tiempos: no imprimir SQL, bindings, sesiones o secretos.
            console.error('Diagnóstico SQLSTATE:', JSON.stringify([...new Set([...contenido.matchAll(/SQLSTATE\[([A-Z0-9]{5})\]/g)].map(m => m[1]))]));
            const etapas = [...contenido.matchAll(/portal\.mapa\.etapa (\{[^\r\n]+\})/g)].slice(-12).map(m => {
                try { const e = JSON.parse(m[1]); return {etapa: e.etapa, duracion_ms: e.duracion_ms, total_ms: e.total_ms}; } catch { return null; }
            }).filter(Boolean);
            console.error('Diagnóstico de etapas del mapa:', JSON.stringify(etapas));
        }
    } catch { console.error('Diagnóstico local de consultas no disponible.'); }
    if (conexion) {
        try {
            const estado = await conexion.send('Runtime.evaluate', {expression: `JSON.stringify({url: location.href, titulo: document.title, foco: {id: document.activeElement?.id, elemento: document.activeElement?.tagName}, busqueda: document.querySelector('#busqueda-explorador-taxonomico')?.value, explorador: (()=>{const el=document.querySelector('#explorador-taxonomico');if(!el||!window.Alpine)return null;const d=Alpine.$data(el);return {abierto:d.abierto,listo:d.listo,buscando:d.buscando,visorCargando:d.visorCargando,error:d.error,sugerencias:d.sugerencias?.length,busqueda:d.busqueda};})(), texto: document.body.innerText.slice(-1800), tooltip: document.querySelector('.atlas-floating-tooltip')?.outerHTML, marcadores: [...document.querySelectorAll('.atlas-map [aria-label^="Ubicación original"]')].map(p=>({etiqueta:p.getAttribute('aria-label'), rect:p.getBoundingClientRect().toJSON()}))})`, returnByValue: true});
            console.error('Estado de la página:', estado.result.value);
        } catch { /* Se conserva la causa original si el navegador ya terminó. */ }
    }
    throw error;
} finally {
    // Cerrar Neon por su protocolo permite que renderers y servicios liberen
    // el perfil temporal. Terminar sólo el proceso lanzador dejaba handles de
    // Windows abiertos aunque todas las aserciones hubieran terminado.
    try { await conexion?.send('Browser.close'); } catch { /* El navegador puede haber terminado durante el diagnóstico. */ }
    conexion?.close();
    if (procesoNavegador && procesoNavegador.exitCode === null && procesoNavegador.signalCode === null) {
        await Promise.race([new Promise(resolve => procesoNavegador.once('close', resolve)), demora(5000)]);
    }
    for (const proceso of procesos.reverse()) {
        if (proceso.exitCode === null && proceso.signalCode === null) {
            proceso.kill();
            await Promise.race([new Promise(resolve => proceso.once('close', resolve)), demora(5000)]);
        }
    }
    try {
        if (existsSync(fixture)) execFileSync(php, ['tests/Browser/portal-fixture.php', 'limpiar', token, fixture], {cwd: proyecto, env: entorno, stdio: 'pipe'});
    } finally {
        const absoluto = path.resolve(temporal), raizTemporal = path.resolve(tmpdir()) + path.sep;
        assert.ok(absoluto.startsWith(raizTemporal) && path.basename(absoluto).startsWith('hubdigital-browser-'), 'Ruta temporal de limpieza inválida.');
        rmSync(absoluto, {recursive: true, force: true, maxRetries: 8, retryDelay: 300});
    }
}
