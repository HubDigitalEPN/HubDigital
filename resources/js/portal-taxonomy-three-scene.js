import {Box3, BufferAttribute, BufferGeometry, Color, DynamicDrawUsage, Frustum, Matrix4, LineBasicMaterial, LineSegments, PerspectiveCamera,
    Points, Raycaster, Scene, ShaderMaterial, Vector2, Vector3, WebGLRenderer} from 'three';
import {OrbitControls} from 'three/addons/controls/OrbitControls.js';
import {octree} from 'd3-octree';
import {crearIndiceRama3D, distribuirRama3D, filtrarRama3D} from './portal-taxonomy-three-model';
import {crearCacheRamas3D} from './portal-taxonomy-three-cache';
import {buscarNodoEspacial, nodosEnEncuadre, prepararRadiosEspaciales} from './portal-taxonomy-three-spatial';
import {materialMundos3D} from './portal-taxonomy-three-worlds';
import {crearAtlasBiologico3D, GRUPOS_BIOLOGICOS_3D, grupoBiologico3D} from './portal-taxonomy-three-organisms';
import urlWorker from './portal-taxonomy-three-worker.js?worker&url';
import {rangoVisual3D} from './portal-taxonomy-three-ranks';
import {textoLinajeTaxonomico} from './portal-taxonomy-lineage';
import {factorAbundancia3D, fondoEstelar3D} from './portal-taxonomy-three-style';
import {crearConsultaPuntero} from './portal-taxonomy-pointer';
import {crearLeyendaTaxonomica} from './portal-taxonomy-tooltip';

const numero = valor => Number(valor).toLocaleString('es-EC');
const crear = (tag, clase, texto) => {const el = document.createElement(tag); el.className = clase; if (texto) el.textContent = texto; return el;};
const boton = (nombre, texto, accion) => {const el = crear('button', '', texto); el.type = 'button'; el.setAttribute('aria-label', nombre); el.title = nombre; el.addEventListener('click', accion); return el;};

