import {BufferAttribute, BufferGeometry, Color, DoubleSide, Mesh, MeshBasicMaterial, OrthographicCamera, Points, Scene, ShaderMaterial, WebGLRenderer} from 'three';
import {crearArbolDiversidad, encuadreDiversidad, proyectarDiversidad, zoomDiversidad} from './portal-taxonomy-diversity-model';
import {rangoVisual3D} from './portal-taxonomy-three-ranks';
import {textoLinajeTaxonomico} from './portal-taxonomy-lineage';

const numero = n => Number(n).toLocaleString('es-EC');
const crear = (tag, clase, texto) => {const el = document.createElement(tag); el.className = clase; if (texto) el.textContent = texto; return el;};
const boton = (nombre, texto, accion) => {const el = crear('button', '', texto); el.type = 'button'; el.setAttribute('aria-label', nombre); el.title = nombre; el.addEventListener('click', accion); return el;};

/** Árbol plano en Three.js: zoom semántico, geometría agrupada y dibujo por demanda. */
export function crearEscenaDiversidad(elemento, acciones) {
    const marco = crear('div', 'portal-diversity-scene'); marco.dataset.motor = 'Three.js';
    const cabecera = crear('div', 'portal-three-context'), identidad = crear('div', 'portal-three-identity');
    const titulo = crear('h3', 'portal-three-title'), meta = crear('p', 'portal-three-meta'); identidad.append(titulo, meta);
    const controles = crear('div', 'portal-three-controls');
    const volver = boton('Volver al padre de la rama', '↶', () => {const padre = indice?.porClave.get(raiz)?.padre; if (padre) acciones.navegar(padre);});
    controles.append(volver); cabecera.append(identidad, controles);
    const ajuste = boton('Ajustar árbol de diversidad', '⤢', () => ajustar()); ajuste.className = 'portal-three-fit';
    const maximizar = boton('Ver visualizador en pantalla completa', '⛶', () => pantallaCompleta().catch(() => error('No se pudo activar la pantalla completa.'))); maximizar.className = 'portal-three-maximize';
    const barra = elemento.closest('.taxonomy-navigator').querySelector('.taxonomy-navigator-zoom');
    barra.prepend(cabecera); barra.append(ajuste, maximizar);
    const vista = crear('div', 'portal-diversity-viewport');
    const etiquetas = crear('div', 'portal-diversity-labels');
    const ayuda = crear('div', 'portal-diversity-guide');
    ayuda.id = 'portal-diversity-guide';
    ayuda.append(crear('strong', '', 'Árbol de diversidad'), crear('span', '', 'Rueda o pellizco: zoom · Arrastra: desplazar · Clic: explorar rama'));
    const escalaTexto = crear('output', 'portal-diversity-scale'); escalaTexto.setAttribute('aria-label', 'Nivel de zoom');
    const leyenda = crear('div', 'portal-diversity-legend'); leyenda.setAttribute('role', 'group'); leyenda.setAttribute('aria-label', 'Resaltar un rango conservando todas las relaciones publicadas');
    const instrucciones = crear('p', 'portal-three-instructions'); instrucciones.tabIndex = 0;
    instrucciones.setAttribute('role', 'status');
    const detalle = crear('div', 'portal-diversity-tooltip'); detalle.id = 'portal-diversity-tooltip'; detalle.setAttribute('role', 'tooltip'); detalle.hidden = true;
    const estado = crear('p', 'portal-diversity-status'); estado.setAttribute('role', 'alert'); estado.hidden = true;
    marco.append(vista, leyenda, instrucciones); elemento.replaceChildren(marco);
    const salir = boton('Salir de pantalla completa', '⛶', () => document.exitFullscreen()); salir.className = 'portal-diversity-fullscreen-exit'; marco.append(salir);
    let renderer;
    try {renderer = new WebGLRenderer({antialias: true, alpha: true, powerPreference: 'low-power'});}
    catch (fallo) {cabecera.remove(); ajuste.remove(); maximizar.remove(); elemento.replaceChildren(); throw fallo;}
    renderer.setPixelRatio(Math.min(globalThis.devicePixelRatio || 1, 1.5));
    const canvas = renderer.domElement; canvas.className = 'portal-diversity-canvas'; canvas.tabIndex = 0;
    canvas.setAttribute('role', 'img'); canvas.setAttribute('aria-keyshortcuts', 'ArrowLeft ArrowRight ArrowUp ArrowDown PageDown PageUp Enter Home + -');
    canvas.setAttribute('aria-describedby', ayuda.id);
    vista.append(canvas, etiquetas, ayuda, escalaTexto, detalle, estado);
    const escena = new Scene(), camara = new OrthographicCamera(-1, 1, 1, -1, 0, 20); camara.position.z = 10;
    const materialRamas = new MeshBasicMaterial({vertexColors: true, side: DoubleSide, transparent: true, opacity: .85, depthTest: false});
    const materialHojas = new ShaderMaterial({transparent: true, depthTest: false, depthWrite: false,
        uniforms: {pixelRatio: {value: renderer.getPixelRatio()}},
        vertexShader: 'attribute float size; attribute float leaf; attribute vec3 color; varying vec3 shade; varying float tip; uniform float pixelRatio; void main(){shade=color;tip=leaf;gl_PointSize=size*pixelRatio;gl_Position=projectionMatrix*modelViewMatrix*vec4(position,1.0);}',
        fragmentShader: 'varying vec3 shade; varying float tip; void main(){vec2 p=gl_PointCoord*2.0-1.0;float d=tip>0.5?abs(p.x+p.y)*0.5+pow(abs(p.x-p.y)*0.6,2.0):length(p);if(d>0.94)discard;gl_FragColor=vec4(mix(shade,vec3(0.12,0.28,0.23),smoothstep(0.67,0.94,d)*0.55),1.0);\n#include <colorspace_fragment>\n}'});
    const ramas = new Mesh(new BufferGeometry(), materialRamas), hojas = new Points(new BufferGeometry(), materialHojas);
    ramas.frustumCulled = hojas.frustumCulled = false; hojas.renderOrder = 2; escena.add(ramas, hojas);
    let indice, arbol, fuente, raiz, ancho = 1, alto = 1, cam = {x: 0, y: 0, escala: 1}, base = 1;
    let frame = null, destruido = false, activo = true, elegido = -1, resaltado = '', hijosTeclado = -1, puntos = [], arrastre = null;
    let limitesZoom = {min: .1, max: 10000}, revision = 0;
    const punteros = new Map(), colores = new Map(), etiquetasDOM = new Map(), abort = new AbortController();
    const escuchar = (el, tipo, fn, opciones = {}) => el.addEventListener(tipo, fn, {...opciones, signal: abort.signal});
    const color = valor => {if (!colores.has(valor)) colores.set(valor, new Color(valor)); return colores.get(valor);};
    function programar() {
        if (frame !== null || destruido || !activo || document.hidden) return;
        frame = requestAnimationFrame(() => {frame = null; if (!destruido && activo && elemento.closest('dialog')?.open && arbol) dibujar();});
    }
    function mensaje(i = elegido) {
        const clave = arbol?.claves[i >= 0 ? i : 0];
        instrucciones.textContent = `${textoLinajeTaxonomico(indice, clave)} · Jerarquía taxonómica, sin escala temporal`;
        instrucciones.title = instrucciones.textContent;
    }
    function error(texto) {estado.textContent = texto; estado.hidden = false;}
    function ajustar() {
        if (!arbol) return;
        cam = encuadreDiversidad(arbol, ancho, alto); base = cam.escala;
        limitesZoom = {min: base * .5, max: Math.max(base * 256, 70 / arbol.separacion)};
        programar();
    }
    function zoom(factor, punto = {x: 0, y: 0}) {if (!arbol) return; cam = zoomDiversidad(cam, factor, punto, limitesZoom); programar();}
    function liberarGeometria(objeto, atributos) {
        const anterior = objeto.geometry, nueva = new BufferGeometry();
        for (const [nombre, [valores, tamano]] of Object.entries(atributos)) nueva.setAttribute(nombre, new BufferAttribute(Float32Array.from(valores), tamano));
        objeto.geometry = nueva; anterior.dispose();
    }
    function dibujar() {
        ocultarDetalle();
        const inicio = performance.now(), lineas = [], tintas = [], posiciones = [], rgb = [], tamanos = [], terminal = [], candidatos = [];
        puntos = [];
        const pila = [0], verde = color('#619078'), tenue = color('#b4c2b9');
        const proyectar = i => proyectarDiversidad(arbol, i, cam, ancho, alto);
        function linea(a, b, grosor, tinta) {
            const distancia = Math.hypot(b.x - a.x, b.y - a.y); if (!distancia) return;
            const dx = -(b.y - a.y) / distancia * grosor / 2, dy = (b.x - a.x) / distancia * grosor / 2;
            for (const [x, y] of [[a.x+dx,a.y+dy],[a.x-dx,a.y-dy],[b.x+dx,b.y+dy],[b.x+dx,b.y+dy],[a.x-dx,a.y-dy],[b.x-dx,b.y-dy]]) {
                lineas.push(x - ancho / 2, alto / 2 - y, 0); tintas.push(tinta.r, tinta.g, tinta.b);
            }
        }
        while (pila.length) {
            const i = pila.pop(), padre = arbol.padres[i], p = proyectar(i), b = arbol.limites.subarray(i * 4, i * 4 + 4);
            const nodo = indice.porClave.get(arbol.claves[i]), rango = rangoVisual3D(nodo), lista = arbol.hijos[i];
            const coincide = !resaltado || rango.id === resaltado || i === 0 || i === elegido;
            if (padre >= 0) {
                const a = proyectar(padre);
                if (Math.max(a.x, p.x) >= -15 && Math.min(a.x, p.x) <= ancho + 15 && Math.max(a.y, p.y) >= -15 && Math.min(a.y, p.y) <= alto + 15) {
                    const grosor = Math.min(10, Math.max(1, Math.sqrt(arbol.terminales[i]) * .55 * Math.pow(cam.escala / base, .15)));
                    let anterior = a;
                    // Bézier vertical: ramas suaves que conservan el padre exacto.
                    for (let paso = 1; paso <= 10; paso++) {
                        const t = paso / 10, s = 1 - t, y = (a.y + p.y) / 2;
                        const siguiente = {x: s*s*s*a.x + 3*s*s*t*a.x + 3*s*t*t*p.x + t*t*t*p.x,
                            y: s*s*s*a.y + 3*s*s*t*y + 3*s*t*t*y + t*t*t*p.y};
                        linea(anterior, siguiente, grosor * (1 - t * .35), coincide ? verde : tenue); anterior = siguiente;
                    }
                }
            }
            // Rechazo por caja del clado. Los brotes subpíxel se abren al acercarse.
            if ((b[2]-cam.x)*cam.escala < -ancho/2-140 || (b[0]-cam.x)*cam.escala > ancho/2+140 ||
                (b[3]-cam.y)*cam.escala < -alto/2-80 || (b[1]-cam.y)*cam.escala > alto/2+80) continue;
            const espacio = (arbol.sectores[i*2+1] - arbol.sectores[i*2]) * cam.escala;
            const brote = lista.length > 0 && espacio < 2;
            if (p.x >= -20 && p.x <= ancho+20 && p.y >= -20 && p.y <= alto+20) {
                const tamano = i === elegido ? 16 : i === 0 ? 15 : !lista.length ? 8 : brote ? 7 : 8;
                const tinta = color(coincide ? rango.color : '#c6cec8');
                posiciones.push(p.x-ancho/2, alto/2-p.y, 1); rgb.push(tinta.r,tinta.g,tinta.b); tamanos.push(tamano); terminal.push(!lista.length || brote ? 1 : 0);
                puntos.push({i, ...p, tamano});
                if (i === 0 || i === elegido || espacio >= 80 && coincide) candidatos.push({i, ...p, espacio});
            }
            if (!brote) for (let j = lista.length - 1; j >= 0; j--) pila.push(lista[j]);
        }
        liberarGeometria(ramas, {position: [lineas, 3], color: [tintas, 3]});
        liberarGeometria(hojas, {position: [posiciones, 3], color: [rgb, 3], size: [tamanos, 1], leaf: [terminal, 1]});
        dibujarEtiquetas(candidatos);
        renderer.render(escena, camara);
        const factor = cam.escala / base;
        acciones.limitesZoom?.(limitesZoom.min / base, limitesZoom.max / base);
        escalaTexto.textContent = `${numero(Math.round(factor * 10) / 10)}×`;
        acciones.zoom?.(factor); acciones.ajustado?.(Math.abs(factor - 1) < .001 && Math.abs(cam.x-encuadreDiversidad(arbol,ancho,alto).x)<.01 && Math.abs(cam.y-encuadreDiversidad(arbol,ancho,alto).y)<.01);
        marco.dataset.zoom = factor.toFixed(3); marco.dataset.etiquetas = String(etiquetasDOM.size);
        marco.dataset.enEncuadre = String(puntos.length); marco.dataset.drawCalls = String(renderer.info.render.calls);
        marco.dataset.frames = String(renderer.info.render.frame); marco.dataset.renderMs = (performance.now()-inicio).toFixed(2);
        marco.dataset.centro = `${cam.x},${cam.y}`;
    }
    function dibujarEtiquetas(candidatos) {
        candidatos.sort((a,b) => (a.i === elegido ? -1 : b.i === elegido ? 1 : arbol.niveles[a.i]-arbol.niveles[b.i]) || b.espacio-a.espacio);
        const cajas = [], usados = new Set();
        for (const p of candidatos) {
            if (usados.size >= 42) break;
            const nodo = indice.porClave.get(arbol.claves[p.i]);
            const w = Math.min(210, Math.max(95, nodo.nombre.length*7.1+20)), h = 42;
            const x = Math.max(4, Math.min(ancho-w-4, p.x-w/2)), y = p.y+11;
            if (y < 65 || y+h > alto-4 || cajas.some(b => x < b.x+b.w+6 && x+w+6 > b.x && y < b.y+b.h+5 && y+h+5 > b.y)) continue;
            cajas.push({x,y,w,h}); usados.add(p.i);
            let el = etiquetasDOM.get(p.i);
            if (!el) {
                el = boton(`${nodo.nombre}. Explorar esta rama`, '', () => acciones.seleccionar(arbol.claves[p.i]));
                el.className = 'portal-diversity-label'; el.tabIndex = -1;
                el.append(crear('strong','',nodo.nombre), crear('small','', `${nodo.etiqueta} · ${numero(nodo.total)} reg.`));
                etiquetasDOM.set(p.i,el); etiquetas.append(el);
                el.addEventListener('contextmenu', e => {e.preventDefault(); e.stopPropagation(); abrirMenu(p.i,e);});
            }
            el.style.transform = `translate(${x}px,${y}px)`; el.style.width = `${w}px`;
            el.classList.toggle('is-selected', p.i === elegido);
        }
        for (const [i,el] of etiquetasDOM) if (!usados.has(i)) {el.remove(); etiquetasDOM.delete(i);}
    }
    function coordenada(evento) {const r = canvas.getBoundingClientRect(); return {x:(evento.clientX-r.left)*ancho/r.width, y:(evento.clientY-r.top)*alto/r.height};}
    function buscar(evento) {
        const p = coordenada(evento); let mejor = -1, distancia = Infinity;
        for (const n of puntos) {const d = Math.hypot(p.x-n.x,p.y-n.y); if (d <= Math.max(13,n.tamano/2+5) && d < distancia) {distancia=d; mejor=n.i;}}
        return mejor;
    }
    function ocultarDetalle() {detalle.hidden = true; canvas.setAttribute('aria-describedby', ayuda.id);}
    function mostrarDetalle(i) {
        if (i < 0) return ocultarDetalle();
        const nodo = indice.porClave.get(arbol.claves[i]), p = proyectarDiversidad(arbol,i,cam,ancho,alto);
        detalle.replaceChildren(crear('strong','',nodo.nombre),crear('span','',`${nodo.etiqueta} · ${numero(nodo.total)} registros`));
        detalle.style.left = `${Math.max(8,Math.min(ancho-228,p.x+14))}px`;
        detalle.style.top = `${Math.max(72,Math.min(alto-80,p.y-15))}px`;
        detalle.hidden = false; canvas.setAttribute('aria-describedby', `${ayuda.id} ${detalle.id}`);
    }
    function abrirMenu(i, evento) {
        if (i < 0) return;
        const r = canvas.getBoundingClientRect(), p = proyectarDiversidad(arbol,i,cam,ancho,alto);
        const x = r.left+p.x*r.width/ancho, y = r.top+p.y*r.height/alto;
        acciones.menu(arbol.claves[i],evento,{left:x-9,right:x+9,top:y-9,bottom:y+9,limite:r});
    }
    escuchar(vista,'wheel', e => {e.preventDefault(); const p=coordenada(e); zoom(Math.exp(-Math.max(-120,Math.min(120,e.deltaY))*.007),{x:p.x-ancho/2,y:alto/2-p.y});}, {passive:false});
    function gesto() {
        const ps=[...punteros.values()]; if (!ps.length) return null;
        return {x:ps.reduce((s,p)=>s+p.x,0)/ps.length,y:ps.reduce((s,p)=>s+p.y,0)/ps.length,
            distancia:ps.length>1?Math.hypot(ps[0].x-ps[1].x,ps[0].y-ps[1].y):0};
    }
    escuchar(canvas,'pointerdown',e=>{
        if (e.button!==0) return;
        ocultarDetalle();
        canvas.focus({preventScroll:true}); canvas.setPointerCapture(e.pointerId); punteros.set(e.pointerId,coordenada(e));
        arrastre={inicio:coordenada(e),movido:punteros.size>1,revision};
    });
    escuchar(canvas,'pointermove',e=>{
        if (!punteros.has(e.pointerId)) {const i=buscar(e);canvas.style.cursor=i>=0?'pointer':'grab';mostrarDetalle(i);return;}
        const antes=gesto(); punteros.set(e.pointerId,coordenada(e)); const despues=gesto();
        if (Math.hypot(despues.x-arrastre.inicio.x,despues.y-arrastre.inicio.y)>4) arrastre.movido=true;
        cam={...cam,x:cam.x-(despues.x-antes.x)/cam.escala,y:cam.y+(despues.y-antes.y)/cam.escala};
        if (antes.distancia>0 && despues.distancia>0) zoom(despues.distancia/antes.distancia,{x:despues.x-ancho/2,y:alto/2-despues.y});
        programar();
    });
    escuchar(canvas,'pointerup',e=>{
        const pulsacion=arrastre; punteros.delete(e.pointerId);
        if (canvas.hasPointerCapture(e.pointerId)) canvas.releasePointerCapture(e.pointerId);
        if (!punteros.size) arrastre=null;
        if (pulsacion && !pulsacion.movido && pulsacion.revision===revision) {const i=buscar(e); if(i>=0) acciones.seleccionar(arbol.claves[i]);}
    });
    escuchar(canvas,'lostpointercapture',e=>{punteros.delete(e.pointerId); if(!punteros.size) arrastre=null;});
    escuchar(canvas,'pointercancel',()=>{punteros.clear();arrastre=null;});
    escuchar(canvas,'pointerleave',ocultarDetalle);
    escuchar(canvas,'contextmenu',e=>{e.preventDefault();e.stopPropagation();abrirMenu(buscar(e),e);});
    escuchar(canvas,'keydown',e=>{
        if(!arbol) return;
        if(e.key==='ContextMenu'||e.shiftKey&&e.key==='F10') {e.preventDefault();abrirMenu(elegido>=0?elegido:0,e);return;}
        if(!['+','=','-','Home','ArrowLeft','ArrowRight','ArrowUp','ArrowDown','PageDown','PageUp','Enter'].includes(e.key)) return;
        e.preventDefault();e.stopPropagation();
        if(e.key==='Home') return ajustar();
        if(['+','='].includes(e.key)) return zoom(1.5);
        if(e.key==='-') return zoom(1/1.5);
        if(e.key==='Enter') {if(elegido>=0) acciones.seleccionar(arbol.claves[elegido]);return;}
        if(['PageDown','PageUp'].includes(e.key)) {
            const lista=arbol.hijos[0];if(!lista.length)return;
            hijosTeclado=(hijosTeclado+(e.key==='PageDown'?1:-1)+lista.length)%lista.length;elegido=lista[hijosTeclado];mensaje();programar();return;
        }
        cam={...cam,x:cam.x+({'ArrowLeft':-70,'ArrowRight':70}[e.key]||0)/cam.escala,y:cam.y+({'ArrowUp':70,'ArrowDown':-70}[e.key]||0)/cam.escala};programar();
    });
    escuchar(canvas,'webglcontextlost',e=>{e.preventDefault();error('Se perdió el contexto gráfico. Cambia de visualización para recuperarlo; la jerarquía de la izquierda sigue disponible.');});
    escuchar(canvas,'webglcontextrestored',()=>{estado.hidden=true;programar();});
    escuchar(document,'visibilitychange',()=>{if(document.hidden&&frame!==null){cancelAnimationFrame(frame);frame=null;}else programar();});
    const resize=new ResizeObserver(()=>{
        if(destruido||!vista.clientWidth||!vista.clientHeight)return;
        const w=vista.clientWidth,h=vista.clientHeight;if(w===ancho&&h===alto)return;
        const anterior=cam.escala/base, ajustada=Math.abs(anterior-1)<.001;
        ancho=w;alto=h;camara.left=-w/2;camara.right=w/2;camara.top=h/2;camara.bottom=-h/2;camara.updateProjectionMatrix();renderer.setSize(w,h,false);
        if(arbol){if(ajustada)ajustar();else{base=encuadreDiversidad(arbol,w,h).escala;limitesZoom={min:base*.5,max:Math.max(base*256,70/arbol.separacion)};}}
        programar();
    }); resize.observe(vista);
    async function pantallaCompleta(){if(document.fullscreenElement===marco)return document.exitFullscreen();await marco.requestFullscreen();canvas.focus({preventScroll:true});}
    return {
        async actualizar(nuevos,clave,nuevoIndice){
            if(destruido)return;
            indice=nuevoIndice;
            if(fuente===nuevos&&raiz===clave&&arbol)return;
            revision++;punteros.clear();arrastre=null;fuente=nuevos;raiz=clave;resaltado='';hijosTeclado=-1;
            arbol=crearArbolDiversidad(indice,clave);elegido=0;
            for(const el of etiquetasDOM.values())el.remove();etiquetasDOM.clear();
            const nodo=indice.porClave.get(clave);titulo.textContent=nodo.nombre;meta.textContent=`${nodo.etiqueta} · ${numero(nodo.total)} registros`;
            volver.disabled=!indice.porClave.has(nodo.padre);
            const rangos=new Map();for(const k of arbol.claves){const r=rangoVisual3D(indice.porClave.get(k));rangos.set(r.id,r);}
            leyenda.replaceChildren();
            for(const r of [...rangos.values()].sort((a,b)=>a.orden-b.orden)){
                const b=boton(`Resaltar ${r.nombre}`,r.nombre,()=>{resaltado=resaltado===r.id?'':r.id;for(const el of leyenda.children)el.setAttribute('aria-pressed',String(el.dataset.rango===resaltado));marco.dataset.rangoResaltado=resaltado;programar();});
                b.dataset.rango=r.id;b.setAttribute('aria-pressed','false');b.style.setProperty('--rank-color',r.color);leyenda.append(b);
            }
            canvas.setAttribute('aria-label',`${nodo.nombre}: árbol de diversidad con ${numero(arbol.claves.length)} taxones. Flechas para desplazar; + y − para zoom; Inicio para ajustar; AvPág y RePág recorren los hijos; Intro explora la rama. La jerarquía accesible está a la izquierda.`);
            marco.dataset.raiz=clave;marco.dataset.nombre=nodo.nombre;marco.dataset.nodos=String(arbol.claves.length);marco.dataset.terminales=String(arbol.terminales[0]);
            marco.dataset.relaciones=String(arbol.claves.length-1);marco.dataset.rotando='false';marco.dataset.rangoResaltado='';
            estado.hidden=true;mensaje();ajustar();
        },
        ajustar,zoom,pantallaCompleta,error,
        seleccionar(clave){const i=arbol?.claves.indexOf(clave)??-1;if(i>=0){elegido=i;mensaje();programar();}},
        async localizar(clave){const i=arbol?.claves.indexOf(clave)??-1;if(i<0)return false;elegido=i;cam=encuadreDiversidad(arbol,ancho,alto,i);mensaje();programar();return true;},
        contiene:clave=>Boolean(arbol?.claves.includes(clave)),
        limpiarSeleccion(){elegido=0;mensaje();ajustar();},
        rangoActivo:()=>resaltado,
        enfocar(clave){const i=arbol?.claves.indexOf(clave)??-1;if(i<0)return false;elegido=i;canvas.focus({preventScroll:true});mensaje();programar();return true;},
        mostrar(valor){activo=valor;cabecera.hidden=ajuste.hidden=maximizar.hidden=!valor;if(valor)programar();else if(frame!==null){cancelAnimationFrame(frame);frame=null;}},
        destruir(){
            destruido=true;revision++;if(document.fullscreenElement===marco)document.exitFullscreen().catch(()=>{});
            abort.abort();resize.disconnect();if(frame!==null)cancelAnimationFrame(frame);punteros.clear();etiquetasDOM.clear();colores.clear();
            ramas.geometry.dispose();hojas.geometry.dispose();materialRamas.dispose();materialHojas.dispose();renderer.dispose();renderer.forceContextLoss();
            cabecera.remove();ajuste.remove();maximizar.remove();elemento.replaceChildren();arbol=indice=fuente=null;puntos=[];
        },
    };
}
