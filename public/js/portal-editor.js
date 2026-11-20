(() => {
window.prepararPortalEditorial = (doc,mapa={}) => {
    // El servidor entrega la maqueta sin scripts. Las sustituciones de texto
    // se aplican desde el editor y siempre mediante nodos de texto.
    const json=id=>{try{return JSON.parse(doc.getElementById(id)?.textContent||'{}');}catch{return {};}};
    const dic=Object.assign({},json('textos-sistema-datos'),json('textos-editoriales-datos'));
    const normalizar=s=>s.replace(/\s+/gu,' ').trim();
    const claves=Object.keys(dic).sort((a,b)=>b.length-a.length).map(s=>s.replace(/[.*+?^${}()|[\]\\]/gu,'\\$&').replace(/\s+/gu,'\\s+'));
    const patron=claves.length?new RegExp('(?<![\\p{L}\\p{N}_])(?:'+claves.join('|')+')(?![\\p{L}\\p{N}_])','gu'):null;
    const traducir=s=>dic[normalizar(s)]??(patron?s.replace(patron,t=>dic[normalizar(t)]??t):s);
    const walker=doc.createTreeWalker(doc.body,4);while(walker.nextNode()){const n=walker.currentNode;if(!n.parentElement.closest('script,style,textarea,pre,code,dd,td,[data-copy-preserve]'))n.nodeValue=traducir(n.nodeValue);}
    doc.querySelectorAll('[title],[alt],[aria-label],[placeholder]').forEach(el=>{if(el.closest('[data-copy-preserve],td,dd'))return;for(const a of ['title','alt','aria-label','placeholder'])if(el.hasAttribute(a))el.setAttribute(a,traducir(el.getAttribute(a)));});
    doc.querySelectorAll('header a,header span,footer a,footer p').forEach(el=>{if(el.hasAttribute('data-portal-copy')||el.closest('[data-editor-no-text]'))return;const keys=Object.keys(mapa).filter(k=>k.startsWith('navegacion.')&&normalizar(mapa[k])===normalizar(el.textContent));if(keys.length===1)el.dataset.portalCopy=keys[0];});
    const style=doc.createElement('style');style.textContent='[data-vista-editorial] input,[data-vista-editorial] select,[data-vista-editorial] textarea{pointer-events:none} [data-vista-editorial] .portal-mobile-actions{display:none!important} @media(max-width:1023px){[data-vista-editorial] .portal-header-container{height:auto!important;min-height:4.75rem;flex-wrap:wrap;gap:.5rem;padding-block:.5rem}[data-vista-editorial] header nav:not(#menu-portal-movil){display:flex!important;order:3;width:100%;height:3rem;justify-content:center}}';doc.head.append(style);
};
const textoEditorial = el => {
    const clone=el.cloneNode?.(true);
    clone?.querySelectorAll('[data-disposicion-handle]').forEach(n=>n.remove());
    return (clone?.textContent ?? el.textContent ?? '').replace(/\s+/gu,' ').trim();
};
window.portalVisualEditor = (wire,mapa,scope='portal:inicio',paginas={},originales={}) => ({
    wire,mapa,scope,paginas,originales,opciones:[],mensaje:'',mensajePublicacion:'',
    errorSeleccion:'',originalLocal:'',textoVisible:'',textoLocal:'',claveLocal:'',publicando:false,
    init(){this.$nextTick(()=>{this.bloquear();this.ajustarAltura();if(this.$refs.portal?.contentDocument?.readyState==='complete')this.iniciar();});this._resize=()=>this.ajustarAltura();window.addEventListener('resize',this._resize);},
    destroy(){window.removeEventListener('resize',this._resize);clearTimeout(this._navegacion);},
    ajustarAltura(){const f=this.$refs.portal;if(f?.getBoundingClientRect)f.style.setProperty('--hub-editor-altura',Math.max(220,window.innerHeight-f.getBoundingClientRect().top-88)+'px');},
    bloquear(){const f=this.$refs.portal;if(f){f.style.pointerEvents='none';f.inert=true;}},
    navegar(href){
        const url=new URL(href,window.location.href),pagina=this.paginas[url.pathname];url.searchParams.set('_editor','1');
        if(url.origin!==window.location.origin||!pagina)return;
        if(pagina.startsWith('http')){window.Livewire.navigate(pagina);return;}
        if(pagina!==this.wire.grupo){
            this.wire.$set('grupo',pagina,false);
            const direccion=new URL(window.location.href);
            if(pagina==='inicio')direccion.searchParams.delete('grupo');
            else direccion.searchParams.set('grupo',pagina);
            window.history.replaceState(window.history.state,'',direccion);
        }
        this.scope='portal:'+pagina;
        if(pagina==='catalogo'&&!url.searchParams.has('vista'))url.searchParams.set('vista','mapa');
        this.opciones=[];
        this.bloquear();
        this.$refs.portal.src=url.href;
    },
    cambiarVistaCatalogo(vista){
        if (this.scope !== 'portal:catalogo' || !['arbol','mapa','tarjetas','registros'].includes(vista)) return;
        const url = new URL(this.$refs.portal.src, window.location.href);
        if (url.searchParams.get('vista') === vista) return;
        url.searchParams.set('vista', vista);
        this.bloquear();
        this.$refs.portal.src = url.href;
    },
    iniciar(){
        const doc=this.$refs.portal.contentDocument;
        if(!doc?.body){this.mensaje='La vista no se pudo cargar. Recarga el editor.';return;}
        if(doc.body.dataset.portalEditorReady==='true'){this.$refs.portal.style.pointerEvents='auto';this.$refs.portal.inert=false;return;}
        window.prepararPortalEditorial(doc,this.mapa);
        doc.addEventListener('submit',e=>{e.preventDefault();e.stopImmediatePropagation();},true);
        doc.addEventListener('click',e=>{
            e.preventDefault();e.stopImmediatePropagation();clearTimeout(this._navegacion);
            const vista = e.target.closest('[data-vista-catalogo]')?.dataset.vistaCatalogo;
            if (vista && e.detail < 2) {
                this._navegacion = setTimeout(() => this.cambiarVistaCatalogo(vista), 350);
                return;
            }
            const arbol = doc.getElementById('explorador-taxonomico');
            if (arbol && e.detail < 2) {
                if (e.target.closest('button[aria-controls="explorador-taxonomico"]')) {arbol.showModal();return;}
                if (e.target.closest('.taxonomy-explorer-close')) {arbol.close();return;}
            }
            const enlace=e.target.closest('a[href]');
            if(enlace?.closest('nav')&&e.detail<2)this._navegacion=setTimeout(()=>this.navegar(enlace.href),350);
        },true);
        doc.addEventListener('dblclick',e=>{clearTimeout(this._navegacion);this.seleccionar(e);},true);
        doc.addEventListener('keydown',e=>{
            if(e.key!=='Enter')return;
            const vista = e.target.closest('[data-vista-catalogo]')?.dataset.vistaCatalogo;
            if (e.altKey && vista) {e.preventDefault();this.cambiarVistaCatalogo(vista);}
            else if(e.altKey&&e.target.closest('nav a[href]')){e.preventDefault();this.navegar(e.target.closest('nav a[href]').href);}
            else this.seleccionar(e);
        },true);
        doc.querySelectorAll?.('[data-portal-copy],h1,h2,h3,h4,p,label,summary').forEach(el=>{if(!el.closest('script,style,td,[data-copy-preserve]'))el.tabIndex=0;});
        doc.body.dataset.portalEditorReady='true';
        window.hubDisposicionContenido.aplicarGuardada(doc.body,this.scope);
        this.ajustarAltura();this.$refs.portal.style.pointerEvents='auto';this.$refs.portal.inert=false;
    },
    seleccionar(e){
        e.preventDefault();e.stopImmediatePropagation();
        const foto=e.target.closest('[data-editorial-foto]');
        if(foto){this.wire.seleccionarFotoEditorial(foto.dataset.editorialFoto,foto.alt);return;}
        if(e.target.closest('[data-editor-no-text]'))return;
        let el=e.target.closest('h1,h2,h3,h4,p,button,a,label,summary,span,dt,li')||e.target;
        if(el.closest('script,style,textarea,pre,code,td,[data-copy-preserve]'))return;
        let clave=el.closest('[data-portal-copy]')?.dataset.portalCopy;this.opciones=[];
        for(let candidato=el,n=0;candidato&&n<4;candidato=candidato.parentElement,n++){
            if(clave)break;
            const texto=textoEditorial(candidato),coincidencias=Object.keys(this.mapa).filter(k=>this.mapa[k].replace(/\s+/gu,' ').trim()===texto);
            if(coincidencias.length>1){this.opciones=coincidencias;this.$refs.opciones?.scrollIntoView({block:'center'});return;}
            if(coincidencias.length){clave=coincidencias[0];el=candidato;break;}
        }
        el.scrollIntoView({block:'nearest'});
        if(clave)this.editar(clave);
        else if(!el.closest('dd')){const texto=textoEditorial(el);if(texto&&texto.length<=5000)this.editar('literal:'+texto);}
    },
    editar(clave){
        if(this.publicando)return;
        this.opciones=[];
        this.errorSeleccion='';
        this.claveLocal=clave;
        this.originalLocal=clave.startsWith('literal:')?clave.slice(8):(this.originales[clave]??this.mapa[clave]??'');
        this.textoVisible=this.mapa[clave]??this.originalLocal;
        this.textoLocal=this.textoVisible;
        this.$flux?.modal('portal-texto-inmediato').show();
    },
    async publicarTexto(){
        if(!this.claveLocal||this.publicando)return;
        this.errorSeleccion='';
        this.mensajePublicacion='';
        this.publicando=true;
        try{
            const resultado=await this.wire.publicarTextoVisual(this.claveLocal,this.textoVisible,this.textoLocal);
            this.errorSeleccion=resultado?.error??'';
            if(resultado?.guardado){
                this.$flux?.modal('portal-texto-inmediato').close();
                this.mensajePublicacion='Transacción Efectuada';
            }
        }
        catch{this.errorSeleccion='No se pudo guardar el texto. Intenta de nuevo.';}
        finally{this.publicando=false;}
    },
    recargar(textoPublicado=false){
        this.bloquear();const clave=this.wire.claveSeleccionada;
        if(textoPublicado&&clave)this.mapa[clave.startsWith('editorial.')?'literal:'+this.wire.originalSeleccionado:clave]=this.wire.textoActual;
        if (this.$refs.portal) {
            const url = new URL(this.$refs.portal.src, window.location.href);
            url.searchParams.set('_editor', '1');
            url.searchParams.set('editor', Date.now());
            this.$refs.portal.src = url.href;
        }
    },
});
window.wizardVisualEditor = (wire,mapa,pasoInicial=0,posicionesPorPaso={},originales={},clavesPorPaso={}) => ({
    wire,mapa,clavesPorPaso,pasoActivo:pasoInicial,opciones:[],errorSeleccion:'',originalLocal:'',textoVisible:'',textoLocal:'',claveLocal:'',publicando:false,
    init(){this._resize=()=>this.ajustarAltura();window.addEventListener('resize',this._resize);},
    destroy(){clearTimeout(this._pasoPendiente);window.removeEventListener('resize',this._resize);},
    preparar(){
        const vista=this.$refs.vistaWizard;
        if(!vista)return;
        const fragmento=vista.querySelector('[data-wizard-fragmento]');
        if(fragmento&&fragmento!==this._fragmentoPreparado){
            window.hubDisposicionContenido?.aplicarGuardada(fragmento,'wizard:'+this.pasoActivo,posicionesPorPaso[this.pasoActivo]??{});
            this._fragmentoPreparado=fragmento;
        }
        vista.querySelectorAll('h2,h3,label,button,[data-flux-label],[data-flux-field][data-wizard-copy]').forEach(el=>el.tabIndex=0);
        this.ajustarAltura();
    },
    ajustarAltura(){const e=this.$refs.vistaWizard;if(e)e.style.setProperty('--hub-editor-altura',Math.max(220,window.innerHeight-e.getBoundingClientRect().top-88)+'px');},
    editar(clave){
        if(this.publicando)return;
        this.opciones=[];
        this.errorSeleccion='';
        this.claveLocal=clave;
        this.originalLocal=clave.startsWith('literal:')?clave.slice(8):(originales[clave]??this.mapa[clave]??'');
        this.textoVisible=this.mapa[clave]??this.originalLocal;
        this.textoLocal=this.textoVisible;
        this.$flux?.modal('wizard-texto-inmediato').show();
    },
    navegarPaso(evento){
        const paso=evento.target.closest('[data-wizard-paso]');
        if(!paso)return;
        clearTimeout(this._pasoPendiente);
        evento.preventDefault();evento.stopImmediatePropagation();
        const id=Number(paso.dataset.wizardPaso);
        if(evento.detail===0)this.activarPaso(id);
        if(evento.detail===1)this._pasoPendiente=setTimeout(()=>this.activarPaso(id),300);
    },
    activarPaso(id){
        if(this.pasoActivo===id||!Object.hasOwn(this.clavesPorPaso,id))return;
        this.opciones=[];
        this.pasoActivo=id;
        this.wire.$set('pasoEditor',id,false);
        this.$nextTick(()=>{
            if(this.$refs.vistaWizard)this.$refs.vistaWizard.scrollTop=0;
            this.preparar();
        });
    },
    editarPaso(evento){
        const paso=evento.target.closest('[data-wizard-paso]');
        if(!paso)return;
        clearTimeout(this._pasoPendiente);
        evento.preventDefault();evento.stopImmediatePropagation();
        this.editar(paso.dataset.wizardCopy);
    },
    clic(e){
        e.preventDefault();e.stopImmediatePropagation();
        const tarjeta=e.target.closest('[data-wizard-modalidad]');
        if(tarjeta&&e.detail<2&&tarjeta.dataset.wizardModalidad!==this.wire.modalidadEditor)this.wire.$set('modalidadEditor',tarjeta.dataset.wizardModalidad);
    },
    teclado(e){
        if((e.key==='Enter'||e.key===' ')&&e.target.closest('[data-wizard-modalidad]'))this.clic(e);
        else if(e.key==='Enter')this.seleccionar(e);
    },
    seleccionar(evento){
        evento.preventDefault();evento.stopImmediatePropagation();this.opciones=[];
        const clave=evento.target.closest('[data-wizard-copy]')?.dataset.wizardCopy;
        if(clave){this.editar(clave);return;}
        const campo=evento.target.closest('[data-flux-field]');
        let el=campo?.querySelector?.('label,[data-flux-label],[id$="-label"]')||evento.target.closest('h1,h2,h3,p,button,a,label,span,dt,li,[data-flux-label]');
        if(!el||el.closest('script,style,textarea,td,dd,[data-copy-preserve]'))return;
        const literal=textoEditorial(el);
        for(let n=0;el&&n<3;n++,el=el.parentElement){
            const texto=textoEditorial(el),claves=Object.keys(this.mapa).filter(k=>this.mapa[k].replace(/\s+/gu,' ').trim()===texto);
            if(claves.length>1){this.opciones=claves;return;}
            if(claves.length===1){this.editar(claves[0]);return;}
        }
        if(literal&&literal.length<=1000)this.editar('literal:'+literal);
    },
    async publicarTexto(){
        if(!this.claveLocal||this.publicando)return;
        this.errorSeleccion='';
        this.publicando=true;
        try{
            const resultado=await this.wire.publicarTextoVisual(this.claveLocal,this.textoVisible,this.textoLocal);
            if(!resultado?.guardado){this.errorSeleccion=resultado?.error||'No se pudo guardar el texto. Intenta de nuevo.';return;}
            this.mapa[this.claveLocal]=resultado.texto;
            this.$flux?.modal('wizard-texto-inmediato').close();
            this.$nextTick(()=>this.preparar());
        }
        catch{this.errorSeleccion='No se pudo guardar el texto. Intenta de nuevo.';}
        finally{this.publicando=false;}
    },
});
})();
