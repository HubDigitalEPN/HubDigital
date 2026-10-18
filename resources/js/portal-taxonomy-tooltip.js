import {resumenTaxon3D} from './portal-taxonomy-three-ranks.js';

/** Una leyenda a la vez: salir, girar o cambiar de rama invalida la respuesta. */
export function crearConsultaLeyenda({solicitar, mostrar, programar = setTimeout, cancelar = clearTimeout}) {
    let version=0, timer=null, peticion=null, clave='', destruido=false;
    function limpiar() {version++;if(timer!==null)cancelar(timer);timer=null;peticion?.abort();peticion=null;clave='';}
    return {
        consultar(nodo) {
            if(destruido||!nodo||clave===nodo.clave)return;
            limpiar();clave=nodo.clave;const turno=version;
            timer=programar(async()=>{
                timer=null;const solicitud=new AbortController();peticion=solicitud;
                try {const texto=await solicitar(nodo,solicitud.signal);if(!destruido&&turno===version)mostrar(typeof texto==='string'?texto.slice(0,800):'');}
                catch(error){if(error.name!=='AbortError'&&!destruido&&turno===version)mostrar('');}
                finally{if(peticion===solicitud)peticion=null;}
            },140);
        },
        cancelar:limpiar,
        destruir(){destruido=true;limpiar();},
    };
}

/** Tooltip compartido por el árbol y las esferas; nunca altera la selección. */
export function crearLeyendaTaxonomica(vista,canvas) {
    const detalle=document.createElement('div');detalle.className=`portal-taxonomy-tooltip ${canvas.className.includes('diversity')?'portal-diversity-tooltip':'portal-three-tooltip'}`;detalle.id=`${canvas.className}-tooltip`;
    detalle.setAttribute('role','tooltip');detalle.hidden=true;
    const nombre=document.createElement('strong'),meta=document.createElement('span'),leyenda=document.createElement('p');
    detalle.append(nombre,meta,leyenda);vista.append(detalle);
    const endpoint=vista.closest('dialog')?.dataset.fichaEndpoint;
    let posicion={x:0,y:0},actual='';
    function colocar(){
        const w=detalle.offsetWidth,h=detalle.offsetHeight;
        detalle.style.left=`${Math.max(8,Math.min(vista.clientWidth-w-8,posicion.x+18))}px`;
        detalle.style.top=`${Math.max(8,Math.min(vista.clientHeight-h-8,posicion.y+16))}px`;
    }
    const consulta=crearConsultaLeyenda({
        async solicitar(nodo,signal){
            if(!endpoint)return '';
            const url=new URL(endpoint.replace('__TAXON__',encodeURIComponent(nodo.id)),location.href);
            if(url.origin!==location.origin)return '';
            const r=await fetch(url,{signal,cache:'no-store',credentials:'same-origin',headers:{Accept:'application/json'}});
            if(!r.ok)return '';const ficha=await r.json();return ficha.descripcion;
        },
        mostrar(texto){leyenda.textContent=texto;leyenda.hidden=!texto;colocar();},
    });
    function ocultar(){
        actual='';consulta.cancelar();detalle.hidden=true;delete detalle.dataset.taxon;
        const ids=(canvas.getAttribute('aria-describedby')||'').split(' ').filter(id=>id&&id!==detalle.id);
        if(ids.length)canvas.setAttribute('aria-describedby',ids.join(' '));else canvas.removeAttribute('aria-describedby');
    }
    return {
        mostrar(nodo,p){
            if(!nodo?.nombre?.trim())return ocultar();
            posicion=p;
            if(actual!==nodo.clave){
                actual=nodo.clave;nombre.textContent=nodo.nombre;
                meta.textContent=resumenTaxon3D(nodo);
                leyenda.textContent='';leyenda.hidden=true;detalle.dataset.taxon=nodo.id;
                consulta.consultar(nodo);
            }
            detalle.hidden=false;
            const ids=new Set((canvas.getAttribute('aria-describedby')||'').split(' ').filter(Boolean));ids.add(detalle.id);canvas.setAttribute('aria-describedby',[...ids].join(' '));colocar();
        },
        ocultar,
        destruir(){consulta.destruir();ocultar();detalle.remove();},
    };
}
