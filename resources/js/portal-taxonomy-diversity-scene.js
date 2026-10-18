import {Box3, BufferAttribute, BufferGeometry, Color, DirectionalLight, Frustum, HemisphereLight, InstancedMesh, LineBasicMaterial, LineSegments,
    Matrix4, MeshStandardMaterial, MOUSE, Object3D, PerspectiveCamera, Scene, SphereGeometry, TOUCH, Vector3, WebGLRenderer} from 'three';
import {OrbitControls} from 'three/addons/controls/OrbitControls.js';
import {aperturaRamaDiversidad,crearArbolDiversidad,detalleTaxonomicoDiversidad,encuadreRaizDiversidad,radioYemaDiversidad,seleccionarDetalleDiversidad,siguienteYemaDiversidad} from './portal-taxonomy-diversity-model';
import {rangoVisual3D,resumenTaxon3D} from './portal-taxonomy-three-ranks';
import {textoLinajeTaxonomico} from './portal-taxonomy-lineage';
import {crearLeyendaTaxonomica} from './portal-taxonomy-tooltip';

const numero=n=>Number(n).toLocaleString('es-EC');
const crear=(tag,clase,texto)=>{const el=document.createElement(tag);el.className=clase;if(texto)el.textContent=texto;return el;};
const boton=(nombre,texto,accion)=>{const el=crear('button','',texto);el.type='button';el.setAttribute('aria-label',nombre);el.title=nombre;el.addEventListener('click',accion);return el;};

