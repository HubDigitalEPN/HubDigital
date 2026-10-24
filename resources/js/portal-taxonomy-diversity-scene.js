import {Box3, BufferAttribute, BufferGeometry, Color, DirectionalLight, Fog, Frustum, HemisphereLight, InstancedMesh, LineBasicMaterial, LineSegments,
    Matrix4, MeshStandardMaterial, MOUSE, Object3D, PerspectiveCamera, Scene, SphereGeometry, TOUCH, Vector3, WebGLRenderer} from 'three';
import {OrbitControls} from 'three/addons/controls/OrbitControls.js';
import {LEYENDA_DIVERSIDAD,aperturaRamaDiversidad,crearArbolDiversidad,detalleTaxonomicoDiversidad,encuadreRaizDiversidad,limiteZoomRangosDiversidad,radioYemaDiversidad,seleccionarDetalleDiversidad,zoomParaRangoDiversidad,zoomRaizDiversidad} from './portal-taxonomy-diversity-model';
import {rangoVisual3D,resumenTaxon3D} from './portal-taxonomy-three-ranks';
import {actualizarBreadcrumbTaxonomico} from './portal-taxonomy-lineage';
import {crearLeyendaTaxonomica} from './portal-taxonomy-tooltip';
import {distribuirEtiquetasTaxonomicas} from './portal-taxonomy-label-layout';

const numero=n=>Number(n).toLocaleString('es-EC');
const crear=(tag,clase,texto)=>{const el=document.createElement(tag);el.className=clase;if(texto)el.textContent=texto;return el;};
const boton=(nombre,texto,accion)=>{const el=crear('button','',texto);el.type='button';el.setAttribute('aria-label',nombre);el.title=nombre;el.addEventListener('click',accion);return el;};

