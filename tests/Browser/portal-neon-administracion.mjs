import {execFileSync, spawn} from 'node:child_process';
import {createInterface} from 'node:readline';
import path from 'node:path';
import assert from 'node:assert/strict';

export function prepararMatrizAdministracion(php, proyecto, entorno) {
    // El esquema y su historial se restauran sin datos. La migración ya
    // figura aplicada, por lo que solo falta su fila inicial en esta base.
    const inicializar = String.raw`
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection('pgsql');
$destino = $db->selectOne('select current_database() as nombre, host(inet_server_addr()) as host');
if (!$app->environment('testing') || $destino->host !== '127.0.0.1' || !preg_match('/^hd_pkg_[a-f0-9]{12}_browser$/D', $destino->nombre)) {
    fwrite(STDERR, 'La inicialización exige la base aislada de Opera Neon.'); exit(65);
}
try {
    $db->transaction(static function () use ($db): void {
        if ($db->table('recepciones.configuracion_matriz')->where('clave', 'depositos')->exists()) return;
        $modulo = base_path('Modules/GestionPrestamosRecepciones/database');
        $definicion = file_get_contents($modulo.'/data/matriz-deposito-v2.json');
        $db->table('recepciones.configuracion_matriz')->insert([
            'clave'=>'depositos', 'definicion'=>$definicion, 'created_at'=>now(), 'updated_at'=>now(),
        ]);
        (require $modulo.'/migrations/2026_10_26_200001_update_depositante_matrix_template.php')->up();
    });
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage()); exit(1); }
`;
    execFileSync(php, ['-r', inicializar], {cwd:proyecto, env:entorno, timeout:60000, stdio:'pipe'});
    console.log('Matriz de depósito inicializada en la base aislada del recorrido.');
}

const diagnostico = `(() => {
    const estado = window.__hubdigitalPrueba = {excepciones: [], red: [], pendientes: 0, latencia: 0};
    addEventListener('error', evento => { if (evento.error) estado.excepciones.push(evento.message); });
    addEventListener('unhandledrejection', evento => estado.excepciones.push(String(evento.reason)));
    const error = console.error;
    console.error = (...args) => { estado.excepciones.push(args.map(String).join(' ')); error.apply(console, args); };
    const livewire = (url, method) => {
        const destino = new URL(url, location.href);
        return destino.origin === location.origin && destino.pathname.includes('livewire') && method?.toUpperCase() === 'POST';
    };
    const fetch = window.fetch;
    window.fetch = async function (input, options) {
        if (!livewire(input.url || input, options?.method || input.method)) return fetch.apply(this, arguments);
        estado.pendientes++;
        try {
            if (estado.latencia) await new Promise(resolve => setTimeout(resolve, estado.latencia));
            const response = await fetch.apply(this, arguments);
            if (!response.ok) estado.red.push(response.status);
            return response;
        } catch (error) { estado.red.push(String(error)); throw error; }
        finally { estado.pendientes--; }
    };
    const open = XMLHttpRequest.prototype.open;
    const send = XMLHttpRequest.prototype.send;
    const monitorear = new WeakSet();
    XMLHttpRequest.prototype.open = function (method, url) {
        if (livewire(url, method)) monitorear.add(this); else monitorear.delete(this);
        return open.apply(this, arguments);
    };
    XMLHttpRequest.prototype.send = function () {
        if (!monitorear.has(this)) return send.apply(this, arguments);
        estado.pendientes++;
        const finalizar = () => {
            if (this.status === 0 || this.status >= 400) estado.red.push(this.status);
            estado.pendientes--;
        };
        this.addEventListener('loadend', finalizar, {once:true});
        try { return send.apply(this, arguments); }
        catch (error) {
            this.removeEventListener('loadend', finalizar);
            estado.pendientes--;
            estado.red.push(String(error));
            throw error;
        }
    };
})()`;