/** Yemas con volumen, tallos finos y apertura progresiva de clados reales. */
export function crearEscenaDiversidad(elemento,acciones) {
    const marco=crear('div','portal-diversity-scene');marco.dataset.motor='Three.js';
    const cabecera=crear('div','portal-three-context'),identidad=crear('div','portal-three-identity');
    const titulo=crear('h3','portal-three-title'),meta=crear('p','portal-three-meta');identidad.append(titulo,meta);
    const controles=crear('div','portal-three-controls');
    const volver=boton('Volver al padre de la rama','↶',()=>{const padre=indice?.porClave.get(raiz)?.padre;if(padre)acciones.navegar(padre);});
    controles.append(volver);cabecera.append(identidad,controles);
    const maximizar=boton('Ver visualizador en pantalla completa','⛶',()=>pantallaCompleta().catch(()=>error('No se pudo activar la pantalla completa.')));maximizar.className='portal-three-maximize';
    const barra=elemento.closest('.taxonomy-navigator').querySelector('.taxonomy-navigator-zoom');barra.prepend(cabecera);barra.append(maximizar);
    const vista=crear('div','portal-diversity-viewport'),etiquetas=crear('div','portal-diversity-labels');
    const ayuda=crear('p','sr-only','Rueda o pellizco: zoom progresivo. Arrastrar: desplazar. Ctrl y arrastrar o Ctrl y flechas: girar en 3D. Inicio: vista inicial. AvPág y RePág: explorar hijos. Intro: abrir rama.');ayuda.id='portal-diversity-help';
    const escalaTexto=crear('output','portal-diversity-scale');escalaTexto.setAttribute('aria-label','Nivel de zoom');
    const leyenda=crear('div','portal-diversity-legend');leyenda.setAttribute('role','group');leyenda.setAttribute('aria-label','Resaltar un rango conservando todas las relaciones publicadas');
    const instrucciones=crear('p','portal-three-instructions');instrucciones.tabIndex=0;instrucciones.setAttribute('role','status');
    const estado=crear('p','portal-diversity-status');estado.setAttribute('role','alert');estado.hidden=true;
    marco.append(vista,leyenda,instrucciones);elemento.replaceChildren(marco);
    const controlesAmpliados=crear('div','portal-diversity-fullscreen-controls');controlesAmpliados.setAttribute('role','group');controlesAmpliados.setAttribute('aria-label','Zoom y pantalla completa');
    const alejar=boton('Alejar árbol en pantalla completa','−',()=>zoom(1/1.6)),acercar=boton('Acercar árbol en pantalla completa','+',()=>zoom(1.6));
    const salir=boton('Salir de pantalla completa','⛶',()=>salirDeAmpliacion());salir.className='portal-diversity-fullscreen-exit';controlesAmpliados.append(alejar,acercar,salir);marco.append(controlesAmpliados);
    let renderer;
    try{renderer=new WebGLRenderer({antialias:true,alpha:true,powerPreference:'low-power',logarithmicDepthBuffer:true});}
    catch(fallo){cabecera.remove();maximizar.remove();elemento.replaceChildren();throw fallo;}
    renderer.setPixelRatio(Math.min(globalThis.devicePixelRatio||1,1.5));
    const canvas=renderer.domElement;canvas.className='portal-diversity-canvas';canvas.tabIndex=0;canvas.setAttribute('role','img');
    canvas.setAttribute('aria-keyshortcuts','ArrowLeft ArrowRight ArrowUp ArrowDown Control+ArrowLeft Control+ArrowRight Control+ArrowUp Control+ArrowDown PageDown PageUp Enter Home + -');canvas.setAttribute('aria-describedby',ayuda.id);
    vista.append(canvas,etiquetas,ayuda,escalaTexto,estado);
    const tooltip=crearLeyendaTaxonomica(vista,canvas);
    const escena=new Scene(),camara=new PerspectiveCamera(38,1,.01,10000),camaraGPU=new PerspectiveCamera(),origenGPU=new Vector3();
    escena.add(new HemisphereLight('#fffdf0','#355044',2.5));
    const luz=new DirectionalLight('#fff7df',3);luz.position.set(-400,600,800);escena.add(luz);
    const lateral=new DirectionalLight('#c4e5d0',1.4);lateral.position.set(500,180,-300);escena.add(lateral);
    const materialRamas=new LineBasicMaterial({vertexColors:true,transparent:true,opacity:.85,depthWrite:false});
    const materialHojas=new MeshStandardMaterial({roughness:.56,metalness:.02});
    const geometriaHoja=new SphereGeometry(1,12,8),ramas=new LineSegments(new BufferGeometry(),materialRamas);ramas.frustumCulled=false;escena.add(ramas);
    let hojas;
    const orbit=new OrbitControls(camara,canvas);orbit.enableDamping=false;orbit.enableZoom=false;
    orbit.mouseButtons.LEFT=MOUSE.PAN;orbit.touches.ONE=TOUCH.PAN;orbit.touches.TWO=TOUCH.DOLLY_PAN;orbit.rotateSpeed=.7;orbit.panSpeed=.8;
    const frustum=new Frustum(),matriz=new Matrix4(),caja=new Box3(),punto=new Vector3(),objeto=new Object3D();
    const colores=new Map(),etiquetasDOM=new Map(),troncosCache=new Map(),abort=new AbortController();
    const reducido=matchMedia('(prefers-reduced-motion: reduce)');
    let indice,arbol,fuente,raiz,ancho=1,alto=1,base=1,maxZoom=4096,radioArbol=1,rangosPresentes=[],rangosNodos=[];
    let frame=null,destruido=false,activo=true,elegido=0,resaltado='',hijoTeclado=-1,puntos=[],pulsacion=null,destino=null,firma='',revision=0,limiteRango=3;
    let dimensionado=false,ampliado=false,ampliacionAutomatica=true,fullscreenPendiente=false;
    let focoZoom=0,rangoInicial=0,aperturaPorClick=false;
    const dialogo=elemento.closest('dialog'),toques=new Map();
    const escuchar=(el,tipo,fn,opciones={})=>el.addEventListener(tipo,fn,{...opciones,signal:abort.signal});
    const color=valor=>{if(!colores.has(valor))colores.set(valor,new Color(valor));return colores.get(valor);};
    const posicion=(i,v=new Vector3())=>v.set(arbol.posiciones[i*2],arbol.posiciones[i*2+1],arbol.profundidad[i]);
    function programar(){if(frame!==null||destruido||!activo||document.hidden)return;frame=requestAnimationFrame(()=>{frame=null;if(!destruido&&activo&&elemento.closest('dialog')?.open&&arbol)dibujar();});}
    function mensaje(i=elegido){instrucciones.textContent=`${textoLinajeTaxonomico(indice,arbol?.claves[i>=0?i:0])} · Jerarquía taxonómica, sin escala temporal`;instrucciones.title=instrucciones.textContent;}
    function error(texto){estado.textContent=texto;estado.hidden=false;}
    function cajaClado(i){const b=arbol.limites3D.subarray(i*6,i*6+6);return caja.min.set(b[0],b[1],b[2]),caja.max.set(b[3],b[4],b[5]),caja;}
    function encuadrar(i=0,animado=false,conservarOrientacion=false){
        if(!arbol)return;
        focoZoom=i;
        const bounds=cajaClado(i).clone();
        if(!i){
            bounds.makeEmpty();const inicial=detalleTaxonomicoDiversidad(rangosNodos[0],1,rangosPresentes,rangoInicial).orden;
            let expuestos=0;
            for(let j=0;j<arbol.claves.length;j++)if(j===0||rangosNodos[j]<=inicial){bounds.expandByPoint(posicion(j));expuestos++;}
            // Si la selección es una Clase/Familia individual, reservar el
            // espacio de su corona: el siguiente zoom debe descubrir hijos,
            // en vez de encuadrar sólo el marcador y dejarlos fuera de pantalla.
            if(expuestos===1&&arbol.hijos[0].length)bounds.copy(cajaClado(0));
        }
        bounds.expandByScalar(Math.max(2,arbol.radios[i]*2.8));const centro=bounds.getCenter(new Vector3()),tamano=bounds.getSize(new Vector3());
        let distancia=Math.max(tamano.y,tamano.x/Math.max(.2,camara.aspect),tamano.z*.8,40)/(2*Math.tan(camara.fov*Math.PI/360)) * 1.28;
        const direccion=animado||conservarOrientacion?camara.position.clone().sub(orbit.target).normalize():new Vector3(.22,.16,1).normalize();
        const objetivo={posicion:centro.clone().addScaledVector(direccion,distancia),centro};
        if(!i&&aperturaPorClick){
            const inicial=detalleTaxonomicoDiversidad(rangosNodos[0],1,rangosPresentes,rangoInicial).orden;
            const pie=ampliado?leyenda.offsetHeight+instrucciones.offsetHeight:0;
            const encuadre=encuadreRaizDiversidad(arbol,inicial,ancho,alto,{inferior:70+pie,direccion:direccion.toArray()});
            objetivo.centro.fromArray(encuadre.centro);objetivo.posicion.fromArray(encuadre.posicion);distancia=encuadre.distancia;
        }
        if(!i){base=distancia;radioArbol=Math.max(50,tamano.length());maxZoom=Math.max(4096,Math.min(1e7,80/arbol.separacion));orbit.minDistance=base/maxZoom;orbit.maxDistance=base*2;}
        if(animado&&!reducido.matches)destino=objetivo;else{destino=null;camara.position.copy(objetivo.posicion);orbit.target.copy(centro);orbit.update();}
        marco.dataset.animando=String(Boolean(destino));tooltip.ocultar();programar();
    }
    function ajustar(){encuadrar();}
    function zoom(factor){
        if(!arbol||!Number.isFinite(factor)||factor<=0)return;
        if(factor>1&&ampliacionAutomatica&&!ampliado)ampliarAutomaticamente();
        const centro=destino?.centro||orbit.target;
        const origen=destino?.posicion||camara.position,distancia=origen.distanceTo(centro);
        const nueva=Math.max(orbit.minDistance,Math.min(orbit.maxDistance,distancia/factor));
        const siguiente=detalleTaxonomicoDiversidad(rangosNodos[0],base/nueva,rangosPresentes,rangoInicial).orden;
        focoZoom=siguienteYemaDiversidad(arbol,focoZoom,siguiente);
        const objetivo=posicion(focoZoom);
        destino={centro:objetivo,posicion:origen.clone().sub(centro).normalize().multiplyScalar(nueva).add(objetivo)};
        Object.assign(marco.dataset,{yemaZoom:arbol.claves[focoZoom],registrosYema:String(arbol.registros[focoZoom])});
        if(reducido.matches){camara.position.copy(destino.posicion);orbit.target.copy(destino.centro);destino=null;orbit.update();}
        marco.dataset.animando=String(Boolean(destino));tooltip.ocultar();programar();
    }
    function proyectar(i){const p=posicion(i,punto).project(camara);return{x:(p.x+1)*ancho/2,y:(1-p.y)*alto/2,z:p.z};}
    function extension(i){
        const b=arbol.limites3D.subarray(i*6,i*6+6);let x0=Infinity,y0=Infinity,x1=-Infinity,y1=-Infinity;
        for(let j=0;j<8;j++){punto.set(b[(j&1)?3:0],b[(j&2)?4:1],b[(j&4)?5:2]).project(camara);if(punto.z>1)return Infinity;x0=Math.min(x0,punto.x);x1=Math.max(x1,punto.x);y0=Math.min(y0,punto.y);y1=Math.max(y1,punto.y);}
        return Math.max((x1-x0)*ancho/2,(y1-y0)*alto/2);
    }
    function crearTronco(i,vertices,tintas,indices){
        const p=arbol.padres[i];if(p<0)return;
        const guardado=troncosCache.get(i);
        if(guardado)return unirTronco(guardado,i,vertices,tintas,indices);
        const a=posicion(p),b=posicion(i),c=arbol.curvas.subarray(i*6,i*6+6),c1=new Vector3(c[0],c[1],c[2]),c2=new Vector3(c[3],c[4],c[5]);
        const curva=[],segmentos=[],pasos=8;
        for(let j=0;j<=pasos;j++){
            const t=j/pasos,s=1-t,centro=a.clone().multiplyScalar(s*s*s).addScaledVector(c1,3*s*s*t).addScaledVector(c2,3*s*t*t).addScaledVector(b,t*t*t);
            curva.push(...centro.toArray());if(j<pasos)segmentos.push(j,j+1);
        }
        // Primitivas 3D de un píxel: el zoom no convierte los tallos en tubos
        // que tapen las yemas. Se conservan la curva, profundidad y oclusión.
        const plantilla={posiciones:Float64Array.from(curva),indices:segmentos};
        if(troncosCache.size>=256)troncosCache.delete(troncosCache.keys().next().value);
        troncosCache.set(i,plantilla);unirTronco(plantilla,i,vertices,tintas,indices);
    }
    function unirTronco(plantilla,i,vertices,tintas,indices){
        const offset=vertices.length/3,rango=rangoVisual3D(indice.porClave.get(arbol.claves[i]));
        const tinta=color(resaltado&&rango.id!==resaltado?'#b4bfb2':'#78947c');
        for(let j=0;j<plantilla.posiciones.length;j+=3){vertices.push(plantilla.posiciones[j]-origenGPU.x,plantilla.posiciones[j+1]-origenGPU.y,plantilla.posiciones[j+2]-origenGPU.z);tintas.push(tinta.r,tinta.g,tinta.b);}
        for(const j of plantilla.indices)indices.push(offset+j);
    }
    function dibujar(){
        const inicio=performance.now();
        if(destino){
            const paso=reducido.matches?1:.25;
            camara.position.lerp(destino.posicion,paso);orbit.target.lerp(destino.centro,paso);
            if(camara.position.distanceTo(destino.posicion)<Math.max(.0000001,camara.position.distanceTo(orbit.target)*.0004)){camara.position.copy(destino.posicion);orbit.target.copy(destino.centro);destino=null;}
            orbit.update();
        }
        const distancia=camara.position.distanceTo(orbit.target);camara.near=Math.max(1e-9,distancia*.00003);camara.far=distancia+radioArbol*5;camara.updateProjectionMatrix();camara.updateMatrixWorld();
        const detalle=detalleTaxonomicoDiversidad(rangosNodos[0],base/distancia,rangosPresentes,rangoInicial);limiteRango=detalle.orden;
        for(const b of leyenda.children)b.hidden=Number(b.dataset.orden)>limiteRango;
        // Origen flotante: las coordenadas completas siguen en doble precisión;
        // los buffers GPU contienen sólo diferencias locales durante el zoom.
        if(origenGPU.distanceTo(orbit.target)>distancia*24){origenGPU.copy(orbit.target);firma='';}
        frustum.setFromProjectionMatrix(matriz.multiplyMatrices(camara.projectionMatrix,camara.matrixWorldInverse));
        const medidas=new Map(),presupuesto=ancho<600?80:160;
        const {visibles,brotes:colapsados}=seleccionarDetalleDiversidad(arbol,rangosNodos,limiteRango,i=>{
            if(!frustum.intersectsBox(cajaClado(i).expandByScalar(arbol.radios[i]*3)))return null;
            const p=proyectar(i),m={...p,extension:extension(i)};medidas.set(i,m);return m;
        },{presupuesto,ancho,alto});
        // El taxón elegido por abundancia tiene prioridad sobre la agrupación
        // de densidad, sin aumentar el presupuesto ni abrir rangos posteriores.
        if(focoZoom>=0&&rangosNodos[focoZoom]<=limiteRango&&!visibles.includes(focoZoom)){
            const p=proyectar(focoZoom);
            if(p.z>=-1&&p.z<=1&&p.x>=0&&p.x<=ancho&&p.y>=0&&p.y<=alto){
                if(visibles.length>=presupuesto)visibles.pop();visibles.push(focoZoom);medidas.set(focoZoom,{...p,extension:extension(focoZoom)});
            }
        }
        visibles.sort((a,b)=>a-b);puntos=[];
        const nuevaFirma=`${limiteRango}|${resaltado}|${visibles.join(',')}`;
        if(nuevaFirma!==firma){
            firma=nuevaFirma;const vertices=[],tintas=[],indices=[];for(const i of visibles)crearTronco(i,vertices,tintas,indices);
            const geometria=new BufferGeometry();geometria.setAttribute('position',new BufferAttribute(Float32Array.from(vertices),3));geometria.setAttribute('color',new BufferAttribute(Float32Array.from(tintas),3));geometria.setIndex(indices);
            ramas.geometry.dispose();ramas.geometry=geometria;
        }
        const focal=alto/(2*Math.tan(camara.fov*Math.PI/360)),candidatos=[];let cantidad=0;
        for(const i of visibles){
            const p=medidas.get(i);if(p.z< -1||p.z>1||p.x< -30||p.x>ancho+30||p.y< -30||p.y>alto+30)continue;
            const nodo=indice.porClave.get(arbol.claves[i]),rango=rangoVisual3D(nodo),hoja=!arbol.hijos[i].length,brote=colapsados.has(i);
            if(i!==0&&rango.orden>limiteRango)continue;
            const prof=-posicion(i,punto).applyMatrix4(camara.matrixWorldInverse).z;
            const radio=prof/focal*Math.min(28,radioYemaDiversidad(arbol.especies[i]));
            posicion(i,objeto.position).sub(origenGPU);objeto.quaternion.identity();objeto.scale.setScalar(radio);objeto.updateMatrix();
            hojas.setMatrixAt(cantidad,objeto.matrix);hojas.setColorAt(cantidad,color(resaltado&&rango.id!==resaltado&&i!==elegido?'#acb8a8':rango.color));cantidad++;
            const tamano=radio*focal/prof;puntos.push({i,...p,tamano});
            if(i===0||i===elegido||i===focoZoom||hoja||brote||rango.orden===limiteRango||tamano>15||p.extension>150)candidatos.push({i,...p,espacio:tamano*2,hoja,brote});
        }
        hojas.count=cantidad;hojas.instanceMatrix.needsUpdate=true;if(hojas.instanceColor)hojas.instanceColor.needsUpdate=true;
        dibujarEtiquetas(candidatos);camaraGPU.copy(camara);camaraGPU.position.sub(origenGPU);camaraGPU.updateMatrixWorld();renderer.render(escena,camaraGPU);
        const factor=base/distancia;escalaTexto.textContent=`${numero(Math.round(factor*10)/10)}×`;acciones.zoom?.(factor);acciones.limitesZoom?.(.5,maxZoom);acciones.ajustado?.(Math.abs(factor-1)<.001&&!destino);
        alejar.disabled=factor<=.50001;acercar.disabled=factor>=maxZoom*.99999;
        Object.assign(marco.dataset,{zoom:factor.toFixed(3),ramasDibujadas:String(Math.max(0,visibles.length-1)),presupuesto:String(presupuesto),cacheTroncos:String(troncosCache.size),etiquetas:String(etiquetasDOM.size),enEncuadre:String(puntos.length),contraidos:String(colapsados.size),nivelDetalle:String(puntos.reduce((nivel,p)=>Math.max(nivel,arbol.niveles[p.i]),0)),rangoDetalle:detalle.nombre,drawCalls:String(renderer.info.render.calls),frames:String(renderer.info.render.frame),renderMs:(performance.now()-inicio).toFixed(2),centro:orbit.target.toArray().join(','),orientacion:camara.quaternion.toArray().map(n=>n.toFixed(5)).join(','),animando:String(Boolean(destino))});
        if(destino)programar();
    }
    function dibujarEtiquetas(candidatos){
        candidatos.sort((a,b)=>(a.i===focoZoom?-1:b.i===focoZoom?1:a.i===elegido?-1:b.i===elegido?1:arbol.niveles[a.i]-arbol.niveles[b.i])||b.espacio-a.espacio);
        const cajas=[],usados=new Set();
        for(const p of candidatos){
            if(usados.size>=42)break;const nodo=indice.porClave.get(arbol.claves[p.i]),w=Math.min(230,Math.max(120,nodo.nombre.length*7.5+24)),h=42;
            const opciones=[[p.x-w/2,p.y+(p.espacio>70?-h/2:12)],[p.x-w/2,p.y-h-12],[p.x+14,p.y-h/2],[p.x-w-14,p.y-h/2],[p.x-w/2,p.y-h*2-14]];
            const sitio=opciones.map(([x,y])=>({x:Math.max(4,Math.min(ancho-w-4,x)),y})).find(({x,y})=>y>=4&&y+h<=alto-4&&!cajas.some(b=>x<b.x+b.w+6&&x+w+6>b.x&&y<b.y+b.h+5&&y+h+5>b.y));
            if(!sitio)continue;const{x,y}=sitio;
            cajas.push({x,y,w,h});usados.add(p.i);let el=etiquetasDOM.get(p.i);
            if(!el){el=boton(`${nodo.nombre}. Explorar esta rama`,'',()=>elegirNodo(p.i));el.className='portal-diversity-label';el.tabIndex=-1;el.append(crear('strong','',nodo.nombre),crear('small','',resumenTaxon3D(nodo)));etiquetasDOM.set(p.i,el);etiquetas.append(el);el.addEventListener('contextmenu',e=>{e.preventDefault();e.stopPropagation();abrirMenu(p.i,e);});}
            el.style.transform=`translate(${x}px,${y}px)`;el.style.width=`${w}px`;el.dataset.puntoX=String(p.x);el.dataset.puntoY=String(p.y);el.dataset.especies=String(arbol.especies[p.i]);el.dataset.radioYema=String(Math.min(28,radioYemaDiversidad(arbol.especies[p.i])));el.dataset.clave=arbol.claves[p.i];el.classList.toggle('is-selected',p.i===elegido);el.classList.toggle('is-leaf',p.hoja);
            el.querySelector('small').textContent=resumenTaxon3D(nodo);
        }
        for(const [i,el]of etiquetasDOM)if(!usados.has(i)){el.remove();etiquetasDOM.delete(i);}
    }
    function coordenada(e){const r=canvas.getBoundingClientRect();return{x:(e.clientX-r.left)*ancho/r.width,y:(e.clientY-r.top)*alto/r.height};}
    function elegirNodo(i){acciones.seleccionar(arbol.claves[i]);}
    function buscar(e){const p=coordenada(e);let mejor=-1,distancia=Infinity,prof=Infinity;for(const n of puntos){const d=Math.hypot(p.x-n.x,p.y-n.y);if(d<=Math.max(9,n.tamano*1.15)&&(d<distancia-2||Math.abs(d-distancia)<=2&&n.z<prof)){mejor=n.i;distancia=d;prof=n.z;}}return mejor;}
    function mostrarDetalle(i){if(i<0)return tooltip.ocultar();tooltip.mostrar(indice.porClave.get(arbol.claves[i]),proyectar(i));}
    function abrirMenu(i,e){if(i<0)return;const p=proyectar(i),r=canvas.getBoundingClientRect(),x=r.left+p.x*r.width/ancho,y=r.top+p.y*r.height/alto;acciones.menu(arbol.claves[i],e,{left:x-9,right:x+9,top:y-9,bottom:y+9,limite:r});}
    orbit.addEventListener('change',programar);orbit.addEventListener('start',()=>{destino=null;focoZoom=-1;marco.dataset.animando='false';tooltip.ocultar();marco.dataset.rotando='true';});orbit.addEventListener('end',()=>{marco.dataset.rotando='false';});
    escuchar(vista,'wheel',e=>{e.preventDefault();e.stopPropagation();zoom(Math.exp(-Math.max(-120,Math.min(120,e.deltaY))*Math.log(1.6)/120),coordenada(e));},{passive:false,capture:true});
    // El pellizco comparte exactamente el zoom semántico y el autocentrado de
    // la rueda. OrbitControls sólo recibe el ratón; no aplica otro dolly paralelo.
    const gesto=()=>{const a=[...toques.values()];return a.length===2?{distancia:Math.hypot(a[0].x-a[1].x,a[0].y-a[1].y),clientX:(a[0].x+a[1].x)/2,clientY:(a[0].y+a[1].y)/2}:null;};
    escuchar(canvas,'pointerdown',e=>{if(e.pointerType!=='touch')return;e.stopImmediatePropagation();canvas.setPointerCapture(e.pointerId);tooltip.ocultar();destino=null;const t={x:e.clientX,y:e.clientY,inicioX:e.clientX,inicioY:e.clientY,movido:false};toques.set(e.pointerId,t);if(toques.size>1)for(const t of toques.values())t.movido=true;},{capture:true});
    escuchar(canvas,'pointermove',e=>{const t=toques.get(e.pointerId);if(!t)return;e.preventDefault();e.stopImmediatePropagation();const antes=gesto(),dx=e.clientX-t.x,dy=e.clientY-t.y;t.x=e.clientX;t.y=e.clientY;t.movido ||=Math.hypot(t.x-t.inicioX,t.y-t.inicioY)>5;const despues=gesto();if(antes&&despues&&antes.distancia>0)zoom(despues.distancia/antes.distancia,coordenada(despues));else if(toques.size===1&&t.movido){destino=null;focoZoom=-1;orbit.pan(-dx,-dy);orbit.update();programar();}},{capture:true,passive:false});
    const terminarToque=e=>{const t=toques.get(e.pointerId);if(!t)return;e.stopImmediatePropagation();toques.delete(e.pointerId);if(e.type==='pointerup'&&!t.movido){const i=buscar(e);if(i>=0)elegirNodo(i);}if(canvas.hasPointerCapture(e.pointerId))canvas.releasePointerCapture(e.pointerId);};
    escuchar(canvas,'pointerup',terminarToque,{capture:true});escuchar(canvas,'pointercancel',terminarToque,{capture:true});
    // OrbitControls mantiene Ctrl+arrastre como giro cuando LEFT es PAN.
    escuchar(canvas,'pointerdown',e=>{tooltip.ocultar();canvas.focus({preventScroll:true});pulsacion={x:e.clientX,y:e.clientY,id:e.pointerId,boton:e.button,ctrl:e.ctrlKey,revision};});
    escuchar(canvas,'pointerup',e=>{const p=pulsacion;pulsacion=null;if(p&&p.boton===0&&!p.ctrl&&p.id===e.pointerId&&p.revision===revision&&Math.hypot(e.clientX-p.x,e.clientY-p.y)<5){const i=buscar(e);if(i>=0)elegirNodo(i);}});
    escuchar(canvas,'pointermove',e=>{if(!pulsacion){const i=buscar(e);canvas.style.cursor=i>=0?'pointer':'grab';mostrarDetalle(i);}else tooltip.ocultar();});
    escuchar(canvas,'pointercancel',()=>{pulsacion=null;tooltip.ocultar();});escuchar(canvas,'pointerleave',()=>tooltip.ocultar());
    escuchar(canvas,'contextmenu',e=>{e.preventDefault();e.stopPropagation();abrirMenu(buscar(e),e);});
    escuchar(canvas,'keydown',e=>{
        if(!arbol)return;if(e.key==='ContextMenu'||e.shiftKey&&e.key==='F10'){e.preventDefault();abrirMenu(elegido,e);return;}
        if(!['+','=','-','Home','ArrowLeft','ArrowRight','ArrowUp','ArrowDown','PageDown','PageUp','Enter'].includes(e.key))return;
        e.preventDefault();e.stopPropagation();tooltip.ocultar();
        if(e.key==='Home')return ajustar();if(['+','='].includes(e.key))return zoom(1.6);if(e.key==='-')return zoom(1/1.6);
        if(e.key==='Enter'){elegirNodo(elegido);return;}
        if(['PageDown','PageUp'].includes(e.key)){const lista=arbol.hijos[0];if(!lista.length)return;hijoTeclado=(hijoTeclado+(e.key==='PageDown'?1:-1)+lista.length)%lista.length;elegido=lista[hijoTeclado];mensaje();encuadrar(elegido,true);mostrarDetalle(elegido);return;}
        if(e.ctrlKey){if(e.key==='ArrowLeft'||e.key==='ArrowRight')orbit.rotateLeft(e.key==='ArrowLeft'? .13:-.13);else orbit.rotateUp(e.key==='ArrowUp'? .13:-.13);}else{focoZoom=-1;orbit.pan(({'ArrowLeft':70,'ArrowRight':-70}[e.key]||0),({'ArrowUp':70,'ArrowDown':-70}[e.key]||0));}orbit.update();programar();
    });
    escuchar(canvas,'webglcontextlost',e=>{e.preventDefault();error('Se perdió el contexto gráfico. Cambia de visualización para recuperarlo; la jerarquía de la izquierda sigue disponible.');});
    escuchar(canvas,'webglcontextrestored',()=>{estado.hidden=true;firma='';programar();});
    escuchar(document,'visibilitychange',()=>{if(document.hidden){if(frame!==null)cancelAnimationFrame(frame);frame=null;destino=null;marco.dataset.animando='false';tooltip.ocultar();}else programar();});
    const resize=new ResizeObserver(()=>{if(destruido||!vista.clientWidth||!vista.clientHeight)return;const w=vista.clientWidth,h=vista.clientHeight;if(w===ancho&&h===alto)return;const apertura=arbol&&aperturaPorClick&&!destino&&Math.abs(base/camara.position.distanceTo(orbit.target)-1)<.001;ancho=w;alto=h;camara.aspect=w/h;camara.updateProjectionMatrix();renderer.setSize(w,h,false);if(arbol&&!dimensionado)ajustar();else if(apertura)encuadrar(0,false,true);dimensionado=true;programar();});resize.observe(vista);
    function expandir(){ampliado=true;marco.classList.add('is-expanded');dialogo?.classList.add('has-diversity-expanded');marco.dataset.ampliado='true';canvas.focus({preventScroll:true});}
    function restaurar(){ampliado=false;marco.classList.remove('is-expanded');dialogo?.classList.remove('has-diversity-expanded');marco.dataset.ampliado='false';ampliacionAutomatica=false;canvas.focus({preventScroll:true});}
    function ampliarAutomaticamente(){expandir();if(document.fullscreenEnabled&&navigator.userActivation?.isActive&&!fullscreenPendiente){fullscreenPendiente=true;marco.requestFullscreen().catch(()=>{}).finally(()=>{fullscreenPendiente=false;});}}
    async function salirDeAmpliacion(){restaurar();if(document.fullscreenElement===marco)await document.exitFullscreen();}
    async function pantallaCompleta(){if(ampliado)return salirDeAmpliacion();expandir();if(document.fullscreenEnabled&&navigator.userActivation?.isActive)await marco.requestFullscreen().catch(()=>{});}
    escuchar(document,'fullscreenchange',()=>{if(!document.fullscreenElement&&ampliado)restaurar();});
    escuchar(document,'keydown',e=>{if(!ampliado)return;if(e.key==='Escape'){if(marco.querySelector('[role="menu"]')?.checkVisibility())return;e.preventDefault();e.stopImmediatePropagation();salirDeAmpliacion();}else if(e.key==='Tab'){const controles=[...marco.querySelectorAll('button:not([tabindex="-1"]),canvas')].filter(el=>el.checkVisibility()&&!el.disabled);if(!controles.length)return;e.preventDefault();const i=controles.indexOf(document.activeElement);controles[(i+(e.shiftKey?-1:1)+controles.length)%controles.length].focus({preventScroll:true});}},{capture:true});
    return {
        async actualizar(nuevos,clave,nuevoIndice){
            if(destruido)return;if(fuente===nuevos&&raiz===clave&&indice===nuevoIndice)return;
            revision++;tooltip.ocultar();troncosCache.clear();etiquetasDOM.clear();etiquetas.replaceChildren();fuente=nuevos;indice=nuevoIndice;raiz=clave;arbol=crearArbolDiversidad(indice,clave);rangosNodos=Array.from(arbol.rangos);elegido=0;focoZoom=0;rangoInicial=rangosNodos[0];aperturaPorClick=false;hijoTeclado=-1;resaltado='';firma='';
            if(hojas){escena.remove(hojas);hojas.dispose();}hojas=new InstancedMesh(geometriaHoja,materialHojas,arbol.claves.length);hojas.frustumCulled=false;escena.add(hojas);
            const nodo=indice.porClave.get(clave);titulo.textContent=nodo.nombre;meta.textContent=resumenTaxon3D(nodo);volver.disabled=!indice.porClave.has(nodo.padre);
            const rangos=new Map();for(const k of arbol.claves){const r=rangoVisual3D(indice.porClave.get(k));rangos.set(r.id,r);}leyenda.replaceChildren();
            rangosPresentes=[...rangos.values()].map(r=>r.orden);
            for(const r of [...rangos.values()].sort((a,b)=>a.orden-b.orden)){const b=boton(`Resaltar ${r.nombre}`,r.nombre,()=>{resaltado=resaltado===r.id?'':r.id;for(const el of leyenda.children)el.setAttribute('aria-pressed',String(el.dataset.rango===resaltado));marco.dataset.rangoResaltado=resaltado;programar();});b.dataset.rango=r.id;b.dataset.orden=String(r.orden);b.setAttribute('aria-pressed','false');b.style.setProperty('--rank-color',r.color);leyenda.append(b);}
            canvas.setAttribute('aria-label',`${nodo.nombre}: árbol de diversidad 3D con ${numero(arbol.claves.length)} taxones. Rueda: zoom progresivo que revela ramas; Ctrl y arrastrar o Ctrl y flechas: girar; arrastrar: desplazar; Inicio: vista inicial; AvPág y RePág: hijos; Intro: explorar rama.`);
            Object.assign(marco.dataset,{raiz:clave,nombre:nodo.nombre,nodos:String(arbol.claves.length),terminales:String(arbol.terminales[0]),relaciones:String(arbol.claves.length-1),rotando:'false',rangoResaltado:'',camara:'perspectiva'});estado.hidden=true;mensaje();ajustar();
        },
        ajustar,zoom,pantallaCompleta,error,
        seleccionar(clave){const i=arbol?.claves.indexOf(clave)??-1;if(i>=0){elegido=i;if(i===0){rangoInicial=aperturaRamaDiversidad(arbol);aperturaPorClick=true;encuadrar();}mensaje();programar();}},
        async localizar(clave){const i=arbol?.claves.indexOf(clave)??-1;if(i<0)return false;elegido=i;encuadrar(i,true);mensaje();return true;},
        contiene:clave=>Boolean(arbol?.claves.includes(clave)),limpiarSeleccion(){elegido=0;mensaje();ajustar();},rangoActivo:()=>resaltado,
        enfocar(clave){const i=arbol?.claves.indexOf(clave)??-1;if(i<0)return false;elegido=i;canvas.focus({preventScroll:true});mensaje();mostrarDetalle(i);programar();return true;},
        mostrar(valor){activo=valor;cabecera.hidden=maximizar.hidden=!valor;orbit.enabled=valor;if(valor)programar();else{if(frame!==null)cancelAnimationFrame(frame);frame=null;destino=null;marco.dataset.animando='false';tooltip.ocultar();}},
        destruir(){destruido=true;revision++;if(document.fullscreenElement===marco)document.exitFullscreen().catch(()=>{});dialogo?.classList.remove('has-diversity-expanded');abort.abort();toques.clear();resize.disconnect();orbit.dispose();tooltip.destruir();if(frame!==null)cancelAnimationFrame(frame);etiquetasDOM.clear();colores.clear();troncosCache.clear();ramas.geometry.dispose();hojas?.dispose();geometriaHoja.dispose();materialRamas.dispose();materialHojas.dispose();renderer.dispose();renderer.forceContextLoss();cabecera.remove();maximizar.remove();elemento.replaceChildren();arbol=indice=fuente=null;puntos=[];},
    };
}