export function crearEscenaTaxonomica(elemento, acciones) {
    const marco = crear('div', 'portal-three-scene'); marco.dataset.motor = 'Three.js + d3-octree';
    const cabecera = crear('div', 'portal-three-context'), titulo = crear('h3', 'portal-three-title');
    const meta = crear('p', 'portal-three-meta'), identidad = crear('div', 'portal-three-identity'); identidad.append(titulo, meta);
    const controles = crear('div', 'portal-three-controls'); controles.setAttribute('role', 'group'); controles.setAttribute('aria-label', 'Controles del árbol 3D');
    const volver = boton('Volver al padre de la rama 3D', '↶', () => {const padre = indice?.porClave.get(clave)?.padre; if (padre) acciones.navegar(padre);});
    controles.append(volver);
    const maximizar = boton('Ver visualizador en pantalla completa', '⛶', () => pantallaCompleta().catch(() => mostrarError('No se pudo activar la pantalla completa. Vuelve a intentarlo.')));
    maximizar.className = 'portal-three-maximize';
    cabecera.append(identidad, controles);
    const barra = elemento.closest('.taxonomy-navigator').querySelector('.taxonomy-navigator-zoom');
    barra.prepend(cabecera); barra.append(maximizar);
    const vista = crear('div', 'portal-three-viewport'), leyenda = crear('div', 'portal-three-legend');
    vista.style.setProperty('--taxonomy-starfield', fondoEstelar3D());
    leyenda.setAttribute('aria-label', 'Filtrar mundos por rango taxonómico; pulsa otra vez para ver toda la rama'); leyenda.setAttribute('role', 'group');
    const estado = crear('p', 'portal-three-status'); estado.setAttribute('role', 'status'); estado.hidden = true;
    const instrucciones = crear('p', 'portal-three-instructions');
    instrucciones.tabIndex = 0;
    marco.append(vista, leyenda, instrucciones); elemento.replaceChildren(marco);
    const salirPantalla = boton('Salir de pantalla completa', '⛶', () => document.exitFullscreen());
    salirPantalla.className = 'portal-three-fullscreen-exit'; marco.append(salirPantalla);
    let renderer;
    try {renderer = new WebGLRenderer({antialias: true, alpha: true, powerPreference: 'high-performance'});}
    catch (error) {elemento.replaceChildren(); cabecera.remove(); maximizar.remove(); throw error;}
    renderer.setPixelRatio(Math.min(globalThis.devicePixelRatio || 1, 1.5));
    const canvas = renderer.domElement; canvas.className = 'portal-three-canvas'; canvas.tabIndex = 0;
    canvas.setAttribute('role', 'img'); canvas.setAttribute('aria-label', 'Árbol 3D: flechas para girar, + y − para zoom, Inicio para ajustar');
    vista.append(canvas, estado);
    const leyendaFlotante=crearLeyendaTaxonomica(vista,canvas);
    const escena = new Scene(), camara = new PerspectiveCamera(44, 1, .1, 15000);
    camara.position.set(0, 90, 900);
    const orbit = new OrbitControls(camara, canvas); orbit.enableDamping = false; orbit.minDistance = 8; orbit.maxDistance = 9000;
    orbit.zoomToCursor = true; orbit.rotateSpeed = .65; orbit.zoomSpeed = .8; orbit.enablePan = true;
    const rayo = new Raycaster(), puntero = new Vector2(), punto = new Vector3(), caja = new Box3();
    const centro = new Vector3(), direccionInicial = new Vector3(0, .28, 1).normalize();
    const derechaInicial = new Vector3(1, 0, 0), arribaInicial = new Vector3().crossVectors(direccionInicial, derechaInicial);
    const encuadre = new Frustum(), matrizEncuadre = new Matrix4(), matrizAnterior = new Float64Array(16), planosEncuadre = new Float32Array(24);
    const colores = new Map(), gruposBiologicos = new Map(), atlasBiologico = crearAtlasBiologico3D();
    const resaltado = new LineSegments(new BufferGeometry(), new LineBasicMaterial({color: '#65c4cf', transparent: true, opacity: .92, depthTest: false}));
    resaltado.visible = false; resaltado.renderOrder = 5; escena.add(resaltado);
    let fuente, indice, clave, dibujo, dibujoCompleto, puntos, conexiones, espacial, tamanos;
    let destruido = false, activo = true, revision = 0, idWorker = 0, frame = null, idle = null, pulsacion = null;
    let sobreGrafo = false, pausaHasta = 0, reanudar = null, ultimoFrame = 0, esperaFrame = null, renderizando = false, ultimoTelemetria = 0;
    let indiceListo = false, consultandoEncuadre = false, ultimoEncuadre = 0, esperaEncuadre = null, firmaEncuadre = false, maxTamano = 92;
    let dibujoIndexado, idIndice = 0, jerarquiaFallback, indicesVisibles, recortado = false;
    let hijosDirectos = [], hijoTeclado = -1;
    let seleccionElegida = '', indiceElegido = -1, pulsoHasta = 0;
    const rangoActivo = {value: 0}; let filtroRango = '', rangosActuales = new Map(), revisionFiltro = 0;
    const movimientoReducido = matchMedia('(prefers-reduced-motion: reduce)');
    let distanciaAjustada = 900, ancho = 1, alto = 1, indiceCursor = 0, nodoResaltado = -1, zoomNotificado;
    const pendientes = new Map(), cache = crearCacheRamas3D(); let worker, bootstrapWorker;
    try {
        const archivoWorker = new URL(urlWorker, import.meta.url);
        // En desarrollo Laravel y Vite pueden tener orígenes distintos. El bootstrap es del sitio;
        // Vite sigue resolviendo y empaquetando el módulo del worker para la publicación.
        if (archivoWorker.origin !== location.origin) {
            bootstrapWorker = URL.createObjectURL(new Blob([`import ${JSON.stringify(archivoWorker.href)};`], {type: 'text/javascript'}));
        }
        worker = new Worker(bootstrapWorker || archivoWorker, {type: 'module'});
        worker.onmessage = ({data}) => {
            if (data.tipo === 'indice') {if (data.revision === revision) {
                if (data.falta) {indexar(revision, true); return;}
                marco.dataset.indexados = String(data.cantidad); marco.dataset.indexMs = data.tiempo.toFixed(2);
                marco.dataset.indiceReutilizado = String(Boolean(data.reutilizado)); indiceListo = data.completo; programar();
            } return;}
            if (data.tipo === 'seleccion') {marco.dataset.pickMs = data.tiempo.toFixed(2); marco.dataset.puntosConsultados = String(data.estadisticas.puntos || 0);}
            const promesa = pendientes.get(data.id); if (!promesa) return;
            pendientes.delete(data.id); data.error ? promesa.reject(new Error(data.error)) : promesa.resolve(data.tipo === 'seleccion' ? data.nodo : data.tipo === 'encuadre' ? data : data.dibujo);
        };
        worker.onerror = () => {
            for (const promesa of pendientes.values()) promesa.reject(new Error('No se pudo preparar el árbol en segundo plano.'));
            pendientes.clear(); worker.terminate(); worker = null; if (bootstrapWorker) {URL.revokeObjectURL(bootstrapWorker); bootstrapWorker = null;}
            if (dibujo) indexar(revision);
        };
    } catch {worker = null;}

    function programar() {
        if (destruido || !activo || renderizando) return;
        if (esperaFrame !== null) {clearTimeout(esperaFrame); esperaFrame = null;}
        if (frame !== null) return;
        frame = requestAnimationFrame(tiempo => {
            frame = null; if (destruido || !activo || !vista.clientWidth || !vista.clientHeight) return;
            const visible = elemento.closest('dialog').open && document.visibilityState === 'visible';
            if (!visible) {orbit.autoRotate = false; ultimoFrame = 0; marco.dataset.rotando = 'false'; return;}
            const rotar = visible && !sobreGrafo && tiempo >= pausaHasta && !movimientoReducido.matches;
            // 30 fps al girar sola; el arrastre y el zoom siguen respondiendo a sus eventos.
            if (rotar && ultimoFrame && tiempo - ultimoFrame < 32.7) {continuarAnimacion(); return;}
            renderizando = true;
            orbit.autoRotate = rotar; orbit.autoRotateSpeed = 1.21;
            if (rotar) orbit.update(ultimoFrame ? Math.min(.1, (tiempo - ultimoFrame) / 1000) : 1 / 30);
            ultimoFrame = tiempo;
            camara.updateMatrixWorld();
            if (puntos) {
                puntos.material.uniforms.referenceDistance.value = distanciaAjustada;
                puntos.material.uniforms.worldFromView.value.setFromMatrix4(camara.matrixWorld);
                puntos.material.uniforms.selectionPulse.value = tiempo < pulsoHasta && !movimientoReducido.matches
                    ? .5 + .5 * Math.sin((pulsoHasta - tiempo) * .008) : 0;
                marco.dataset.pulsando = String(tiempo < pulsoHasta && !movimientoReducido.matches);
            }
            actualizarEncuadre(tiempo);
            renderer.render(escena, camara);
            const zoomActual = (distanciaAjustada / Math.max(8, orbit.getDistance())).toFixed(3);
            if (zoomActual !== zoomNotificado) {
                zoomNotificado = zoomActual; marco.dataset.zoom = zoomActual;
                acciones.zoom?.(Number(zoomNotificado)); acciones.ajustado?.(Math.abs(Number(zoomNotificado) - 1) < .001);
            }
            if (!rotar || tiempo - ultimoTelemetria >= 200) {
                ultimoTelemetria = tiempo; marco.dataset.drawCalls = String(renderer.info.render.calls);
                marco.dataset.frames = String(renderer.info.render.frame);
                marco.dataset.camera = `${camara.position.x.toFixed(2)},${camara.position.y.toFixed(2)},${camara.position.z.toFixed(2)}`;
                marco.dataset.target = `${orbit.target.x.toFixed(2)},${orbit.target.y.toFixed(2)},${orbit.target.z.toFixed(2)}`;
            }
            marco.dataset.rotando = String(rotar);
            renderizando = false;
            if (rotar || visible && tiempo < pulsoHasta && !movimientoReducido.matches) continuarAnimacion();
            else if (visible && !sobreGrafo && pausaHasta > tiempo && reanudar === null)
                reanudar = setTimeout(() => {reanudar = null; programar();}, Math.max(16, pausaHasta - tiempo));
        });
    }
    function continuarAnimacion() {
        if (esperaFrame !== null || destruido) return;
        esperaFrame = setTimeout(() => {esperaFrame = null; programar();}, Math.max(4, 1000 / 30 - (performance.now() - ultimoFrame) - 8));
    }
    orbit.addEventListener('change', programar);
    orbit.addEventListener('start',()=>leyendaFlotante.ocultar());
    function pausarActividad() {pausaHasta = performance.now() + 6000; orbit.autoRotate = false; programar();}
    function iniciarGiro() {
        pausaHasta = 0; ultimoFrame = 0;
        if (reanudar !== null) {clearTimeout(reanudar); reanudar = null;}
        // El puntero real decide la pausa; un cambio de rama nunca hereda seis segundos de espera.
        sobreGrafo = marco.matches(':hover'); programar();
    }
    const dialogo = elemento.closest('dialog');
    dialogo.addEventListener('pointerdown', pausarActividad, true); dialogo.addEventListener('click', pausarActividad, true); dialogo.addEventListener('keydown', pausarActividad, true);
    marco.addEventListener('pointerenter', () => {sobreGrafo = true; orbit.autoRotate = false; programar();});
    marco.addEventListener('pointerleave', () => {sobreGrafo = false; ultimoFrame = 0; programar();});
    const visibilidad = () => {ultimoFrame = 0; programar();}; document.addEventListener('visibilitychange', visibilidad);
    dialogo.addEventListener('close', visibilidad);
    movimientoReducido.addEventListener('change', visibilidad);
    function ajustar() {
        if (!dibujo) return;
        caja.makeEmpty();
        for (let i = 0; i < dibujo.claves.length; i++) if (!dibujo.visibles || dibujo.visibles[i]) caja.expandByPoint(punto.fromArray(dibujo.posiciones, i * 3));
        if (caja.isEmpty()) return;
        // El encuadre se calcula alrededor de la raíz, incluso en ramas asimétricas.
        centro.fromArray(dibujo.posiciones, 0);
        const vertical = Math.tan(camara.fov * Math.PI / 360);
        distanciaAjustada = 210;
        for (let i = 0; i < dibujo.claves.length; i++) {
            if (dibujo.visibles && !dibujo.visibles[i]) continue;
            punto.fromArray(dibujo.posiciones, i * 3).sub(centro);
            const profundidad = punto.dot(direccionInicial);
            distanciaAjustada = Math.max(distanciaAjustada, profundidad + Math.max(
                Math.abs(punto.dot(arribaInicial)) / (vertical * .82),
                Math.abs(punto.dot(derechaInicial)) / (vertical * camara.aspect * .86)));
        }
        firmaEncuadre = false; orbit.autoRotate = false;
        orbit.target.copy(centro); camara.position.copy(centro).add(direccionInicial.clone().multiplyScalar(distanciaAjustada));
        orbit.update(); programar();
    }
    function zoom(paso) {
        if (!dibujo) return;
        const direccion = camara.position.clone().sub(orbit.target);
        const distancia = Math.max(orbit.minDistance, Math.min(orbit.maxDistance, direccion.length() / paso));
        camara.position.copy(orbit.target).add(direccion.setLength(distancia)); orbit.update(); programar();
    }
    const resize = new ResizeObserver(() => {
        if (!vista.clientWidth || !vista.clientHeight || destruido) return;
        const w = vista.clientWidth, h = vista.clientHeight; if (w === ancho && h === alto) return;
        const factor = dibujo ? distanciaAjustada / Math.max(8, orbit.getDistance()) : 1;
        ancho = w; alto = h; camara.aspect = w / h; camara.updateProjectionMatrix(); renderer.setSize(w, h, false);
        ajustar(); if (Math.abs(factor - 1) > .001) zoom(factor); programar();
    }); resize.observe(vista);

    function liberar(objeto) {if (!objeto) return; escena.remove(objeto); objeto.geometry.dispose(); objeto.material.dispose();}
    function cancelarIndice() {
        if (idle !== null) {if (globalThis.cancelIdleCallback) cancelIdleCallback(idle); else clearTimeout(idle); idle = null;}
    }
    function indexar(solicitud, forzar = false) {
        firmaEncuadre = false;
        if (!forzar && dibujoIndexado === dibujo && (worker || espacial && indiceListo)) {
            if (worker) worker.postMessage({tipo: 'revision', revision: solicitud, idIndice});
            else marco.dataset.indiceReutilizado = 'true';
            return;
        }
        cancelarIndice(); espacial = null; indiceListo = false; marco.dataset.indexados = '0';
        dibujoIndexado = dibujo; idIndice++; marco.dataset.indiceReutilizado = 'false';
        if (worker) {
            const posiciones = dibujo.posiciones.slice(), radios = tamanos.slice(), visibles = dibujo.visibles?.slice();
            worker.postMessage({tipo: 'indexar', posiciones, tamanos: radios, visibles, revision: solicitud, idIndice},
                [posiciones.buffer, radios.buffer, ...(visibles ? [visibles.buffer] : [])]);
            marco.dataset.indiceHilo = 'worker'; return;
        }
        marco.dataset.indiceHilo = 'principal';
        espacial = octree().x(i => dibujo.posiciones[i * 3]).y(i => dibujo.posiciones[i * 3 + 1]).z(i => dibujo.posiciones[i * 3 + 2]);
        indiceCursor = 0;
        const agregar = () => {
            idle = null; if (destruido || solicitud !== revision) return;
            const inicio = performance.now();
            do {
                const fin = Math.min(dibujo.claves.length, indiceCursor + 2048);
                const lote = []; for (let i = indiceCursor; i < fin; i++) if (!dibujo.visibles || dibujo.visibles[i]) lote.push(i);
                espacial.addAll(lote); indiceCursor = fin;
            } while (indiceCursor < dibujo.claves.length && performance.now() - inicio < 6);
            marco.dataset.indexados = String(espacial.size());
            indiceListo = indiceCursor >= dibujo.claves.length;
            if (indiceListo) prepararRadiosEspaciales(espacial, tamanos);
            if (indiceCursor < dibujo.claves.length) idle = globalThis.requestIdleCallback ? requestIdleCallback(agregar, {timeout: 150}) : setTimeout(agregar, 16);
            else programar();
        }; agregar();
    }
    async function actualizarEncuadre(tiempo) {
        if (!dibujo || !indiceListo || consultandoEncuadre || indicesVisibles.length < 2048) return;
        matrizEncuadre.multiplyMatrices(camara.projectionMatrix, camara.matrixWorldInverse);
        const elementos = matrizEncuadre.elements;
        if (firmaEncuadre && elementos.every((valor, i) => Math.abs(valor - matrizAnterior[i]) < 1e-7)) return;
        if (tiempo - ultimoEncuadre < 120) {
            if (esperaEncuadre === null) esperaEncuadre = setTimeout(() => {esperaEncuadre = null; programar();}, 120 - (tiempo - ultimoEncuadre));
            return;
        }
        firmaEncuadre = true; matrizAnterior.set(elementos); ultimoEncuadre = tiempo;
        encuadre.setFromProjectionMatrix(matrizEncuadre);
        const margen = maxTamano * 2 * Math.tan(camara.fov * Math.PI / 360) * orbit.getDistance() / Math.max(1, alto);
        let todoDentro = true;
        for (let i = 0; i < encuadre.planes.length; i++) {
            const plano = encuadre.planes[i], normal = plano.normal, offset = i * 4;
            planosEncuadre[offset] = normal.x; planosEncuadre[offset + 1] = normal.y;
            planosEncuadre[offset + 2] = normal.z; planosEncuadre[offset + 3] = plano.constant + margen;
            if (normal.x * (normal.x >= 0 ? caja.min.x : caja.max.x) + normal.y * (normal.y >= 0 ? caja.min.y : caja.max.y)
                + normal.z * (normal.z >= 0 ? caja.min.z : caja.max.z) + plano.constant + margen < 0) todoDentro = false;
        }
        // En la vista general no hace falta visitar el octree ni copiar índices cada 120 ms.
        if (todoDentro) {
            if (recortado) {
                const indices = puntos.geometry.getIndex(); indices.array.set(indicesVisibles);
                indices.clearUpdateRanges(); indices.addUpdateRange(0, indicesVisibles.length); indices.needsUpdate = true;
                puntos.geometry.setDrawRange(0, indicesVisibles.length); marco.dataset.enEncuadre = String(indicesVisibles.length); recortado = false;
            }
            marco.dataset.descarte = 'vista-completa'; return;
        }
        consultandoEncuadre = true; marco.dataset.descarte = 'octree';
        const solicitud = revision, id = ++idWorker;
        try {
            const resultado = worker ? await new Promise((resolve, reject) => {
                pendientes.set(id, {resolve, reject}); worker.postMessage({tipo: 'encuadre', id, revision: solicitud, planos: planosEncuadre});
            }) : {indices: nodosEnEncuadre(espacial, dibujo.posiciones, planosEncuadre)};
            if (destruido || solicitud !== revision || !resultado.indices) return;
            const indices = puntos.geometry.getIndex();
            // Reutiliza la memoria GPU; cada consulta sólo transfiere los índices visibles.
            if (resultado.indices.length) {
                indices.array.set(resultado.indices); indices.clearUpdateRanges();
                indices.addUpdateRange(0, resultado.indices.length); indices.needsUpdate = true;
            }
            puntos.geometry.setDrawRange(0, resultado.indices.length);
            recortado = true;
            marco.dataset.enEncuadre = String(resultado.indices.length);
            marco.dataset.octantesDescartados = String(resultado.estadisticas?.descartados || 0);
            marco.dataset.cullMs = (resultado.tiempo || 0).toFixed(2);
            programar();
        } catch {firmaEncuadre = false;}
        finally {pendientes.delete(id); consultandoEncuadre = false; if (!destruido) programar();}
    }
    async function buscarPunto(evento) {
        if (!dibujo || (!worker && !espacial)) return -1;
        const rect = canvas.getBoundingClientRect();
        camara.updateMatrixWorld();
        puntero.set((evento.clientX - rect.left) / rect.width * 2 - 1, 1 - (evento.clientY - rect.top) / rect.height * 2);
        rayo.setFromCamera(puntero, camara);
        const origen = rayo.ray.origin.toArray(), direccion = rayo.ray.direction.toArray();
        const escalaPixel = Math.tan(camara.fov * Math.PI / 360) * 2 / rect.height;
        const mundos = {distancia: distanciaAjustada, maxTamano, factor: rangoActivo.value ? 1.5 : 1,
            frente: camara.getWorldDirection(new Vector3()).toArray(), seleccionado: indiceElegido,
            pulso: puntos?.material.uniforms.selectionPulse.value || 0};
        if (!worker) return buscarNodoEspacial(espacial, dibujo.posiciones, origen, direccion, escalaPixel, null, {...mundos, tamanos});
        const id = ++idWorker, solicitud = revision;
        try {
            const resultado = await new Promise((resolve, reject) => {pendientes.set(id, {resolve, reject});
                worker.postMessage({tipo: 'seleccionar', id, revision: solicitud, origen, direccion, escalaPixel, mundos});});
            return solicitud === revision && !destruido ? resultado : -1;
        } catch {return -1;}
        finally {pendientes.delete(id);}
    }
    function resaltar(i) {
        if (!dibujo || i === nodoResaltado) return;
        nodoResaltado = i;
        const nodo = i >= 0 && (!dibujo.visibles || dibujo.visibles[i]) ? indice.porClave.get(dibujo.claves[i]) : null;
        const seleccionado = indice.porClave.get(seleccionElegida);
        const actual = nodo || seleccionado;
        const texto = actual ? `${textoLinajeTaxonomico(indice, actual.clave)} · ${numero(actual.total)} ${actual.total === 1 ? 'registro' : 'registros'}` : ayudaActual();
        instrucciones.textContent = texto; instrucciones.title = texto;
        if (nodo) resaltado.material.color.set(rangoVisual3D(nodo).color);
        marco.dataset.resaltado = nodo?.nombre || '';
        const vertices = [], vistos = new Set();
        while (i > 0 && !vistos.has(i)) {
            vistos.add(i); const padre = dibujo.padres[i]; if (padre < 0) break;
            vertices.push(...dibujo.posiciones.subarray(i * 3, i * 3 + 3), ...dibujo.posiciones.subarray(padre * 3, padre * 3 + 3)); i = padre;
        }
        resaltado.geometry.dispose(); resaltado.geometry = new BufferGeometry();
        if (vertices.length) resaltado.geometry.setAttribute('position', new BufferAttribute(Float32Array.from(vertices), 3));
        resaltado.visible = !!vertices.length; programar();
    }
    function iluminarRango(rango) {
        if (!dibujo || !puntos || rangoActivo.value === (rango?.indice || 0)) return;
        rangoActivo.value = rango?.indice || 0;
        marco.dataset.rangoResaltado = rango?.id || '';
        resaltar(-1);
        const camino = puntos.geometry.getAttribute('onPath'), ramas = conexiones.geometry.getAttribute('onPath');
        camino.array.fill(0); ramas.array.fill(0);
        if (rango) {
            for (let i = 0; i < dibujo.claves.length; i++)
                if ((!dibujo.visibles || dibujo.visibles[i]) && rangoVisual3D(indice.porClave.get(dibujo.claves[i])).id === rango.id) camino.array[i] = 1;
            // El recorrido empaquetado coloca cada padre antes de sus hijos. Una sola
            // pasada inversa une TODOS los caminos a la raíz sin repetir sus segmentos.
            for (let i = dibujo.claves.length - 1; i > 0; i--)
                if (camino.array[i] && dibujo.padres[i] >= 0) camino.array[dibujo.padres[i]] = 1;
            for (let i = 1; i < dibujo.claves.length; i++)
                if (camino.array[i]) ramas.array[(i - 1) * 2] = ramas.array[(i - 1) * 2 + 1] = 1;
            instrucciones.textContent = filtroRango ? ayudaActual() : `${rango.nombre} · ${numero(rango.nodos.length)} taxones · Caminos hasta la raíz`;
        } else {resaltar(indiceElegido); if (indiceElegido < 0) instrucciones.textContent = ayudaActual();}
        camino.needsUpdate = true; ramas.needsUpdate = true;
        marco.dataset.nodosIluminados = String(camino.array.reduce((total, valor) => total + valor, 0));
        marco.dataset.ramasIluminadas = String(ramas.array.reduce((total, valor) => total + valor, 0) / 2);
        programar();
    }
    function ayudaActual() {
        const rango = rangosActuales.get(filtroRango);
        const nodo = indice?.porClave.get(clave);
        const linaje = nodo ? `${textoLinajeTaxonomico(indice, clave)} · ${numero(nodo.total)} registros` : '';
        return rango ? `${linaje} · ${rango.nombre}: ${numero(rango.nodos.length)} taxones` : linaje;
    }
    function marcarSeleccion() {
        if (!dibujo || !puntos) return;
        const seleccion = puntos.geometry.getAttribute('selectedNode');
        if (indiceElegido >= 0 && indiceElegido < seleccion.count) seleccion.setX(indiceElegido, 0);
        indiceElegido = dibujo.claves.indexOf(seleccionElegida);
        if (indiceElegido >= 0 && dibujo.visibles && !dibujo.visibles[indiceElegido]) indiceElegido = -1;
        if (indiceElegido >= 0) seleccion.setX(indiceElegido, 1);
        seleccion.needsUpdate = true;
        marco.dataset.seleccion = seleccionElegida;
        marco.dataset.nombreSeleccion = indice.porClave.get(seleccionElegida)?.nombre || '';
        marco.dataset.seleccionVisible = String(indiceElegido >= 0);
        resaltar(rangoActivo.value ? -1 : indiceElegido); programar();
    }
    async function seleccionar(claveSeleccion) {
        if (!indice?.porClave.has(claveSeleccion) || destruido) return;
        seleccionElegida = claveSeleccion;
        const i = dibujo?.claves.indexOf(claveSeleccion) ?? -1;
        if (filtroRango && (i < 0 || dibujo.visibles && !dibujo.visibles[i])) await escogerRango(null);
        if (!destruido) marcarSeleccion();
    }
    async function localizar(claveSeleccion) {
        if (!dibujoCompleto?.claves.includes(claveSeleccion) || destruido) return false;
        await seleccionar(claveSeleccion);
        if (destruido || seleccionElegida !== claveSeleccion || indiceElegido < 0) return false;
        // Encuadra toda la rama antes de situar el taxón, independientemente del zoom anterior.
        ajustar(); punto.fromArray(dibujo.posiciones, indiceElegido * 3);
        const desplazamiento = camara.position.clone().sub(orbit.target);
        orbit.target.copy(punto); camara.position.copy(punto).add(desplazamiento);
        pausarActividad(); orbit.update();
        pulsoHasta = performance.now() + 8000;
        const nodo = indice.porClave.get(claveSeleccion);
        instrucciones.textContent = `${textoLinajeTaxonomico(indice, claveSeleccion)} · ${numero(nodo.total)} registros`;
        instrucciones.title = instrucciones.textContent;
        canvas.setAttribute('aria-label', `${indice.porClave.get(clave).nombre}: taxón localizado ${indice.porClave.get(claveSeleccion).nombre}. Flechas para girar, + y − para zoom, Inicio para ajustar; Intro convierte la selección en raíz.`);
        programar(); return true;
    }
    async function escogerRango(rango) {
        if (!dibujoCompleto || destruido) return;
        const siguiente = rango?.id === filtroRango ? '' : rango?.id || '';
        const solicitud = ++revision, solicitudFiltro = ++revisionFiltro, inicio = performance.now();
        consultaPuntero.cancelar();leyendaFlotante.ocultar();
        marco.setAttribute('aria-busy', 'true');
        try {
            const resultado = await preparar(clave, siguiente);
            if (destruido || solicitud !== revision || solicitudFiltro !== revisionFiltro) return;
            dibujo = resultado; filtroRango = siguiente; dibujar(); indexar(solicitud); ajustar(); iniciarGiro();
            marco.dataset.filtroMs = (performance.now() - inicio).toFixed(2);
        } catch (error) {if (solicitud === revision) mostrarError('No se pudo cambiar el rango. Selecciónalo de nuevo para reintentar.'); console.error('Filtro 3D:', error);}
        finally {if (solicitud === revision) marco.setAttribute('aria-busy', 'false');}
    }
    function mostrarLeyenda(i) {
        const nodo=i>=0&&(!dibujo.visibles||dibujo.visibles[i])?indice.porClave.get(dibujo.claves[i]):null;
        if(!nodo)return leyendaFlotante.ocultar();
        camara.updateMatrixWorld();punto.fromArray(dibujo.posiciones,i*3).project(camara);
        leyendaFlotante.mostrar(nodo,{x:(punto.x+1)*ancho/2,y:(1-punto.y)*alto/2});
    }
    const consultaPuntero = crearConsultaPuntero({consultar: buscarPunto,
        mostrar: i => {if (!destruido && dibujo) {canvas.style.cursor = i >= 0 ? 'pointer' : 'grab'; resaltar(i);mostrarLeyenda(i);}}});
    canvas.addEventListener('pointerdown', evento => {consultaPuntero.cancelar();leyendaFlotante.ocultar(); if (evento.button === 0) pulsacion = {x: evento.clientX, y: evento.clientY, id: evento.pointerId};});
    canvas.addEventListener('pointerup', async evento => {
        const inicio = pulsacion; pulsacion = null;
        if (!inicio || inicio.id !== evento.pointerId || Math.hypot(evento.clientX - inicio.x, evento.clientY - inicio.y) > 5) return;
        const solicitud = revision, i = await buscarPunto(evento); if (solicitud === revision && i >= 0) acciones.seleccionar(dibujo.claves[i]);
    });
    canvas.addEventListener('pointercancel', () => {pulsacion = null;});
    canvas.addEventListener('pointermove', evento => {if (!pulsacion) consultaPuntero.mover(evento);});
    canvas.addEventListener('pointerleave', () => {consultaPuntero.cancelar();leyendaFlotante.ocultar(); canvas.style.cursor = 'grab'; if (dibujo) resaltar(rangoActivo.value ? -1 : indiceElegido);});
    canvas.addEventListener('contextmenu', async evento => {
        evento.preventDefault(); evento.stopPropagation(); pausarActividad();
        // currentTarget deja de existir al terminar el despacho del evento, antes
        // de que responda el octree. Conservamos el origen y las coordenadas.
        const origen = {type: evento.type, clientX: evento.clientX, clientY: evento.clientY, currentTarget: canvas};
        const solicitud = revision, i = await buscarPunto(origen);
        if (destruido || solicitud !== revision || i < 0) return;
        camara.updateMatrixWorld();
        const rect = canvas.getBoundingClientRect();
        punto.fromArray(dibujo.posiciones, i * 3);
        const profundidad = -punto.clone().applyMatrix4(camara.matrixWorldInverse).z;
        const perspectiva = Math.max(.55, Math.min(3.6, distanciaAjustada / Math.max(1, profundidad)));
        const geometria = puntos.geometry;
        const iluminado = geometria.getAttribute('onPath').getX(i);
        const coincide = Math.abs(geometria.getAttribute('rankId').getX(i) - rangoActivo.value) < .1;
        const enfasis = rangoActivo.value ? .85 + .4 * iluminado + (coincide ? .25 : 0) : 1;
        const radio = tamanos[i] * perspectiva * enfasis * .45;
        punto.project(camara);
        const x = rect.left + (punto.x + 1) * rect.width / 2, y = rect.top + (1 - punto.y) * rect.height / 2;
        acciones.menu(dibujo.claves[i], origen, {left: x - radio, right: x + radio, top: y - radio, bottom: y + radio, limite: rect});
    });
    canvas.addEventListener('keydown', evento => {
        if (!['+', '=', '-', 'Home', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'PageDown', 'PageUp', 'Enter'].includes(evento.key)) return;
        evento.preventDefault(); evento.stopPropagation();
        if (evento.key === 'Home') return ajustar();
        if (['+', '='].includes(evento.key)) return zoom(1.25);
        if (evento.key === '-') return zoom(1 / 1.25);
        if (evento.key === 'Enter') {if (nodoResaltado >= 0) acciones.seleccionar(dibujo.claves[nodoResaltado]); return;}
        if (['PageDown', 'PageUp'].includes(evento.key)) {
            if (!hijosDirectos.length) return;
            hijoTeclado = (hijoTeclado + (evento.key === 'PageDown' ? 1 : -1) + hijosDirectos.length) % hijosDirectos.length;
            resaltar(hijosDirectos[hijoTeclado]);mostrarLeyenda(hijosDirectos[hijoTeclado]); return;
        }
        const vector = camara.position.clone().sub(orbit.target);
        const eje = ['ArrowLeft', 'ArrowRight'].includes(evento.key) ? new Vector3(0, 1, 0) : new Vector3(1, 0, 0);
        vector.applyAxisAngle(eje, ['ArrowRight', 'ArrowDown'].includes(evento.key) ? .12 : -.12);
        camara.position.copy(orbit.target).add(vector); orbit.update(); programar();
    });
    canvas.addEventListener('webglcontextlost', evento => {evento.preventDefault(); mostrarError('La vista 3D se ha pausado. Selecciona la rama de nuevo cuando el navegador recupere los gráficos.');});
    canvas.addEventListener('webglcontextrestored', () => {estado.hidden = true; programar();});

    function prepararLeyenda(rangos) {
        rangosActuales = rangos;
        rangoActivo.value = 0;
        marco.dataset.rangoResaltado = ''; marco.dataset.nodosIluminados = '0'; marco.dataset.ramasIluminadas = '0';
        leyenda.replaceChildren();
        const niveles = [...rangos.values()].sort((a, b) => a.orden - b.orden || a.nombre.localeCompare(b.nombre, 'es'));
        const fragmento = document.createDocumentFragment();
        for (const rango of niveles) {
            const item = boton(`Filtrar ${rango.nombre}: ${numero(rango.nodos.length)} taxones; conservar la raíz`, rango.nombre, () => escogerRango(rango));
            item.className = 'portal-three-legend-item';
            item.setAttribute('aria-pressed', 'false');
            item.style.setProperty('--rank-color', rango.color); item.dataset.rango = rango.id; fragmento.append(item);
            item.addEventListener('pointerenter', () => iluminarRango(rango)); item.addEventListener('focus', () => iluminarRango(rango));
            item.addEventListener('pointerleave', () => {if (document.activeElement !== item) iluminarRango(null);});
            item.addEventListener('blur', () => iluminarRango(null));
        }
        leyenda.append(fragmento);
    }
    async function preparar(raiz, rango = '') {
        const claveCache = JSON.stringify([raiz, rango]);
        const fuenteSolicitud = fuente;
        let lectura;
        lectura = cache.obtener(claveCache, async () => {
            // Una renovación espera al navegador; no bloquea la geometría que ya está visible.
            if (lectura?.estado === 'stale') await new Promise(resolve => {
                if (globalThis.requestIdleCallback) requestIdleCallback(resolve, {timeout: 500}); else setTimeout(resolve, 16);
            });
            if (destruido || fuente !== fuenteSolicitud) throw new Error('La fuente pública ha cambiado.');
            if (worker) {
                const id = ++idWorker;
                return new Promise((resolve, reject) => {pendientes.set(id, {resolve, reject}); worker.postMessage({id, raiz, rango, tipo: 'dibujar'});});
            }
            jerarquiaFallback ||= crearIndiceRama3D(fuente);
            const completo = rango && dibujoCompleto?.claves[0] === raiz ? dibujoCompleto
                : distribuirRama3D(fuente, raiz, jerarquiaFallback, Boolean(rango));
            return filtrarRama3D(completo, jerarquiaFallback.porClave, rango);
        });
        marco.dataset.cacheOrigen = lectura.estado; informarCache();
        if (lectura.valor) {
            lectura.promesa?.then(() => {if (!destruido && fuente === fuenteSolicitud) {delete marco.dataset.cacheError; informarCache();}}, () => {
                if (!destruido && fuente === fuenteSolicitud) {marco.dataset.cacheError = 'revalidacion'; informarCache();}
            });
            return lectura.valor;
        }
        try {return await lectura.promesa;}
        finally {if (!destruido && fuente === fuenteSolicitud) informarCache();}
    }
    function informarCache() {
        const estadoCache = cache.estado();
        marco.dataset.cacheBytes = String(estadoCache.bytes); marco.dataset.cacheEntradas = String(estadoCache.entradas);
        marco.dataset.cacheLimiteBytes = String(estadoCache.maxBytes); marco.dataset.cacheAciertos = String(estadoCache.aciertos);
        marco.dataset.cacheStale = String(estadoCache.stale); marco.dataset.cacheRenovaciones = String(estadoCache.renovaciones);
        marco.dataset.cachePendientes = String(estadoCache.pendientes);
    }
    function mostrarError(mensaje) {estado.textContent = mensaje; estado.hidden = false; estado.setAttribute('role', 'alert');}
    function dibujar() {
            cancelarIndice(); nodoResaltado = -1; indiceElegido = -1; rangoActivo.value = 0; instrucciones.textContent = ayudaActual(); marco.dataset.resaltado = '';
            consultaPuntero.cancelar();leyendaFlotante.ocultar(); firmaEncuadre = false; recortado = false;
            liberar(puntos); liberar(conexiones); resaltado.visible = false;
            const cantidad = dibujo.claves.length, rgb = new Float32Array(cantidad * 3);
            const TipoRango = rangosActuales.size <= 255 ? Uint8Array : rangosActuales.size <= 65535 ? Uint16Array : Float32Array;
            const idsRangos = new TipoRango(cantidad);
            const organismos = new Uint8Array(cantidad), visibles = new Uint8Array(cantidad), rangosVisibles = new Set();
            indicesVisibles = new Uint32Array(cantidad); let cantidadVisible = 0;
            tamanos = new Float32Array(cantidad); maxTamano = 0;
            let maxRegistros = 0;
            for (let i = 0; i < cantidad; i++) if (!dibujo.visibles || dibujo.visibles[i])
                maxRegistros = Math.max(maxRegistros, Number(indice.porClave.get(dibujo.claves[i]).total) || 0);
            const lineas = new Float32Array(Math.max(0, cantidad - 1) * 6), rgbLineas = new Float32Array(lineas.length);
            for (let i = 0; i < cantidad; i++) {
                const estilo = rangoVisual3D(indice.porClave.get(dibujo.claves[i]));
                const rango = rangosActuales.get(estilo.id); idsRangos[i] = rango.indice;
                if (!colores.has(rango.id)) colores.set(rango.id, new Color(rango.color));
                colores.get(rango.id).toArray(rgb, i * 3);
                tamanos[i] = i === 0 ? 92 : filtroRango ? Math.max(9, Math.min(38, 180 / Math.sqrt(Math.max(1, dibujo.cantidadVisible - 1))))
                    : dibujo.padres[i] === 0 ? 26 : Math.max(4.5, 11 - Math.log10(Math.max(10, cantidad)) * .7);
                tamanos[i] *= factorAbundancia3D(indice.porClave.get(dibujo.claves[i]).total, maxRegistros);
                maxTamano = Math.max(maxTamano, tamanos[i]);
                visibles[i] = !dibujo.visibles || dibujo.visibles[i] ? 1 : 0;
                if (visibles[i]) {indicesVisibles[cantidadVisible++] = i; rangosVisibles.add(estilo.id);}
                organismos[i] = GRUPOS_BIOLOGICOS_3D.indexOf(grupoBiologico3D(indice.porClave.get(dibujo.claves[i]), indice.porClave, gruposBiologicos));
            }
            for (let i = 1; i < cantidad; i++) {
                const padre = dibujo.padres[i]; if (padre < 0) continue;
                const offset = (i - 1) * 6;
                lineas.set(dibujo.posiciones.subarray(padre * 3, padre * 3 + 3), offset); lineas.set(dibujo.posiciones.subarray(i * 3, i * 3 + 3), offset + 3);
                rgbLineas.set(rgb.subarray(padre * 3, padre * 3 + 3), offset); rgbLineas.set(rgb.subarray(i * 3, i * 3 + 3), offset + 3);
            }
            const geometria = new BufferGeometry(); geometria.setAttribute('position', new BufferAttribute(dibujo.posiciones, 3));
            geometria.setAttribute('color', new BufferAttribute(rgb, 3)); geometria.setAttribute('pointSize', new BufferAttribute(tamanos, 1));
            geometria.setAttribute('rankId', new BufferAttribute(idsRangos, 1));
            geometria.setAttribute('onPath', new BufferAttribute(new Uint8Array(cantidad), 1));
            geometria.setAttribute('selectedNode', new BufferAttribute(new Uint8Array(cantidad), 1));
            geometria.setAttribute('organismType', new BufferAttribute(organismos, 1)); geometria.setAttribute('visibleNode', new BufferAttribute(visibles, 1));
            indicesVisibles = indicesVisibles.subarray(0, cantidadVisible);
            const indicesGPU = new Uint32Array(cantidad); indicesGPU.set(indicesVisibles);
            geometria.setIndex(new BufferAttribute(indicesGPU, 1).setUsage(DynamicDrawUsage)); geometria.setDrawRange(0, indicesVisibles.length);
            puntos = new Points(geometria, materialMundos3D(renderer.getPixelRatio(), rangoActivo, atlasBiologico)); puntos.renderOrder = 2; escena.add(puntos);
            const geometriaLineas = new BufferGeometry(); geometriaLineas.setAttribute('position', new BufferAttribute(lineas, 3)); geometriaLineas.setAttribute('color', new BufferAttribute(rgbLineas, 3));
            geometriaLineas.setAttribute('onPath', new BufferAttribute(new Uint8Array(Math.max(0, cantidad - 1) * 2), 1));
            conexiones = new LineSegments(geometriaLineas, new ShaderMaterial({vertexColors: true, transparent: true, depthWrite: false,
                uniforms: {activeRank: rangoActivo},
                vertexShader: 'attribute float onPath; uniform float activeRank; varying vec3 shade; varying float strength; void main(){shade=activeRank>0.5?mix(color,vec3(1.0),onPath*0.24):mix(color,vec3(0.23,0.30,0.38),0.28); strength=activeRank<0.5?0.32:mix(0.025,0.85,onPath); gl_Position=projectionMatrix*modelViewMatrix*vec4(position,1.0);}',
                fragmentShader: 'varying vec3 shade; varying float strength; void main(){gl_FragColor=vec4(shade,strength);\n#include <colorspace_fragment>\n}',
            }));
            conexiones.visible = cantidad > 1; escena.add(conexiones);
            const nodo = indice.porClave.get(clave), rango = rangosActuales.get(filtroRango);
            canvas.setAttribute('aria-label', `${nodo.nombre}: ${rango ? `raíz visible y filtro ${rango.nombre}, ${numero(rango.nodos.length)} taxones` : `raíz y ${numero(cantidad - 1)} descendientes`}. Flechas para girar, + y − para zoom, Inicio para ajustar. AvPág y RePág recorren los hijos, Intro convierte el nodo en raíz y muestra su ficha.`);
            canvas.setAttribute('aria-keyshortcuts', 'ArrowLeft ArrowRight ArrowUp ArrowDown PageDown PageUp Enter Home');
            marco.dataset.raiz = clave; marco.dataset.nombre = nodo.nombre; marco.dataset.nodos = String(indicesVisibles.length); marco.dataset.relaciones = String(cantidad - 1);
            marco.dataset.registros = String(nodo.total); marco.dataset.profundidad = String(dibujo.profundidadMaxima);
            marco.dataset.rangoFiltrado = filtroRango; marco.dataset.nodosTotales = String(dibujoCompleto.claves.length);
            marco.dataset.enEncuadre = String(indicesVisibles.length); marco.dataset.nodosIluminados = '0'; marco.dataset.ramasIluminadas = '0';
            marco.dataset.rangoResaltado = '';
            marco.dataset.material = 'mundos-biologicos'; marco.dataset.grupoBiologico = grupoBiologico3D(nodo, indice.porClave, gruposBiologicos);
            marco.dataset.rangosVisibles = [...rangosVisibles].join(',');
            marco.dataset.descarte = indicesVisibles.length < 2048 ? 'rama-pequena' : 'pendiente';
            hijosDirectos = []; hijoTeclado = -1;
            for (let i = 1; i < cantidad; i++) if (dibujo.padres[i] === 0 && visibles[i]) hijosDirectos.push(i);
            for (const item of leyenda.children) {
                item.setAttribute('aria-pressed', String(item.dataset.rango === filtroRango)); item.disabled = false;
            }
            estado.hidden = true;
            marcarSeleccion();
    }
    async function pantallaCompleta() {
        if (document.fullscreenElement === marco) return document.exitFullscreen();
        await marco.requestFullscreen(); canvas.focus({preventScroll: true});
    }
    return {
        async actualizar(nuevos, raiz, nuevoIndice) {
            if (destruido) return;
            leyendaFlotante.ocultar();
            const cambioFuente = fuente !== nuevos;
            indice = nuevoIndice;
            const nodo = indice.porClave.get(raiz); if (!nodo) throw new Error('Raíz pública no disponible.');
            titulo.textContent = nodo.nombre; meta.textContent = `${nodo.etiqueta} · ${numero(nodo.total)} ${nodo.total === 1 ? 'registro' : 'registros'}`;
            volver.disabled = !indice.porClave.has(nodo.padre);
            if (!cambioFuente && clave === raiz && dibujo) {
                // Volver al dibujo actual cancela una raíz distinta que siga preparándose.
                if (!estado.hidden) {
                    revision++; revisionFiltro++; consultaPuntero.cancelar(); estado.hidden = true; indexar(revision);
                    for (const item of leyenda.children) item.disabled = false;
                }
                if (filtroRango) await escogerRango(null);
                if (cache.contiene(JSON.stringify([raiz, '']))) preparar(raiz).catch(() => {});
                seleccionElegida = clave; marcarSeleccion(); iniciarGiro(); return;
            }
            const solicitud = ++revision, inicio = performance.now(); revisionFiltro++; consultaPuntero.cancelar();
            if (cambioFuente) {
                fuente = nuevos; cache.limpiar(); gruposBiologicos.clear(); jerarquiaFallback = null; dibujoIndexado = null;
                // Una fuente pública distinta puede retirar nodos: liberar también la escena anterior.
                cancelarIndice(); espacial = null; indiceListo = false; dibujo = dibujoCompleto = null; indicesVisibles = null;
                liberar(puntos); liberar(conexiones); puntos = conexiones = null; resaltado.visible = false;
                worker?.postMessage({tipo: 'fuente', nodos: nuevos.map(({clave, padre, rango, etiqueta}) => ({clave, padre, rango, etiqueta}))});
            }
            for (const item of leyenda.children) item.disabled = true;
            estado.setAttribute('role', 'status'); estado.textContent = 'Abriendo las ramas…'; estado.hidden = false;
            const resultado = await preparar(raiz);
            if (destruido || solicitud !== revision) return;
            dibujoCompleto = dibujo = resultado; clave = raiz; seleccionElegida = raiz; filtroRango = '';
            const rangos = new Map();
            for (let i = 0; i < resultado.claves.length; i++) {
                const estilo = rangoVisual3D(indice.porClave.get(resultado.claves[i]));
                if (!rangos.has(estilo.id)) rangos.set(estilo.id, {...estilo, indice: rangos.size + 1, nodos: []});
                rangos.get(estilo.id).nodos.push(i);
            }
            prepararLeyenda(rangos); dibujar(); indexar(solicitud); ajustar(); iniciarGiro();
            marco.dataset.renderMs = (performance.now() - inicio).toFixed(2);
        },
        ajustar, zoom, seleccionar, localizar,
        pantallaCompleta,
        limpiarSeleccion() {seleccionElegida = ''; pulsoHasta = 0; marcarSeleccion(); ajustar();},
        rangoActivo: () => filtroRango,
        enfocar(claveSeleccion) {
            const i = dibujo?.claves.indexOf(claveSeleccion) ?? -1;
            if (i < 0 || dibujo.visibles && !dibujo.visibles[i]) return false;
            canvas.focus({preventScroll: true}); resaltar(i); return true;
        },
        contiene: claveSeleccion => Boolean(dibujoCompleto?.claves.includes(claveSeleccion)), error: mostrarError,
        mostrar(valor) {
            activo = valor; cabecera.hidden = !valor; maximizar.hidden = !valor;
            if (valor) {ultimoFrame = 0; programar();}
            else {
                orbit.autoRotate = false; marco.dataset.rotando = 'false';
                if (frame !== null) {cancelAnimationFrame(frame); frame = null;}
                for (const timer of [esperaFrame, reanudar, esperaEncuadre]) if (timer !== null) clearTimeout(timer);
                esperaFrame = reanudar = esperaEncuadre = null; consultaPuntero.cancelar();leyendaFlotante.ocultar();
            }
        },
        destruir() {
            if (document.fullscreenElement === marco) document.exitFullscreen().catch(() => {});
            destruido = true; revision++; cancelarIndice(); resize.disconnect(); orbit.dispose(); worker?.terminate();
            dialogo.removeEventListener('pointerdown', pausarActividad, true); dialogo.removeEventListener('click', pausarActividad, true); dialogo.removeEventListener('keydown', pausarActividad, true);
            document.removeEventListener('visibilitychange', visibilidad); if (reanudar !== null) clearTimeout(reanudar);
            dialogo.removeEventListener('close', visibilidad); if (esperaFrame !== null) clearTimeout(esperaFrame);
            movimientoReducido.removeEventListener('change', visibilidad);
            if (esperaEncuadre !== null) clearTimeout(esperaEncuadre);
            if (bootstrapWorker) URL.revokeObjectURL(bootstrapWorker);
            for (const promesa of pendientes.values()) promesa.reject(new Error('Visor cerrado.')); pendientes.clear(); cache.limpiar();
            if (frame !== null) cancelAnimationFrame(frame); consultaPuntero.destruir();leyendaFlotante.destruir();
            liberar(puntos); liberar(conexiones); liberar(resaltado); atlasBiologico.dispose(); gruposBiologicos.clear(); renderer.dispose(); renderer.forceContextLoss(); elemento.replaceChildren(); cabecera.remove(); maximizar.remove();
        },
    };
}