export async function iniciarNeonAdministracion(origen, excepciones, erroresLivewire, peticiones) {
    const sitio = new URL(origen);
    assert.equal(sitio.hostname, '127.0.0.1', 'El recorrido usa únicamente su servidor local aislado.');
    const lanzador = path.join(process.env.USERPROFILE, '.codex/tools/opera-neon/Start-OperaNeonMcp.ps1');
    const proceso = spawn('powershell.exe', ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', lanzador],
        {stdio:['pipe','pipe','pipe'], windowsHide:true});
    const pendientes = new Map();
    let siguiente = 0;
    let pagina;
    let creada = false;
    let indicios = '';
    let observados = {excepciones:0, red:0};
    const escribir = mensaje => proceso.stdin.write(JSON.stringify({jsonrpc:'2.0', ...mensaje})+'\n');
    const llamar = (method, params, limite = 45000) => new Promise((resolve, reject) => {
        const id = ++siguiente;
        const timer = setTimeout(() => {
            pendientes.delete(id);
            reject(new Error(`Neon QA no respondió a ${method}.`));
        }, limite);
        pendientes.set(id, {resolve, reject, timer});
        escribir({id, method, params});
    });
    proceso.on('exit', () => {
        for (const {reject, timer} of pendientes.values()) {
            clearTimeout(timer); reject(new Error('El proceso MCP de Neon QA terminó.'));
        }
        pendientes.clear();
    });
    createInterface({input:proceso.stdout}).on('line', linea => {
        let mensaje;
        try { mensaje = JSON.parse(linea); } catch { return; }
        const consulta = pendientes.get(mensaje.id);
        if (!consulta) return;
        clearTimeout(consulta.timer);
        pendientes.delete(mensaje.id);
        if (mensaje.error) consulta.reject(new Error(mensaje.error.message));
        else consulta.resolve(mensaje.result);
    });
    const herramienta = async (name, args = {}) => {
        const respuesta = await llamar('tools/call', {name, arguments: args});
        const salida = respuesta?.content?.filter(item => item.type === 'text').map(item => item.text).join('\n') || '';
        if (respuesta?.isError) throw new Error(`Neon QA (${name}): ${salida}`);
        return salida;
    };
    const buscarPagina = lista => lista.split('\n').find(linea => new RegExp(`(?:^|[ (])${origen.replaceAll('.', '\\.')}\/`).test(linea))?.match(/^\s*(\d+):/)?.[1];
    try {
        await new Promise((resolve, reject) => {
            const timer = setTimeout(() => reject(new Error('El lanzador oficial de Neon QA no inició.')), 45000);
            proceso.once('error', error => { clearTimeout(timer); reject(error); });
            proceso.once('exit', () => { clearTimeout(timer); reject(new Error('No se inició el lanzador oficial de Neon QA.')); });
            proceso.stderr.on('data', chunk => {
                indicios = (indicios + chunk).slice(-2000);
                if (indicios.includes('DevTools')) { clearTimeout(timer); resolve(); }
            });
        });
        await llamar('initialize', {protocolVersion:'2024-11-05', capabilities:{}, clientInfo:{name:'HubDigital QA',version:'1.0.0'}});
        escribir({method:'notifications/initialized'});
        pagina = buscarPagina(await herramienta('list_pages'));
        if (!pagina) {
            await herramienta('new_page', {url: origen+'/login'});
            pagina = buscarPagina(await herramienta('list_pages'));
            creada = true;
        }
        assert.ok(pagina, 'No se ubicó la pestaña QA del servidor aislado.');
        pagina = Number(pagina);
        await herramienta('select_page', {pageId:pagina, bringToFront:true});
    } catch (error) {
        proceso.stdin.end();
        throw error;
    }

    async function evaluar(expression) {
        const salida = await herramienta('evaluate_script', {pageId:pagina, waitForStableDom:false,
            function: `async () => {
                const value = await eval(${JSON.stringify(expression)});
                return {value: value instanceof Node || value === window.Livewire && value != null ? true : value,
                    diagnostico: window.__hubdigitalPrueba || null};
            }`});
        const texto = salida.match(/```json\s+([\s\S]+?)```/)?.[1];
        if (!texto) throw new Error('El MCP no devolvió el resultado esperado de la evaluación.');
        const resultado = JSON.parse(texto);
        const estado = resultado.diagnostico;
        if (estado) {
            excepciones.push(...estado.excepciones.slice(observados.excepciones));
            erroresLivewire.push(...estado.red.slice(observados.red));
            observados = {excepciones:estado.excepciones.length, red:estado.red.length};
            if (estado.pendientes) peticiones.add('Livewire'); else peticiones.delete('Livewire');
        }
        return {result:{value:resultado.value}};
    }

    async function moverCursor(x, y) {
        const label = x === 0 && y === 0
            ? 'Distribución de Registros'
            : (await evaluar(`document.elementFromPoint(${x}, ${y})?.closest('[aria-label^="Ubicación original"]')?.getAttribute('aria-label')`)).result.value;
        assert.ok(label, 'El cursor debe apuntar a un marcador visible o al título del mapa.');
        const snapshot = await herramienta('take_snapshot', {pageId:pagina, verbose:true});
        const element = snapshot.split('\n').find(line => x === 0 && y === 0
            ? line.includes(`heading "${label} `) || line.includes(`heading "${label}"`)
            : line.includes(`button "${label}"`));
        const uid = element?.match(/^\s*uid=(\S+)/)?.[1];
        assert.ok(uid, `El elemento no se encontró en el árbol accesible de Neon QA: ${label}`);
        return herramienta('hover', {pageId:pagina, uid});
    }

    return {
        on() { /* El diagnóstico se obtiene de cada documento de la pestaña QA. */ },
        async send(method, params = {}) {
            if (method === 'Runtime.evaluate') return evaluar(params.expression);
            if (method === 'Page.navigate') {
                assert.equal(new URL(params.url).origin, origen);
                observados = {excepciones:0, red:0};
                peticiones.clear();
                return herramienta('navigate_page', {pageId:pagina, type:'url', url:params.url, timeout:40000, initScript:diagnostico});
            }
            if (method === 'Input.dispatchKeyEvent' && params.type === 'char') return herramienta('type_text', {pageId:pagina, text:params.text});
            if (method === 'Input.dispatchKeyEvent' && params.key === 'Escape') {
                if (params.type === 'keyDown') await herramienta('press_key', {pageId:pagina, key:'Escape'});
                return;
            }
            if (method === 'Input.dispatchMouseEvent' && params.type === 'mouseMoved') return moverCursor(params.x, params.y);
            if (method === 'Network.emulateNetworkConditions') return evaluar(`
                if (window.__hubdigitalPrueba) window.__hubdigitalPrueba.latencia = ${Number(params.latency) || 0};
            `);
            if (method === 'Browser.close') {
                if (creada) await herramienta('close_page', {pageId:pagina});
                return;
            }
            if (['Page.enable','Runtime.enable','Network.enable','Network.clearBrowserCookies',
                'Emulation.setDeviceMetricsOverride'].includes(method)) return;
            throw new Error(`Operación no implementada por el recorrido administrativo de Neon QA: ${method}`);
        },
        async subirMatriz(archivo) {
            const captura = await herramienta('take_snapshot', {pageId:pagina, verbose:true});
            const uid = captura.match(/^\s*uid=(\S+)\s+button "(?:Matriz Excel o CSV|Matriz del catálogo)"(?:\s|$)/m)?.[1];
            assert.ok(uid, 'No se encontró el selector de matriz en el DOM accesible de Neon.');
            await herramienta('upload_file', {pageId:pagina, uid, filePaths:[archivo]});
        },
        close() { proceso.stdin.end(); },
    };
}
