(() => {
    function ajustarAltura(canvas) {
        if (!canvas.isConnected) return;
        if (canvas.ownerDocument.defaultView.innerWidth <= 760) { canvas.style.height = 'auto'; return; }
        const items=[...canvas.children], expansiones=[], width=canvas.clientWidth;
        items.filter(item=>!item.hasAttribute('data-lab-auto')).sort((a,b)=>Number(a.dataset.labY)-Number(b.dataset.labY)).forEach(item=>{
            const base=Number(item.dataset.labY)*width/100;
            const desplazamiento=expansiones.filter(e=>e.desde<=base+1).reduce((total,e)=>total+e.delta,0);
            item.style.top=`${base+desplazamiento}px`;
            if(!item.querySelector('details[open]'))item.labClosedHeight=item.offsetHeight;
            const delta=item.offsetHeight-(item.labClosedHeight||item.offsetHeight);
            if(delta>0)expansiones.push({desde:base+item.labClosedHeight,delta});
        });
        let siguiente=Math.max(0,...items.filter(item=>!item.hasAttribute('data-lab-auto')).map(item=>item.offsetTop+item.offsetHeight))+32;
        items.filter(item=>item.hasAttribute('data-lab-auto')).forEach(item=>{item.style.top=`${siguiente}px`;siguiente+=item.offsetHeight+24;});
        const top = canvas.getBoundingClientRect().top;
        const bottom = Math.max(0, ...[...canvas.children].map(item => item.getBoundingClientRect().bottom - top));
        canvas.style.height = `${Math.ceil(bottom + 32)}px`;
    }
    function observarLienzos(doc) {
        doc.querySelectorAll('[data-lab-free]').forEach(canvas => {
            if (canvas.dataset.observed) return;
            canvas.dataset.observed = 'true';
            const observer = new doc.defaultView.ResizeObserver(() => ajustarAltura(canvas));
            [...canvas.children].forEach(item => observer.observe(item));
            canvas.addEventListener('toggle',()=>ajustarAltura(canvas),true);
            doc.defaultView.addEventListener('resize', () => ajustarAltura(canvas));
            ajustarAltura(canvas);
        });
    }
    document.addEventListener('DOMContentLoaded', () => observarLienzos(document));
    document.addEventListener('livewire:navigated', () => observarLienzos(document));

    document.addEventListener('click', event => {
        const abrir = event.target.closest('[data-lab-open-profile]');
        if (abrir) {
            const modal = document.getElementById(abrir.dataset.labOpenProfile);
            if (modal && !modal.open) { modal.__labOpener = abrir; modal.showModal(); }
            return;
        }
        const cerrar = event.target.closest('[data-lab-close-profile]');
        if (cerrar) { cerrar.closest('dialog')?.close(); return; }
        const modal = event.target;
        if (!modal.matches?.('dialog[data-lab-profile-dialog][open]')) return;
        const rect = modal.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) modal.close();
    });
    document.addEventListener('close', event => {
        if (event.target.matches?.('[data-lab-profile-dialog]')) event.target.__labOpener?.focus({preventScroll:true});
    }, true);

    window.laboratorioEditor = (wire, mapa={}, paginas={}) => ({
        wire, mapa, paginas, mensaje: '',
        campoTexto: '', textoLocal: '', complemento: null, errorTexto: '', publicandoTexto: false,
        init(){this._resize=()=>this.ajustarAltura();window.addEventListener('resize',this._resize);this.$nextTick(()=>{const frame=this.$refs.vista;if(!frame)return;this._onload=()=>this.iniciar();frame.onload=this._onload;if(frame.contentDocument?.readyState==='complete')this.iniciar();});},
        destroy(){window.removeEventListener('resize',this._resize);clearTimeout(this._navegacion);if(this.$refs.vista?.onload===this._onload)this.$refs.vista.onload=null;},
        ajustarAltura(){const f=this.$refs.vista;if(f?.getBoundingClientRect)f.style.setProperty('--hub-editor-altura',Math.max(240,window.innerHeight-f.getBoundingClientRect().top-88)+'px');},
        iniciar() {
            const frame = this.$refs.vista;
            const doc = frame.contentDocument;
            const article = doc?.querySelector('.lab-page');
            if (!article) { this.mensaje = 'No se pudo cargar la vista. Recarga la página.'; return; }
            if (article.dataset.labEditorReady === 'true') return;
            window.prepararPortalEditorial(doc, this.mapa);
            doc.addEventListener('submit', e => {e.preventDefault();e.stopImmediatePropagation();}, true);
            doc.addEventListener('click', e => {
                if (e.target.closest('summary')) return;
                e.preventDefault();e.stopImmediatePropagation();clearTimeout(this._navegacion);
                const enlace = e.target.closest('nav a[href]');
                if (enlace && e.detail < 2) this._navegacion = setTimeout(() => {
                    const url = new URL(enlace.href, window.location.href), destino = this.paginas[url.pathname];
                    if (url.origin === window.location.origin && destino) window.Livewire.navigate(destino);
                }, 350);
            }, true);
            const editar = e => {
                clearTimeout(this._navegacion);
                const elemento = e.target.closest('[data-lab-element]')?.dataset.labElement;
                const foto = e.target.closest('[data-editorial-foto]');
                const texto = e.target.closest('[data-portal-copy]');
                if (!elemento && !foto && (!texto || texto.closest('[data-editor-no-text]'))) return;
                e.preventDefault();e.stopImmediatePropagation();
                if (elemento?.startsWith('foto-')) this.wire.seleccionarFoto(elemento);
                else if (elemento?.startsWith('texto-')) this.editarTexto(elemento);
                else if (foto) this.wire.seleccionarFotoEditorial(foto.dataset.editorialFoto, foto.alt);
                else if (texto) this.wire.seleccionarTextoNavegacion(texto.dataset.portalCopy);
            };
            doc.addEventListener('dblclick', editar, true);
            doc.addEventListener('keydown', e => {if (e.key === 'Enter') editar(e);}, true);
            doc.querySelectorAll('[data-lab-element],[data-portal-copy],[data-editorial-foto]').forEach(el => el.tabIndex=0);
            observarLienzos(doc);
            article.dataset.labEditorReady = 'true';
            this.mensaje = '';this.ajustarAltura();frame.style.pointerEvents='auto';frame.inert=false;
        },
        editarTexto(elemento) {
            if (elemento.startsWith('texto-bloque-')) { this.wire.seleccionarElemento(elemento); return; }
            if (this.publicandoTexto) return;
            const campo = elemento === 'texto-personal' ? 'personal_titulo' : elemento.slice(6);
            if (!Object.hasOwn(this.wire.textos, campo)) return;
            const camposComplementarios = {personal_titulo:'personal_destacado', adrian_bio:'adrian_especialidad', contacto_accion:'contacto_email'};
            this.campoTexto = campo;
            this.textoLocal = this.wire.textos[campo];
            this.complemento = this.wire.textos[camposComplementarios[campo]] ?? null;
            this.errorTexto = '';
            this.$flux.modal('laboratorio-texto-inmediato').show();
        },
        async publicarTexto() {
            if (!this.campoTexto || this.publicandoTexto) return;
            this.publicandoTexto = true;
            this.errorTexto = '';
            try {
                const resultado = await this.wire.publicarTextoVisual(this.campoTexto, this.textoLocal, this.complemento);
                if (resultado?.guardado) this.$flux.modal('laboratorio-texto-inmediato').close();
                else this.errorTexto = resultado?.error || 'No se pudo publicar el texto.';
            } catch {
                this.errorTexto = 'No se pudo publicar el texto. Intenta de nuevo.';
            } finally {
                this.publicandoTexto = false;
            }
        },
        retirarBloque(id) {
            const doc = this.$refs.vista.contentDocument;
            if (!doc) return;
            const elementos = ['texto-bloque-'+id+'-titulo', 'texto-bloque-'+id+'-texto', 'foto-bloque-'+id];
            for (const nodo of doc.querySelectorAll('[data-lab-block],[data-lab-element]')) {
                if (nodo.dataset.labBlock === id || elementos.includes(nodo.dataset.labElement)) nodo.remove();
            }
            doc.querySelectorAll('[data-lab-free]').forEach(ajustarAltura);
        },
        recargar() {
            const frame = this.$refs.vista;
            frame.style.pointerEvents='none';frame.inert=true;
            const url = new URL(frame.src);
            url.searchParams.set('edicion', Date.now());
            frame.src = url.href;
        },
    });
})();
