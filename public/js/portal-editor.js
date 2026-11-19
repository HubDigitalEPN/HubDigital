window.portalVisualEditor = (wire, mapa, ambito='portal:inicio', version='', posiciones={}) => ({
    wire, mapa, seleccionado: null, opciones: [], mensaje: 'Haz clic sobre un texto de la página para editarlo en su ubicación.',
    disposicion: window.editorDisposicionContenido(wire,ambito,version,posiciones),
    seleccionOcupada:false,errorSeleccion:'',originalLocal:'',textoLocal:'',publicando:false,seleccionNumero:0,
    init(){this.$nextTick(()=>{this.bloquear();this.ajustarAltura();if(this.$refs.portal?.contentDocument?.readyState==='complete')this.iniciar();});this._resize=()=>this.ajustarAltura();window.addEventListener('resize',this._resize);},
    destroy(){window.removeEventListener('resize',this._resize);},
    ajustarAltura(){const f=this.$refs.portal;if(f?.getBoundingClientRect)f.style.setProperty('--hub-editor-altura',Math.max(220,window.innerHeight-f.getBoundingClientRect().top-28)+'px');},
    bloquear(){const f=this.$refs.portal;if(f){f.style.pointerEvents='none';f.inert=true;}},
    iniciar() {
        const doc = this.$refs.portal.contentDocument;
        if (!doc?.body) { this.mensaje='La vista no se pudo cargar. Recarga el editor.'; return; }
        if(doc.body.dataset.portalEditorReady==='true'){this.$refs.portal.style.pointerEvents='auto';this.$refs.portal.inert=false;return;}
        doc.addEventListener('submit', e=>{e.preventDefault();e.stopImmediatePropagation();}, true);
        doc.addEventListener('click', e=>{
            if(e.target.closest('[data-disposicion-handle]'))return;
            e.preventDefault(); e.stopImmediatePropagation();
            if(this.disposicion.activa)return;
            const normalizar=t=>(t||'').replace(/\s+/gu,' ').trim();
            let el=e.target.closest('h1,h2,h3,h4,p,button,a,label,summary,span,dt,li') || e.target;
            if(el.closest('script,style,textarea,pre,code,td,[data-copy-preserve]'))return;
            let clave=el.closest('[data-portal-copy]')?.dataset.portalCopy;
            this.opciones=[];
            for(let candidato=el,n=0;candidato && n<4;candidato=candidato.parentElement,n++) {
                if(clave)break;
                const texto=normalizar(candidato.textContent);
                const coincidencias=Object.keys(this.mapa).filter(k=>normalizar(this.mapa[k])===texto);
                if(coincidencias.length>1){this.opciones=coincidencias;this.mensaje='Este texto aparece en varias ubicaciones. Selecciona el campo que deseas cambiar en la lista superior.';this.$refs.opciones?.scrollIntoView({block:'center'});return;}
                if(coincidencias.length){clave=coincidencias[0];el=candidato;break;}
            }
            this.seleccionado?.style.removeProperty('outline');this.seleccionado=el;
            el.style.outline='3px solid #1476c9';el.scrollIntoView({block:'nearest'});
            if(clave)this.editar(clave);
            else {
                if(el.closest('dd'))return;
                const texto=normalizar(el.textContent);
                if(texto && texto.length<=5000)this.editar('literal:'+texto);
            }
        },true);
        doc.addEventListener('keydown', e=>{if(e.key==='Enter' && e.target.matches('a,button')){e.preventDefault();e.target.click();}},true);
        doc.body.dataset.portalEditorReady='true';
        this.disposicion.conectar(doc.querySelector?.('main')||doc.body);
        this.ajustarAltura();
        this.$refs.portal.style.pointerEvents='auto';this.$refs.portal.inert=false;
    },
    editar(clave){
        this.opciones=[];this.errorSeleccion='';this.seleccionOcupada=true;this.originalLocal=clave.startsWith('literal:')?clave.slice(8):this.mapa[clave]||'';this.textoLocal=this.mapa[clave]||this.originalLocal;
        const numero=++this.seleccionNumero;
        this.$flux?.modal('portal-texto-inmediato').show();
        const respuesta=clave.startsWith('literal:')?this.wire.seleccionarLiteral(clave.slice(8)):this.wire.seleccionarTexto(clave);
        Promise.resolve(respuesta).then(dato=>{if(numero!==this.seleccionNumero)return;if(dato?.error){this.errorSeleccion=dato.error;return;}if(dato){this.originalLocal=dato.original;this.textoLocal=dato.texto;}}).catch(()=>{if(numero===this.seleccionNumero)this.errorSeleccion='No se pudo cargar el texto. Cierra y vuelve a seleccionarlo.';}).finally(()=>{if(numero===this.seleccionNumero)this.seleccionOcupada=false;});
    },
    async publicarTexto(){if(this.seleccionOcupada||this.errorSeleccion||this.publicando)return;this.publicando=true;try{this.wire.$set('textoActual',this.textoLocal,false);await this.wire.guardarTexto();}finally{this.publicando=false;}},
    recargar(){
        this.bloquear();
        const clave=this.wire.claveSeleccionada;
        this.mapa[clave.startsWith('editorial.')?'literal:'+this.wire.originalSeleccionado:clave]=this.wire.textoActual;
        if(this.$refs.portal)this.$refs.portal.src=this.$refs.portal.src.split('?')[0]+'?editor='+Date.now();
    },
});

window.wizardVisualEditor = (wire, mapa, ambito='wizard:0',version='',posiciones={}) => ({
    wire, mapa, opciones: [],
    disposicion:window.editorDisposicionContenido(wire,ambito,version,posiciones),
    init(){this.$nextTick(()=>{this.disposicion.conectar(this.$refs.vistaWizard.querySelector('[data-wizard-fragmento]'));this.ajustarAltura();});this._resize=()=>this.ajustarAltura();window.addEventListener('resize',this._resize);},
    destroy(){window.removeEventListener('resize',this._resize);},
    ajustarAltura(){const e=this.$refs.vistaWizard;if(e)e.style.setProperty('--hub-editor-altura',Math.max(220,window.innerHeight-e.getBoundingClientRect().top-28)+'px');},
    editar(clave){this.opciones=[];if(clave.startsWith('literal:'))this.wire.seleccionarLiteral(clave.slice(8));else this.wire.seleccionarTexto(clave);},
    seleccionar(evento) {
        if(evento.target.closest('[data-disposicion-handle]'))return;
        evento.preventDefault();evento.stopImmediatePropagation();this.opciones=[];
        if(this.disposicion.activa)return;
        const normalizar=t=>(t||'').replace(/\s+/gu,' ').trim();
        const campo=evento.target.closest('[data-flux-field]');
        let el=campo?.querySelector?.('label,[id$="-label"]') || evento.target.closest('h1,h2,h3,p,button,a,label,span,dt,li');
        if(!el || el.closest('script,style,textarea,td,dd,[data-copy-preserve]'))return;
        const literal=normalizar(el.textContent);
        for(let n=0;el && n<3;n++,el=el.parentElement){
            const texto=normalizar(el.textContent);
            const claves=Object.keys(this.mapa).filter(k=>normalizar(this.mapa[k])===texto);
            if(claves.length>1){this.opciones=claves;return;}
            if(claves.length===1){this.editar(claves[0]);return;}
        }
        if(literal && literal.length<=1000)this.wire.seleccionarLiteral(literal);
    },
});
