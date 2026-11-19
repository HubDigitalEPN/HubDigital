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
const seleccionEditorial = () => ({
    seleccionOcupada:false,errorSeleccion:'',originalLocal:'',textoLocal:'',claveLocal:'',publicando:false,seleccionNumero:0,
    cargarSeleccion(clave,modal){
        this.opciones=[];this.errorSeleccion='';this.seleccionOcupada=true;this.claveLocal=clave;
        this.originalLocal=clave.startsWith('literal:')?clave.slice(8):this.mapa[clave]||'';
        this.textoLocal=this.mapa[clave]||this.originalLocal;
        const numero=++this.seleccionNumero;
        this.$flux?.modal(modal).show();
        const respuesta=clave.startsWith('literal:')?this.wire.seleccionarLiteral(clave.slice(8)):this.wire.seleccionarTexto(clave);
        Promise.resolve(respuesta).then(dato=>{
            if(numero!==this.seleccionNumero)return;
            if(dato?.error){this.errorSeleccion=dato.error;return;}
            if(dato){this.originalLocal=dato.original;this.textoLocal=dato.texto;}
        }).catch(()=>{if(numero===this.seleccionNumero)this.errorSeleccion='No se pudo cargar el texto. Cierra y vuelve a seleccionarlo.';})
          .finally(()=>{if(numero===this.seleccionNumero)this.seleccionOcupada=false;});
    },
});
window.portalVisualEditor = (wire,mapa,ambito='portal:inicio',version='',posiciones={},paginas={}) => ({
    ...seleccionEditorial(),wire,mapa,paginas,seleccionado:null,opciones:[],mensaje:'',
    disposicion:window.editorDisposicionContenido(wire,ambito,version,posiciones),
    init(){this.$nextTick(()=>{this.bloquear();this.ajustarAltura();if(this.$refs.portal?.contentDocument?.readyState==='complete')this.iniciar();});this._resize=()=>this.ajustarAltura();window.addEventListener('resize',this._resize);},
    destroy(){window.removeEventListener('resize',this._resize);clearTimeout(this._navegacion);},
    ajustarAltura(){const f=this.$refs.portal;if(f?.getBoundingClientRect)f.style.setProperty('--hub-editor-altura',Math.max(220,window.innerHeight-f.getBoundingClientRect().top-88)+'px');},
    bloquear(){const f=this.$refs.portal;if(f){f.style.pointerEvents='none';f.inert=true;}},
    navegar(href){
        const url=new URL(href,window.location.href),pagina=this.paginas[url.pathname];url.searchParams.set('_editor','1');
        if(url.origin!==window.location.origin||!pagina)return;
        if(pagina.startsWith('http')){window.Livewire.navigate(pagina);return;}
        if(pagina!==this.wire.grupo)this.wire.$set('grupo',pagina);
        else {this.bloquear();this.$refs.portal.src=url.href;}
    },
    iniciar(){
        const doc=this.$refs.portal.contentDocument;
        if(!doc?.body){this.mensaje='La vista no se pudo cargar. Recarga el editor.';return;}
        if(doc.body.dataset.portalEditorReady==='true'){this.$refs.portal.style.pointerEvents='auto';this.$refs.portal.inert=false;return;}
        window.prepararPortalEditorial(doc,this.mapa);
        doc.addEventListener('submit',e=>{e.preventDefault();e.stopImmediatePropagation();},true);
        doc.addEventListener('click',e=>{
            if(e.target.closest('[data-disposicion-handle]'))return;
            e.preventDefault();e.stopImmediatePropagation();clearTimeout(this._navegacion);
            const enlace=e.target.closest('a[href]');
            if(enlace?.closest('nav')&&e.detail<2)this._navegacion=setTimeout(()=>this.navegar(enlace.href),350);
        },true);
        doc.addEventListener('dblclick',e=>{clearTimeout(this._navegacion);this.seleccionar(e);},true);
        doc.addEventListener('keydown',e=>{
            if(e.key!=='Enter'||e.target.closest('[data-disposicion-handle]'))return;
            if(e.altKey&&e.target.closest('nav a[href]')){e.preventDefault();this.navegar(e.target.closest('nav a[href]').href);}
            else this.seleccionar(e);
        },true);
        doc.querySelectorAll?.('[data-portal-copy],h1,h2,h3,h4,p,label,summary').forEach(el=>{if(!el.closest('script,style,td,[data-copy-preserve]'))el.tabIndex=0;});
        doc.body.dataset.portalEditorReady='true';
        this.disposicion.conectar(doc.body);
        this.ajustarAltura();this.$refs.portal.style.pointerEvents='auto';this.$refs.portal.inert=false;
    },
    seleccionar(e){
        if(e.target.closest('[data-disposicion-handle]'))return;
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
        this.seleccionado?.style.removeProperty('outline');this.seleccionado=el;
        el.style.outline='3px solid #1476c9';el.scrollIntoView({block:'nearest'});
        if(clave)this.editar(clave);
        else if(!el.closest('dd')){const texto=textoEditorial(el);if(texto&&texto.length<=5000)this.editar('literal:'+texto);}
    },
    editar(clave){this.cargarSeleccion(clave,'portal-texto-inmediato');},
    async publicarTexto(){
        if(this.seleccionOcupada||this.errorSeleccion||this.publicando)return;
        this.publicando=true;
        try{if(this.disposicion.pendiente&&!await this.disposicion.guardar())return;this.wire.$set('textoActual',this.textoLocal,false);await this.wire.guardarTexto();}
        finally{this.publicando=false;}
    },
    recargar(){
        this.bloquear();const clave=this.wire.claveSeleccionada;
        if(clave)this.mapa[clave.startsWith('editorial.')?'literal:'+this.wire.originalSeleccionado:clave]=this.wire.textoActual;
        if(this.$refs.portal)this.$refs.portal.src=this.$refs.portal.src.split('?')[0]+'?_editor=1&editor='+Date.now();
    },
});
window.wizardVisualEditor = (wire,mapa,ambito='wizard:0',version='',posiciones={}) => ({
    ...seleccionEditorial(),wire,mapa,opciones:[],
    disposicion:window.editorDisposicionContenido(wire,ambito,version,posiciones),
    init(){this.$nextTick(()=>this.preparar());this._resize=()=>this.ajustarAltura();window.addEventListener('resize',this._resize);},
    destroy(){window.removeEventListener('resize',this._resize);},
    preparar(){this.disposicion.conectar(this.$refs.vistaWizard.querySelector('[data-wizard-fragmento]'));this.$refs.vistaWizard.querySelectorAll('h2,h3,label,button,[data-flux-label]').forEach(el=>el.tabIndex=0);this.ajustarAltura();},
    ajustarAltura(){const e=this.$refs.vistaWizard;if(e)e.style.setProperty('--hub-editor-altura',Math.max(220,window.innerHeight-e.getBoundingClientRect().top-88)+'px');},
    editar(clave){this.cargarSeleccion(clave,'wizard-texto-inmediato');},
    clic(e){
        if(e.target.closest('[data-disposicion-handle]'))return;
        e.preventDefault();e.stopImmediatePropagation();
        const tarjeta=e.target.closest('[data-wizard-modalidad]');
        if(tarjeta&&e.detail<2&&tarjeta.dataset.wizardModalidad!==this.wire.modalidadEditor)this.wire.$set('modalidadEditor',tarjeta.dataset.wizardModalidad);
    },
    teclado(e){
        if(e.target.closest('[data-disposicion-handle]'))return;
        if((e.key==='Enter'||e.key===' ')&&e.target.closest('[data-wizard-modalidad]'))this.clic(e);
        else if(e.key==='Enter')this.seleccionar(e);
    },
    seleccionar(evento){
        if(evento.target.closest('[data-disposicion-handle]'))return;
        evento.preventDefault();evento.stopImmediatePropagation();this.opciones=[];
        const campo=evento.target.closest('[data-flux-field]');
        let el=campo?.querySelector?.('label,[id$="-label"]')||evento.target.closest('h1,h2,h3,p,button,a,label,span,dt,li');
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
        if(this.seleccionOcupada||this.errorSeleccion||this.publicando)return;
        this.publicando=true;
        try{if(this.disposicion.pendiente&&!await this.disposicion.guardar())return;this.wire.$set('textoActual',this.textoLocal,false);await this.wire.guardarSeleccion();this.$nextTick(()=>this.preparar());}
        finally{this.publicando=false;}
    },
});
})();