/** Yemas con volumen, tallos finos y apertura progresiva de clados reales. */
export function crearEscenaDiversidad(elemento,acciones) {
    const marco=crear('div','portal-diversity-scene');marco.dataset.motor='Three.js';
    const cabecera=crear('div','portal-three-context'),identidad=crear('div','portal-three-identity');
    const breadcrumb=crear('nav','portal-taxonomy-breadcrumb');cabecera.append(breadcrumb);
    const titulo=crear('h3','portal-three-title'),meta=crear('p','portal-three-meta');identidad.append(titulo,meta);
    const controles=crear('div','portal-three-controls');
    const volverAlPadre=()=>{const padre=indice?.porClave.get(raiz)?.padre;if(indice?.porClave.has(padre))acciones.navegar(padre);};
    const volver=boton('Volver al padre de la rama','↶',volverAlPadre);
    controles.append(volver);cabecera.append(identidad,controles);
    const maximizar=boton('Ver visualizador en pantalla completa','⛶',()=>pantallaCompleta().catch(()=>error('No se pudo activar la pantalla completa.')));maximizar.className='portal-three-maximize';
    const barra=elemento.closest('.taxonomy-navigator').querySelector('.taxonomy-navigator-zoom');barra.prepend(cabecera);barra.append(maximizar);
    const vista=crear('div','portal-diversity-viewport'),etiquetas=crear('div','portal-diversity-labels');
    const uniones=document.createElementNS('http://www.w3.org/2000/svg','svg');uniones.classList.add('portal-diversity-label-links');uniones.setAttribute('aria-hidden','true');
    const ayuda=crear('p','sr-only','Rueda o pellizco: zoom progresivo. Arrastrar: desplazar. Ctrl y arrastrar o Ctrl y flechas: girar en 3D. Inicio: vista inicial. AvPág y RePág: explorar hijos. Intro: abrir rama.');ayuda.id='portal-diversity-help';
    const escala=crear('div','portal-diversity-scale'),panelZoom=crear('div','portal-diversity-slider-panel'),deslizador=crear('input','portal-diversity-slider');
    const escalaTexto=boton('Mostrar control de zoom','',()=>{abrirControlZoom();deslizador.focus({preventScroll:true});});escalaTexto.className='portal-diversity-zoom-value';
    panelZoom.id='portal-diversity-slider-panel';panelZoom.hidden=true;escalaTexto.setAttribute('aria-controls',panelZoom.id);escalaTexto.setAttribute('aria-expanded','false');
    deslizador.type='range';deslizador.min='0';deslizador.max='1000';deslizador.step='1';deslizador.setAttribute('aria-label','Zoom del árbol');deslizador.setAttribute('aria-orientation','vertical');panelZoom.append(deslizador);escala.append(panelZoom,escalaTexto);
    const leyenda=crear('div','portal-diversity-legend');leyenda.setAttribute('role','group');leyenda.setAttribute('aria-label','Rangos taxonómicos: abrir u ocultar niveles');
    const estado=crear('p','portal-diversity-status');estado.setAttribute('role','alert');estado.hidden=true;
    marco.append(vista,leyenda);elemento.replaceChildren(marco);
    const controlesAmpliados=crear('div','portal-diversity-fullscreen-controls');controlesAmpliados.setAttribute('role','group');controlesAmpliados.setAttribute('aria-label','Navegación, zoom y pantalla completa');
    const volverAmpliado=boton('Volver al padre de la rama en pantalla completa','↶',volverAlPadre);
    const alejar=boton('Alejar árbol en pantalla completa','−',()=>zoom(1/1.6)),acercar=boton('Acercar árbol en pantalla completa','+',()=>zoom(1.6));
    const salir=boton('Salir de pantalla completa','⛶',()=>salirDeAmpliacion());salir.className='portal-diversity-fullscreen-exit';controlesAmpliados.append(volverAmpliado,alejar,acercar,salir);marco.append(controlesAmpliados);
    let renderer;
    try{renderer=new WebGLRenderer({antialias:true,alpha:true,powerPreference:'low-power',logarithmicDepthBuffer:true});}
    catch(fallo){cabecera.remove();maximizar.remove();elemento.replaceChildren();throw fallo;}
    renderer.setPixelRatio(Math.min(globalThis.devicePixelRatio||1,1.5));
    const canvas=renderer.domElement;canvas.className='portal-diversity-canvas';canvas.tabIndex=0;canvas.setAttribute('role','img');
    canvas.setAttribute('aria-keyshortcuts','ArrowLeft ArrowRight ArrowUp ArrowDown Control+ArrowLeft Control+ArrowRight Control+ArrowUp Control+ArrowDown PageDown PageUp Enter Home + -');canvas.setAttribute('aria-describedby',ayuda.id);
    vista.append(canvas,uniones,etiquetas,ayuda,escala,estado);
    const tooltip=crearLeyendaTaxonomica(vista,canvas);
    const escena=new Scene(),camara=new PerspectiveCamera(38,1,.01,10000),camaraGPU=new PerspectiveCamera(),origenGPU=new Vector3();escena.fog=new Fog('#f4f7f1',1000,2000);
    escena.add(new HemisphereLight('#ffffff','#405044',1.8));
    const luz=new DirectionalLight('#ffffff',1.8);luz.position.set(-400,600,800);escena.add(luz);
    const lateral=new DirectionalLight('#ffffff',.6);lateral.position.set(500,180,-300);escena.add(lateral);
    const materialRamas=new LineBasicMaterial({vertexColors:true,transparent:true,opacity:.85,depthWrite:false});
    const materialHojas=new MeshStandardMaterial({roughness:.56,metalness:.02});
    const geometriaHoja=new SphereGeometry(1,12,8),ramas=new LineSegments(new BufferGeometry(),materialRamas);ramas.frustumCulled=false;escena.add(ramas);
    let hojas;
    const orbit=new OrbitControls(camara,canvas);orbit.enableDamping=false;orbit.enableZoom=false;
    orbit.mouseButtons.LEFT=MOUSE.PAN;orbit.touches.ONE=TOUCH.PAN;orbit.touches.TWO=TOUCH.DOLLY_PAN;orbit.rotateSpeed=.7;orbit.panSpeed=.8;
    const frustum=new Frustum(),matriz=new Matrix4(),caja=new Box3(),punto=new Vector3(),objeto=new Object3D();
    const colores=new Map(),etiquetasDOM=new Map(),troncosCache=new Map(),abort=new AbortController();
    const reducido=matchMedia('(prefers-reduced-motion: reduce)');
    let indice,arbol,fuente,raiz,ancho=1,alto=1,base=1,minZoom=.5,maxZoom=.5,radioArbol=1,rangosPresentes=[],rangosNodos=[];
    let frame=null,destruido=false,activo=true,elegido=0,resaltado='',hijoTeclado=-1,puntos=[],pulsacion=null,destino=null,firma='',revision=0,limiteRango=3;
    let dimensionado=false,ampliado=false,ampliacionAutomatica=true,fullscreenPendiente=false;
    let focoZoom=0,rangoInicial=0,encuadreAutomatico=true,arrastrandoZoom=false,rangoForzado=null;
    const dialogo=elemento.closest('dialog'),toques=new Map();
    const escuchar=(el,tipo,fn,opciones={})=>el.addEventListener(tipo,fn,{...opciones,signal:abort.signal});
    function abrirControlZoom(){panelZoom.hidden=false;escalaTexto.setAttribute('aria-expanded','true');}
    function cerrarControlZoom(){if(arrastrandoZoom)return;if(document.activeElement===deslizador)escalaTexto.focus({preventScroll:true});panelZoom.hidden=true;escalaTexto.setAttribute('aria-expanded','false');}
    escuchar(escala,'pointerenter',e=>{if(e.pointerType!=='touch')abrirControlZoom();});
    escuchar(escala,'pointerleave',cerrarControlZoom);
    escuchar(escala,'focusout',e=>{if(!escala.contains(e.relatedTarget))cerrarControlZoom();});
    escuchar(deslizador,'pointerdown',()=>{arrastrandoZoom=true;});
    escuchar(document,'pointerup',()=>{if(!arrastrandoZoom)return;arrastrandoZoom=false;if(!escala.matches(':hover'))cerrarControlZoom();});
    escuchar(deslizador,'pointercancel',()=>{arrastrandoZoom=false;cerrarControlZoom();});
    escuchar(escala,'keydown',e=>{if(e.key==='Escape'){e.preventDefault();e.stopPropagation();cerrarControlZoom();}});
    const color=valor=>{if(!colores.has(valor))colores.set(valor,new Color(valor));return colores.get(valor);};
    const posicion=(i,v=new Vector3())=>v.set(arbol.posiciones[i*2],arbol.posiciones[i*2+1],arbol.profundidad[i]);
    function programar(){if(frame!==null||destruido||!activo||document.hidden)return;frame=requestAnimationFrame(()=>{frame=null;if(!destruido&&activo&&elemento.closest('dialog')?.open&&arbol)dibujar();});}
    function mensaje(){actualizarBreadcrumbTaxonomico(breadcrumb,indice,raiz,acciones.navegar);}
    function error(texto){estado.textContent=texto;estado.hidden=false;}
    function cajaClado(i){const b=arbol.limites3D.subarray(i*6,i*6+6);return caja.min.set(b[0],b[1],b[2]),caja.max.set(b[3],b[4],b[5]),caja;}
    function margenInferior(){return (ancho<600?110:96)+(ampliado?leyenda.offsetHeight:0);}
    function encuadrar(i=0,animado=false,conservarOrientacion=false){
        if(!arbol)return;
        focoZoom=i;encuadreAutomatico=i===0;
        const bounds=cajaClado(i).clone();
        if(!i){
            bounds.makeEmpty();const inicial=detalleTaxonomicoDiversidad(rangosNodos[0],.5,rangosPresentes,rangoInicial).orden;
            let expuestos=0;
            for(let j=0;j<arbol.claves.length;j++)if(j===0||rangosNodos[j]<=inicial){bounds.expandByPoint(posicion(j));expuestos++;}
            // Si la selección es una Clase/Familia individual, reservar el
            // espacio de su corona: el siguiente zoom debe descubrir hijos,
            // en vez de encuadrar sólo el marcador y dejarlos fuera de pantalla.
            if(expuestos===1&&arbol.hijos[0].length)bounds.copy(cajaClado(0));
        }
        bounds.expandByScalar(Math.max(2,arbol.radios[i]*2.8));const centro=bounds.getCenter(new Vector3()),tamano=bounds.getSize(new Vector3());
        let distancia=Math.max(tamano.y,tamano.x/Math.max(.2,camara.aspect),tamano.z*.8,40)/(2*Math.tan(camara.fov*Math.PI/360)) * 1.28;
        const direccion=animado||conservarOrientacion?camara.position.clone().sub(orbit.target).normalize():new Vector3(.8,.45,1).normalize();
        const objetivo={posicion:centro.clone().addScaledVector(direccion,distancia),centro};
        if(!i){
            const inicial=detalleTaxonomicoDiversidad(rangosNodos[0],.5,rangosPresentes,rangoInicial).orden;
            const encuadre=encuadreRaizDiversidad(arbol,inicial,ancho,alto,{inferior:margenInferior(),direccion:direccion.toArray(),reserva:1.2});
            objetivo.centro.fromArray(encuadre.centro);objetivo.posicion.fromArray(encuadre.posicion);distancia=encuadre.distancia;
        }
        if(!i){base=distancia*.5;radioArbol=Math.max(50,tamano.length());maxZoom=Math.max(2,limiteZoomRangosDiversidad(rangosNodos[0],rangosPresentes,rangoInicial)*2);minZoom=zoomParaRangoDiversidad(rangosNodos[0],Math.max(3,rangosNodos[0]),rangosPresentes,rangoInicial);orbit.minDistance=base/maxZoom;orbit.maxDistance=base/minZoom;}
        if(animado&&!reducido.matches)destino=objetivo;else{destino=null;camara.position.copy(objetivo.posicion);orbit.target.copy(objetivo.centro);orbit.update();}
        marco.dataset.animando=String(Boolean(destino));tooltip.ocultar();programar();
    }
    function ajustar(){rangoForzado=detalleTaxonomicoDiversidad(rangosNodos[0],.5,rangosPresentes,rangoInicial).orden;encuadrar();}
    function zoomActual(){return base/(destino?.posicion||camara.position).distanceTo(destino?.centro||orbit.target);}
    function irAlZoom(valor,referencia,automatico=true){
        if(!arbol||!Number.isFinite(valor))return;
        if(automatico)rangoForzado=null;
        const objetivo=Math.max(minZoom,Math.min(maxZoom,valor));
        moverZoom(objetivo,referencia);
    }
    function zoom(factor,referencia,forzarPantallaCompleta=false){
        if(!arbol||!Number.isFinite(factor)||factor<=0)return;
        if(!ampliado&&(forzarPantallaCompleta||factor>1&&ampliacionAutomatica))ampliarAutomaticamente();
        irAlZoom(zoomActual()*factor,referencia);
    }
    function moverZoom(zoomObjetivo,referencia){
        // En la vista general la raíz permanece anclada. Para explorar detalle,
        // el acercamiento conserva una yema real cerca del cursor o del centro,
        // evitando ampliar únicamente el espacio vacío encima de la raíz.
        // Los gestos fraccionarios pueden llegar a 1 + un error de redondeo.
        // Esa tolerancia conserva el anclaje de la vista general hasta 1×.
        const vistaGeneral=rangoForzado===null&&zoomObjetivo<=1+1e-9;
        if(vistaGeneral&&zoomActual()>1+1e-9)encuadrar(0,false,true);
        encuadreAutomatico=vistaGeneral;
        const centro=destino?.centro||orbit.target,origen=destino?.posicion||camara.position;
        const escalaActual=base/origen.distanceTo(centro);
        focoZoom=0;let centrarYema=false;
        if(!vistaGeneral){
            const cursor=referencia?coordenada(referencia):{x:ancho/2,y:alto*.42};let cerca=Infinity;
            const limite=rangoForzado??Math.max(...rangosPresentes);
            const buscarEspecie=zoomObjetivo>=maxZoom*.999&&rangosPresentes.includes(17)&&rangoForzado===null;
            const rangoObjetivo=buscarEspecie?17:rangoForzado,buscarRango=rangoObjetivo!==null;
            for(let i=1;i<arbol.claves.length;i++){
                if(rangosNodos[i]>limite||buscarRango&&rangosNodos[i]!==rangoObjetivo)continue;const p=proyectar(i);
                if(p.z< -1||p.z>1||!buscarRango&&(p.x<20||p.x>ancho-20||p.y<50||p.y>alto-margenInferior()))continue;
                const d=Math.hypot(p.x-cursor.x,p.y-cursor.y);if(d<cerca){cerca=d;focoZoom=i;}
            }
            // En un linaje filtrado la única especie puede quedar detrás de
            // la cámara al acercarse a sus ancestros. El zoom máximo y la
            // leyenda deben centrar una yema real del rango solicitado,
            // aunque aún esté fuera del recorte.
            if(buscarRango&&focoZoom===0){
                for(let i=1;i<arbol.claves.length;i++){
                    if(rangosNodos[i]!==rangoObjetivo)continue;
                    const d=posicion(i).distanceToSquared(centro);if(d<cerca){cerca=d;focoZoom=i;}
                }
            }
            centrarYema=buscarRango&&focoZoom>0;
        }
        const objetivo=zoomRaizDiversidad({centro:centro.toArray(),posicion:origen.toArray()},posicion(focoZoom).toArray(),zoomObjetivo/escalaActual,{min:orbit.minDistance,max:orbit.maxDistance});
        if(centrarYema){const direccion=new Vector3(...objetivo.posicion).sub(new Vector3(...objetivo.centro)),foco=posicion(focoZoom);objetivo.centro=foco.toArray();objetivo.posicion=foco.clone().add(direccion).toArray();}
        destino={centro:new Vector3(...objetivo.centro),posicion:new Vector3(...objetivo.posicion)};
        Object.assign(marco.dataset,{yemaZoom:arbol.claves[focoZoom],registrosYema:String(arbol.registros[focoZoom])});
        if(reducido.matches){camara.position.copy(destino.posicion);orbit.target.copy(destino.centro);destino=null;orbit.update();}
        marco.dataset.animando=String(Boolean(destino));tooltip.ocultar();programar();
    }
    escuchar(deslizador,'input',()=>{if(!ampliado)ampliarAutomaticamente();abrirControlZoom();deslizador.focus({preventScroll:true});irAlZoom(minZoom*(maxZoom/minZoom)**(Number(deslizador.value)/1000));});
    escuchar(escala,'wheel',e=>e.stopPropagation());
    function activarRango(r){
        ampliarAutomaticamente();
        if(r.orden===0||r.orden===3){resaltado=resaltado===r.id?'':r.id;marco.dataset.rangoResaltado=resaltado;programar();return;}
        const actual=rangoForzado??Math.max(...puntos.map(p=>rangosNodos[p.i]));
        const umbral=zoomParaRangoDiversidad(rangosNodos[0],r.orden,rangosPresentes,rangoInicial);
        const nivel=actual>=r.orden?Math.max(minZoom,umbral/1.6):umbral;
        rangoForzado=actual>=r.orden?Math.max(rangosNodos[0],...rangosPresentes.filter(n=>n<r.orden)):r.orden;
        irAlZoom(nivel,undefined,false);
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
        const distancia=camara.position.distanceTo(orbit.target),factor=base/distancia;camara.near=Math.max(1e-9,distancia*.00003);camara.far=distancia+Math.max(radioArbol,ancho,alto)*5;camara.updateProjectionMatrix();camara.updateMatrixWorld();
        limiteRango=rangoForzado??Math.max(...rangosPresentes);
        escena.fog.near=distancia*.6;escena.fog.far=distancia+Math.max(radioArbol*2,distancia*.8);
        // Origen flotante: las coordenadas completas siguen en doble precisión;
        // los buffers GPU contienen sólo diferencias locales durante el zoom.
        if(origenGPU.distanceTo(orbit.target)>distancia*24){origenGPU.copy(orbit.target);firma='';}
        frustum.setFromProjectionMatrix(matriz.multiplyMatrices(camara.projectionMatrix,camara.matrixWorldInverse));
        const medidas=new Map(),presupuesto=ancho<600?80:160,focal=alto/(2*Math.tan(camara.fov*Math.PI/360));
        const {visibles,brotes:colapsados}=seleccionarDetalleDiversidad(arbol,rangosNodos,limiteRango,i=>{
            if(!frustum.intersectsBox(cajaClado(i).expandByScalar(arbol.radios[i]*3)))return null;
            const p=proyectar(i),padre=arbol.padres[i],prof=-posicion(i,punto).applyMatrix4(camara.matrixWorldInverse).z;
            const longitudRama=padre<0||rangosNodos[i]<=Math.max(3,rangoInicial)||rangoForzado!==null?Infinity:posicion(i).distanceTo(posicion(padre))*focal/Math.max(.00001,prof);
            const m={...p,extension:extension(i),longitudRama};medidas.set(i,m);return m;
        },{presupuesto,ancho,alto,umbral:rangoForzado===null?Math.max(12,50/factor):0,umbralRama:72});
        // La raíz anclada tiene prioridad sobre la agrupación de densidad,
        // sin aumentar el presupuesto ni abrir rangos posteriores.
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
        const candidatos=[];let cantidad=0;
        for(const i of visibles){
            const p=medidas.get(i);if(p.z< -1||p.z>1||p.x< -30||p.x>ancho+30||p.y< -30||p.y>alto+30)continue;
            const nodo=indice.porClave.get(arbol.claves[i]),rango=rangoVisual3D(nodo),hoja=!arbol.hijos[i].length,brote=colapsados.has(i);
            if(i!==0&&rango.orden>limiteRango)continue;
            const prof=-posicion(i,punto).applyMatrix4(camara.matrixWorldInverse).z;
            const perspectiva=Math.max(.55,Math.min(1.65,distancia/prof));
            const radio=prof/focal*Math.min(28,radioYemaDiversidad(arbol.especies[i]))*perspectiva;
            posicion(i,objeto.position).sub(origenGPU);objeto.quaternion.identity();objeto.scale.setScalar(radio);objeto.updateMatrix();
            hojas.setMatrixAt(cantidad,objeto.matrix);hojas.setColorAt(cantidad,color(resaltado&&rango.id!==resaltado&&i!==elegido?'#acb8a8':rango.color));cantidad++;
            const tamano=radio*focal/prof;puntos.push({i,...p,tamano});
            if(i===0||i===elegido||i===focoZoom||hoja||brote||rango.orden===limiteRango||tamano>15||p.extension>150)candidatos.push({i,...p,espacio:tamano*2,hoja,brote});
        }
        hojas.count=cantidad;hojas.instanceMatrix.needsUpdate=true;if(hojas.instanceColor)hojas.instanceColor.needsUpdate=true;
        dibujarEtiquetas(candidatos);camaraGPU.copy(camara);camaraGPU.position.sub(origenGPU);camaraGPU.updateMatrixWorld();renderer.render(escena,camaraGPU);
        escalaTexto.textContent=`${numero(Math.round(factor*100)/100)}×`;
        deslizador.value=String(maxZoom===minZoom?0:Math.round(Math.log(zoomActual()/minZoom)/Math.log(maxZoom/minZoom)*1000));deslizador.disabled=maxZoom===minZoom;deslizador.setAttribute('aria-valuetext',escalaTexto.textContent);escalaTexto.setAttribute('aria-label',`Mostrar control de zoom: ${escalaTexto.textContent}`);
        acciones.zoom?.(factor);acciones.limitesZoom?.(minZoom,maxZoom);acciones.ajustado?.(Math.abs(factor-.5)<.001&&!destino);
        marco.dataset.rangosDibujados=[...new Set(puntos.map(p=>rangoVisual3D(indice.porClave.get(arbol.claves[p.i])).nombre))].join(',');
        alejar.disabled=factor<=minZoom*1.00001;acercar.disabled=factor>=maxZoom*.99999;
        const raizProyectada=proyectar(0);
        const masProfundo=puntos.reduce((a,p)=>rangosNodos[p.i]>rangosNodos[a]?p.i:a,0);
        for(const b of leyenda.children){const orden=Number(b.dataset.orden);if(orden===0||orden===3)b.setAttribute('aria-pressed',String(b.dataset.rango===resaltado));else b.setAttribute('aria-expanded',String(!b.disabled&&orden<=(rangoForzado??rangosNodos[masProfundo])));}
        Object.assign(marco.dataset,{zoom:factor.toFixed(3),zoomMin:String(minZoom),zoomMax:String(maxZoom),distanciaCamara:String(distancia),baseCamara:String(base),raizX:String(raizProyectada.x),raizY:String(raizProyectada.y),ramasDibujadas:String(Math.max(0,visibles.length-1)),presupuesto:String(presupuesto),cacheTroncos:String(troncosCache.size),etiquetas:String(etiquetasDOM.size),enEncuadre:String(puntos.length),contraidos:String(colapsados.size),nivelDetalle:String(puntos.reduce((nivel,p)=>Math.max(nivel,arbol.niveles[p.i]),0)),rangoDetalle:rangoVisual3D(indice.porClave.get(arbol.claves[masProfundo])).nombre,drawCalls:String(renderer.info.render.calls),frames:String(renderer.info.render.frame),renderMs:(performance.now()-inicio).toFixed(2),centro:orbit.target.toArray().join(','),orientacion:camara.quaternion.toArray().map(n=>n.toFixed(5)).join(','),animando:String(Boolean(destino))});
        if(destino)programar();
    }
    function dibujarEtiquetas(candidatos){
        candidatos.sort((a,b)=>(a.i===focoZoom?-1:b.i===focoZoom?1:a.i===elegido?-1:b.i===elegido?1:arbol.niveles[a.i]-arbol.niveles[b.i])||b.espacio-a.espacio);
        const usados=new Set(),reservadas=[];
        if(ampliado){const r=cabecera.getBoundingClientRect(),v=vista.getBoundingClientRect();reservadas.push({x:(r.left-v.left)*ancho/v.width,y:(r.top-v.top)*alto/v.height,w:r.width*ancho/v.width,h:r.height*alto/v.height});}
        uniones.setAttribute('viewBox',`0 0 ${ancho} ${alto}`);const lineas=[];
        const situadas=distribuirEtiquetasTaxonomicas(candidatos.map(p=>({...p,tamano:p.espacio/2,w:Math.min(230,Math.max(112,indice.porClave.get(arbol.claves[p.i]).nombre.length*7.5+20))})),puntos,ancho,alto,reservadas);
        for(const p of situadas){
            const nodo=indice.porClave.get(arbol.claves[p.i]),{x,y,w}=p;
            usados.add(p.i);let el=etiquetasDOM.get(p.i);
            if(!el){el=boton(`${nodo.nombre}. Explorar esta rama`,'',e=>{if(!e.ctrlKey)elegirNodo(p.i);});el.className='portal-diversity-label';el.tabIndex=-1;el.append(crear('strong','',nodo.nombre),crear('small','',resumenTaxon3D(nodo)));etiquetasDOM.set(p.i,el);etiquetas.append(el);el.addEventListener('contextmenu',e=>{e.preventDefault();e.stopPropagation();abrirMenu(p.i,e);});}
            const ancla=puntos.find(n=>n.i===p.i);
            el.style.transform=`translate(${x}px,${y}px)`;el.style.width=`${w}px`;el.dataset.puntoX=String(ancla.x);el.dataset.puntoY=String(ancla.y);el.dataset.especies=String(arbol.especies[p.i]);el.dataset.radioYema=String(p.espacio/2);el.dataset.clave=arbol.claves[p.i];el.classList.toggle('is-selected',p.i===elegido);el.classList.toggle('is-leaf',p.hoja);
            el.querySelector('small').textContent=resumenTaxon3D(nodo);
            const linea=document.createElementNS('http://www.w3.org/2000/svg','line');linea.setAttribute('x1',p.inicio.x);linea.setAttribute('y1',p.inicio.y);linea.setAttribute('x2',p.extremo.x);linea.setAttribute('y2',p.extremo.y);linea.dataset.clave=arbol.claves[p.i];lineas.push(linea);
        }
        uniones.replaceChildren(...lineas);
        for(const [i,el]of etiquetasDOM)if(!usados.has(i)){el.remove();etiquetasDOM.delete(i);}
    }
    function coordenada(e){const r=canvas.getBoundingClientRect();return{x:(e.clientX-r.left)*ancho/r.width,y:(e.clientY-r.top)*alto/r.height};}
    function elegirNodo(i){acciones.seleccionar(arbol.claves[i]);}
    function buscar(e){const p=coordenada(e);let mejor=-1,distancia=Infinity,prof=Infinity;for(const n of puntos){const d=Math.hypot(p.x-n.x,p.y-n.y);if(d<=Math.max(9,n.tamano*1.15)&&(d<distancia-2||Math.abs(d-distancia)<=2&&n.z<prof)){mejor=n.i;distancia=d;prof=n.z;}}return mejor;}
    function mostrarDetalle(i){if(i<0)return tooltip.ocultar();tooltip.mostrar(indice.porClave.get(arbol.claves[i]),proyectar(i));}
    function abrirMenu(i,e){if(i<0)return;const p=proyectar(i),r=canvas.getBoundingClientRect(),x=r.left+p.x*r.width/ancho,y=r.top+p.y*r.height/alto;acciones.menu(arbol.claves[i],e,{left:x-9,right:x+9,top:y-9,bottom:y+9,limite:r});}
    // Las etiquetas flotan sobre el canvas. Ctrl+arrastre también debe iniciar
    // el giro al pulsar una de ellas; la captura real continúa sobre el canvas.
    escuchar(etiquetas,'pointerdown',e=>{
        if(!e.ctrlKey||e.button!==0||e.pointerType==='touch')return;
        e.preventDefault();e.stopPropagation();
        canvas.dispatchEvent(new PointerEvent('pointerdown',{bubbles:true,cancelable:true,pointerId:e.pointerId,pointerType:e.pointerType,isPrimary:e.isPrimary,clientX:e.clientX,clientY:e.clientY,button:e.button,buttons:e.buttons,ctrlKey:e.ctrlKey,shiftKey:e.shiftKey,altKey:e.altKey,metaKey:e.metaKey}));
    });
    // El origen del límite orbital coincide con el objetivo del gesto. Así el
    // giro no renormaliza ni desplaza su centro, incluso con coordenadas dobles.
    orbit.addEventListener('change',programar);orbit.addEventListener('start',()=>{orbit.cursor.copy(orbit.target);destino=null;focoZoom=-1;encuadreAutomatico=false;marco.dataset.animando='false';tooltip.ocultar();marco.dataset.rotando='true';});orbit.addEventListener('end',()=>{marco.dataset.rotando='false';});
    escuchar(vista,'wheel',e=>{if(escala.contains(e.target))return;e.preventDefault();e.stopPropagation();zoom(Math.exp(-Math.max(-120,Math.min(120,e.deltaY))*Math.log(1.6)/120),e);},{passive:false,capture:true});
    // El pellizco comparte el zoom físico y el punto de interés de la rueda.
    // OrbitControls sólo recibe el ratón; no aplica otro dolly paralelo.
    const gesto=()=>{const a=[...toques.values()];return a.length===2?{distancia:Math.hypot(a[0].x-a[1].x,a[0].y-a[1].y),clientX:(a[0].x+a[1].x)/2,clientY:(a[0].y+a[1].y)/2}:null;};
    escuchar(canvas,'pointerdown',e=>{if(e.pointerType!=='touch')return;e.stopImmediatePropagation();canvas.setPointerCapture(e.pointerId);tooltip.ocultar();destino=null;const t={x:e.clientX,y:e.clientY,inicioX:e.clientX,inicioY:e.clientY,movido:false};toques.set(e.pointerId,t);if(toques.size>1)for(const t of toques.values())t.movido=true;},{capture:true});
    escuchar(canvas,'pointermove',e=>{const t=toques.get(e.pointerId);if(!t)return;e.preventDefault();e.stopImmediatePropagation();const antes=gesto(),dx=e.clientX-t.x,dy=e.clientY-t.y;t.x=e.clientX;t.y=e.clientY;t.movido ||=Math.hypot(t.x-t.inicioX,t.y-t.inicioY)>5;const despues=gesto();if(antes&&despues&&antes.distancia>0)zoom(despues.distancia/antes.distancia,despues);else if(toques.size===1&&t.movido){destino=null;focoZoom=-1;encuadreAutomatico=false;orbit.pan(-dx,-dy);orbit.update();programar();}},{capture:true,passive:false});
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
        if(e.key==='Home')return ajustar();if(['+','='].includes(e.key))return zoom(1.6,undefined,true);if(e.key==='-')return zoom(1/1.6,undefined,true);
        if(e.key==='Enter'){elegirNodo(elegido);return;}
        if(['PageDown','PageUp'].includes(e.key)){const lista=arbol.hijos[0];if(!lista.length)return;hijoTeclado=(hijoTeclado+(e.key==='PageDown'?1:-1)+lista.length)%lista.length;elegido=lista[hijoTeclado];mensaje();encuadrar(elegido,true);mostrarDetalle(elegido);return;}
        encuadreAutomatico=false;
        if(e.ctrlKey){orbit.cursor.copy(orbit.target);if(e.key==='ArrowLeft'||e.key==='ArrowRight')orbit.rotateLeft(e.key==='ArrowLeft'? .13:-.13);else orbit.rotateUp(e.key==='ArrowUp'? .13:-.13);}else{focoZoom=-1;orbit.pan(({'ArrowLeft':70,'ArrowRight':-70}[e.key]||0),({'ArrowUp':70,'ArrowDown':-70}[e.key]||0));}orbit.update();programar();
    });
    escuchar(canvas,'webglcontextlost',e=>{e.preventDefault();error('Se perdió el contexto gráfico. Cambia de visualización para recuperarlo; la jerarquía de la izquierda sigue disponible.');});
    escuchar(canvas,'webglcontextrestored',()=>{estado.hidden=true;firma='';programar();});
    escuchar(document,'visibilitychange',()=>{if(document.hidden){if(frame!==null)cancelAnimationFrame(frame);frame=null;destino=null;marco.dataset.animando='false';tooltip.ocultar();}else programar();});
    const resize=new ResizeObserver(()=>{
        if(destruido||!vista.clientWidth||!vista.clientHeight)return;
        const w=vista.clientWidth,h=vista.clientHeight;if(w===ancho&&h===alto)return;
        const factor=base/(destino?.posicion||camara.position).distanceTo(destino?.centro||orbit.target);
        ancho=w;alto=h;camara.aspect=w/h;camara.updateProjectionMatrix();renderer.setSize(w,h,false);
        if(arbol&&!dimensionado)ajustar();
        else if(arbol&&encuadreAutomatico){
            // Al entrar/salir de pantalla completa se adapta una sola vez el
            // encuadre al área útil. Después el zoom mantiene esa raíz inmóvil.
            encuadrar(0,false,true);
            const objetivo=zoomRaizDiversidad({centro:orbit.target.toArray(),posicion:camara.position.toArray()},posicion(0).toArray(),factor/.5,{min:orbit.minDistance,max:orbit.maxDistance});
            camara.position.fromArray(objetivo.posicion);orbit.target.fromArray(objetivo.centro);orbit.update();
        }
        dimensionado=true;programar();
    });resize.observe(vista);
    function expandir(){ampliado=true;marco.classList.add('is-expanded');marco.prepend(cabecera);dialogo?.classList.add('has-diversity-expanded');marco.dataset.ampliado='true';canvas.focus({preventScroll:true});}
    function restaurar(){ampliado=false;marco.classList.remove('is-expanded');barra.prepend(cabecera);dialogo?.classList.remove('has-diversity-expanded');marco.dataset.ampliado='false';ampliacionAutomatica=false;canvas.focus({preventScroll:true});}
    function ampliarAutomaticamente(){expandir();if(document.fullscreenEnabled&&navigator.userActivation?.isActive&&!fullscreenPendiente){fullscreenPendiente=true;marco.requestFullscreen().catch(()=>{}).finally(()=>{fullscreenPendiente=false;});}}
    async function salirDeAmpliacion(){restaurar();if(document.fullscreenElement===marco)await document.exitFullscreen();}
    async function pantallaCompleta(){if(ampliado)return salirDeAmpliacion();expandir();if(document.fullscreenEnabled&&navigator.userActivation?.isActive)await marco.requestFullscreen().catch(()=>{});}
    escuchar(document,'fullscreenchange',()=>{if(!document.fullscreenElement&&ampliado)restaurar();});
    escuchar(document,'keydown',e=>{if(!ampliado)return;if(e.key==='Escape'){if(!panelZoom.hidden&&escala.contains(document.activeElement)){e.preventDefault();e.stopImmediatePropagation();cerrarControlZoom();return;}if(marco.querySelector('[role="menu"]')?.checkVisibility())return;e.preventDefault();e.stopImmediatePropagation();salirDeAmpliacion();}else if(e.key==='Tab'){const controles=[...marco.querySelectorAll('button:not([tabindex="-1"]),input,canvas')].filter(el=>el.checkVisibility()&&!el.disabled);if(!controles.length)return;e.preventDefault();const i=controles.indexOf(document.activeElement);controles[(i+(e.shiftKey?-1:1)+controles.length)%controles.length].focus({preventScroll:true});}},{capture:true});
    return {
        async actualizar(nuevos,clave,nuevoIndice){
            if(destruido)return;if(fuente===nuevos&&raiz===clave&&indice===nuevoIndice)return;
            revision++;tooltip.ocultar();troncosCache.clear();etiquetasDOM.clear();etiquetas.replaceChildren();fuente=nuevos;indice=nuevoIndice;raiz=clave;arbol=crearArbolDiversidad(indice,clave);rangosNodos=Array.from(arbol.rangos);elegido=0;focoZoom=0;rangoInicial=aperturaRamaDiversidad(arbol);rangoForzado=null;hijoTeclado=-1;resaltado='';firma='';
            if(hojas){escena.remove(hojas);hojas.dispose();}hojas=new InstancedMesh(geometriaHoja,materialHojas,arbol.claves.length);hojas.frustumCulled=false;escena.add(hojas);
            const nodo=indice.porClave.get(clave);titulo.textContent=nodo.nombre;meta.textContent=resumenTaxon3D(nodo);volver.disabled=volverAmpliado.disabled=!indice.porClave.has(nodo.padre);
            const rangos=new Map();for(const k of arbol.claves){const r=rangoVisual3D(indice.porClave.get(k));rangos.set(r.id,r);}leyenda.replaceChildren();
            rangosPresentes=[...rangos.values()].map(r=>r.orden);
            const todos=new Map(LEYENDA_DIVERSIDAD.map(nombre=>{const r=rangoVisual3D({etiqueta:nombre});return[r.id,r];}));
            for(const r of [...todos.values()].sort((a,b)=>a.orden-b.orden)){
                const resaltar=r.orden===0||r.orden===3,b=boton(`${resaltar?'Resaltar':'Abrir u ocultar'} ${r.nombre}`,r.nombre,()=>{activarRango(r);b.focus({preventScroll:true});});
                b.dataset.rango=r.id;b.dataset.orden=String(r.orden);b.setAttribute(resaltar?'aria-pressed':'aria-expanded','false');b.disabled=!rangos.has(r.id);if(b.disabled)b.title=`${r.nombre}: sin taxones de este rango en la rama seleccionada`;b.style.setProperty('--rank-color',r.color);leyenda.append(b);
            }
            canvas.setAttribute('aria-label',`${nodo.nombre}: árbol de diversidad 3D con ${numero(arbol.claves.length)} taxones. Rueda: zoom progresivo que revela ramas; Ctrl y arrastrar o Ctrl y flechas: girar; arrastrar: desplazar; Inicio: vista inicial; AvPág y RePág: hijos; Intro: explorar rama.`);
            Object.assign(marco.dataset,{raiz:clave,nombre:nodo.nombre,nodos:String(arbol.claves.length),terminales:String(arbol.terminales[0]),relaciones:String(arbol.claves.length-1),rotando:'false',rangoResaltado:'',camara:'perspectiva'});estado.hidden=true;mensaje();ajustar();
        },
        ajustar,zoom:factor=>zoom(factor,undefined,true),pantallaCompleta,error,
        seleccionar(clave){const i=arbol?.claves.indexOf(clave)??-1;if(i>=0){elegido=i;if(i===0){rangoInicial=aperturaRamaDiversidad(arbol);rangoForzado=rangoInicial;encuadrar();}mensaje();programar();}},
        async localizar(clave){const i=arbol?.claves.indexOf(clave)??-1;if(i<0)return false;elegido=i;encuadrar(i,true);mensaje();return true;},
        contiene:clave=>Boolean(arbol?.claves.includes(clave)),limpiarSeleccion(){elegido=0;mensaje();ajustar();},rangoActivo:()=>resaltado,
        enfocar(clave){const i=arbol?.claves.indexOf(clave)??-1;if(i<0)return false;elegido=i;canvas.focus({preventScroll:true});mensaje();mostrarDetalle(i);programar();return true;},
        mostrar(valor){activo=valor;cabecera.hidden=maximizar.hidden=!valor;orbit.enabled=valor;if(valor)programar();else{if(frame!==null)cancelAnimationFrame(frame);frame=null;destino=null;marco.dataset.animando='false';tooltip.ocultar();}},
        destruir(){destruido=true;revision++;if(document.fullscreenElement===marco)document.exitFullscreen().catch(()=>{});dialogo?.classList.remove('has-diversity-expanded');abort.abort();toques.clear();resize.disconnect();orbit.dispose();tooltip.destruir();if(frame!==null)cancelAnimationFrame(frame);etiquetasDOM.clear();colores.clear();troncosCache.clear();ramas.geometry.dispose();hojas?.dispose();geometriaHoja.dispose();materialRamas.dispose();materialHojas.dispose();renderer.dispose();renderer.forceContextLoss();cabecera.remove();maximizar.remove();elemento.replaceChildren();arbol=indice=fuente=null;puntos=[];},
    };
}
