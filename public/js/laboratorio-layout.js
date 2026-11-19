(() => {
    function ajustarAltura(canvas) {
        if (!canvas.isConnected || canvas.closest('[data-lab-editor-canvas]')) return;
        if (window.innerWidth <= 760) { canvas.style.height = 'auto'; return; }
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
    function iniciarPublico() {
        document.querySelectorAll('[data-lab-free]').forEach(canvas => {
            if (canvas.dataset.observed) return;
            canvas.dataset.observed = 'true';
            const observer = new ResizeObserver(() => ajustarAltura(canvas));
            [...canvas.children].forEach(item => observer.observe(item));
            canvas.addEventListener('toggle',()=>ajustarAltura(canvas),true);
            window.addEventListener('resize', () => ajustarAltura(canvas));
            ajustarAltura(canvas);
        });
    }
    document.addEventListener('DOMContentLoaded', iniciarPublico);
    document.addEventListener('livewire:navigated', iniciarPublico);

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

    window.laboratorioEditor = (wire, originales, catalogo, mapa={}, paginas={}) => ({
        posiciones: Object.assign({}, JSON.parse(JSON.stringify(originales))), catalogo, mapa, paginas, seleccion: '', mensaje: '', nodos: {}, wire,
        init(){this._resize=()=>this.ajustarAltura();window.addEventListener('resize',this._resize);this.$nextTick(()=>{const frame=this.$refs.vista;if(!frame)return;this._onload=()=>this.iniciar();frame.onload=this._onload;if(frame.contentDocument?.readyState==='complete'){if(frame.contentDocument.querySelector('.lab-page'))this.iniciar();else if(frame.contentDocument.querySelector('.lab-editor-canvas'))this.recargar();}});},
        destroy(){window.removeEventListener('resize',this._resize);clearTimeout(this._navegacion);if(this.$refs.vista?.onload===this._onload)this.$refs.vista.onload=null;},
        ajustarAltura(){const f=this.$refs.vista;if(f?.getBoundingClientRect)f.style.setProperty('--hub-editor-altura',Math.max(240,window.innerHeight-f.getBoundingClientRect().top-88)+'px');},
        iniciar() {
            const doc = this.$refs.vista.contentDocument;
            const article = doc?.querySelector('.lab-page');
            if(!article&&doc?.querySelector('.lab-editor-canvas')&&Object.keys(this.nodos).length){this.ajustarAltura();return;}
            if (!article) { this.mensaje = 'No se pudo cargar la vista. Recarga la página.'; return; }
            window.prepararPortalEditorial(doc,this.mapa);
            doc.addEventListener('submit',e=>{e.preventDefault();e.stopImmediatePropagation();},true);
            doc.addEventListener('click',e=>{e.preventDefault();e.stopImmediatePropagation();const a=e.target.closest('nav a[href]');if(a&&e.detail<2){clearTimeout(this._navegacion);this._navegacion=setTimeout(()=>{const u=new URL(a.href,window.location.href),destino=this.paginas[u.pathname];if(u.origin===window.location.origin&&destino)window.Livewire.navigate(destino);},350);}},true);
            const editar=e=>{clearTimeout(this._navegacion);if(e.target.closest('[data-lab-editor-canvas]'))return;e.preventDefault();e.stopImmediatePropagation();const foto=e.target.closest('[data-editorial-foto]'),texto=e.target.closest('[data-portal-copy]');if(foto)this.wire.seleccionarFotoEditorial(foto.dataset.editorialFoto,foto.alt);else if(texto&&!texto.closest('[data-editor-no-text]'))this.wire.seleccionarTextoNavegacion(texto.dataset.portalCopy);};
            doc.addEventListener('dblclick',editar,true);doc.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.target.closest('[data-disposicion-handle],[data-disposicion-resize]'))editar(e);},true);
            doc.querySelectorAll('[data-portal-copy],[data-editorial-foto]').forEach(el=>el.tabIndex=0);
            const rect = article.getBoundingClientRect(), width = rect.width;
            const elementos = [...article.querySelectorAll('[data-lab-element]')];
            elementos.forEach(el=>{const id=el.dataset.labElement;if(!this.catalogo[id])this.catalogo[id]={tipo:el.dataset.labKind||'texto',etiqueta:el.dataset.labLabel||(el.textContent.trim().slice(0,80)||id)};});
            const capturas = elementos.map(el => {
                const id = el.dataset.labElement, box = el.getBoundingClientRect(), css = doc.defaultView.getComputedStyle(el);
                const foto = this.catalogo[id].tipo === 'foto';
                const clone = el.classList.contains('lab-free-item') ? el.firstElementChild.cloneNode(true) : el.cloneNode(true);
                const desplegable=clone.classList.contains('lab-readable-copy');
                clone.removeAttribute('class'); clone.removeAttribute('data-lab-element'); clone.removeAttribute('style');
                if(desplegable)clone.classList.add('lab-readable-copy');
                clone.style.cssText = 'margin:0;width:100%;font:inherit;color:inherit;overflow-wrap:anywhere';
                if (foto) { clone.classList.add('lab-photo'); clone.style.height = '100%'; }
                const p = this.posiciones[id] ?? {x: Math.max(0,(box.left-rect.left)/width*100), y: Math.max(0,(box.top-rect.top)/width*100), ancho: box.width/width*100, alto: Math.max(1,box.height/width*100), fuente: Math.max(.5,Math.min(8,parseFloat(css.fontSize)/width*100)), capa:0};
                p.ancho = Math.max(5, Math.min(100-p.x,p.ancho));
                return {id, clone, css: {color:css.color,fontWeight:css.fontWeight,lineHeight:parseFloat(css.lineHeight)/parseFloat(css.fontSize)||1.4}, p};
            });
            const canvas = doc.createElement('div'); canvas.className = 'lab-free-canvas lab-editor-canvas'; canvas.dataset.labEditorCanvas = 'true';
            canvas.tabIndex=0;canvas.setAttribute('aria-label','Página editable. Doble clic en un espacio vacío o Intro para añadir contenido.');
            canvas.addEventListener('dblclick',event=>{if(event.target!==canvas)return;const box=canvas.getBoundingClientRect();this.crearBloque(Math.max(0,Math.min(95,(event.clientX-box.left)/width*100)),Math.max(0,Math.min(4500,(event.clientY-box.top)/width*100)));});
            canvas.addEventListener('keydown',event=>{if(event.target!==canvas||event.key!=='Enter')return;event.preventDefault();this.crearBloque(4,Math.min(4500,Math.max(0,...Object.values(this.posiciones).map(p=>p.y+p.alto))+3));});
            article.replaceWith(canvas); this.nodos = {};
            canvas.addEventListener('click',event=>{if(event.target.closest('a'))event.preventDefault();});
            canvas.addEventListener('toggle',()=>this.pintar(),true);
            for (const {id,clone,css,p} of capturas) {
                this.posiciones[id] = p;
                const item = doc.createElement('div'); item.className = 'lab-free-item lab-editor-item'; item.dataset.elemento = id;
                item.tabIndex = 0; item.setAttribute('role','group'); item.setAttribute('aria-label',this.catalogo[id].etiqueta);
                item.style.color = css.color; item.style.fontWeight = css.fontWeight; item.style.lineHeight = css.lineHeight;
                item.append(clone);
                item.dataset.disposicionEditable='true';
                const asa=doc.createElement('button');asa.type='button';asa.dataset.disposicionHandle=id;asa.textContent='⠿';asa.setAttribute('aria-label','Mover: '+this.catalogo[id].etiqueta);item.append(asa);
                item.addEventListener('pointerdown', event => {
                    this.elegir(id);
                    if (event.target.closest('summary')) return;
                    event.preventDefault(); item.setPointerCapture(event.pointerId);
                    const start = {x:event.clientX,y:event.clientY,scroll:doc.defaultView.scrollY,p:{...this.posiciones[id]}};
                    const mover = e => {
                        const dx = (e.clientX-start.x)/width*100, dy = (e.clientY-start.y+doc.defaultView.scrollY-start.scroll)/width*100;
                        const actual = this.posiciones[id];
                        { actual.x=Math.max(0,Math.min(100-actual.ancho,start.p.x+dx));actual.y=Math.max(0,Math.min(5000,start.p.y+dy)); }
                        this.pintar();
                    };
                    const terminar = () => {item.removeEventListener('pointermove',mover);item.removeEventListener('pointerup',terminar);item.removeEventListener('pointercancel',terminar);this.sincronizar();};
                    item.addEventListener('pointermove',mover);item.addEventListener('pointerup',terminar);item.addEventListener('pointercancel',terminar);
                });
                item.addEventListener('focus',()=>this.elegir(id));
                item.addEventListener('dblclick',event=>{if(event.target.closest('[data-disposicion-handle]'))return;if(id.startsWith('texto-'))this.wire.seleccionarElemento(id);else if(id.startsWith('foto-'))this.wire.seleccionarFoto(id);});
                item.addEventListener('keydown',event=>{
                    if(event.key==='Enter'&&event.target===asa){event.preventDefault();return;}
                    if(event.key==='Enter'){event.preventDefault();event.stopPropagation();if(id.startsWith('foto-'))this.wire.seleccionarFoto(id);else this.wire.seleccionarElemento(id);return;}
                    const delta=event.shiftKey?1:.1,p=this.posiciones[id];
                    if(!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key))return;
                    event.preventDefault();if(event.key==='ArrowLeft')p.x-=delta;if(event.key==='ArrowRight')p.x+=delta;if(event.key==='ArrowUp')p.y-=delta;if(event.key==='ArrowDown')p.y+=delta;
                    p.x=Math.max(0,Math.min(100-p.ancho,p.x));p.y=Math.max(0,Math.min(5000,p.y));this.pintar();this.sincronizar();
                });
                canvas.append(item);this.nodos[id]=item;
            }
            this.pintar();
            this.mensaje = '';this.ajustarAltura();this.$refs.vista.style.pointerEvents='auto';this.$refs.vista.inert=false;
        },
        elegir(id) { this.seleccion=id;Object.entries(this.nodos).forEach(([key,node])=>node.classList.toggle('lab-editor-selected',key===id)); },
        pintar() {
            let bottom=0;
            for(const [id,node] of Object.entries(this.nodos)) {
                const p=this.posiciones[id];if(!p)continue;
                Object.assign(node.style,{left:`${p.x}%`,top:`${p.y}cqw`,width:`${p.ancho}%`,fontSize:`${p.fuente}cqw`,zIndex:p.capa});
                if(this.catalogo[id].tipo==='foto')node.style.height=`${p.alto}cqw`;
                bottom=Math.max(bottom,node.offsetTop+node.offsetHeight);
            }
            const canvas=this.$refs.vista.contentDocument?.querySelector('.lab-editor-canvas');if(canvas)canvas.style.height=`${Math.ceil(bottom+250)}px`;
        },
        sincronizar() {this.wire.set('posiciones',JSON.parse(JSON.stringify(this.posiciones)),false);this.mensaje='Posición ajustada. Pulsa «Guardar y publicar» para aplicarla.';},
        crearBloque(x,y){this.wire.set('posiciones',JSON.parse(JSON.stringify(this.posiciones)),false);this.wire.crearBloqueVisual(x,y);},
        cambiar() {
            const p=this.posiciones[this.seleccion];if(!p)return;
            for(const key of ['x','y','ancho','alto','fuente','capa'])p[key]=Number(p[key])||0;
            p.ancho=Math.max(5,Math.min(100,p.ancho));p.x=Math.max(0,Math.min(100-p.ancho,p.x));p.y=Math.max(0,Math.min(5000,p.y));p.alto=Math.max(1,Math.min(500,p.alto));p.fuente=Math.max(.5,Math.min(8,p.fuente));p.capa=Math.max(0,Math.min(20,Math.round(p.capa)));
            this.pintar();this.sincronizar();
        },
        restaurar() {this.posiciones={};this.wire.set('posiciones',{},false);this.mensaje='Se restaurará el diseño original al guardar.';},
        retirarBloque(id){for(const key of ['texto-bloque-'+id+'-titulo','texto-bloque-'+id+'-texto','foto-bloque-'+id]){this.nodos[key]?.remove();delete this.nodos[key];delete this.posiciones[key];}this.pintar();this.sincronizar();},
        recargar() {this.posiciones=Object.assign({},JSON.parse(JSON.stringify(this.wire.posiciones)));this.$refs.vista.style.pointerEvents='none';this.$refs.vista.inert=true;this.$refs.vista.src=this.$refs.vista.src.split('?')[0]+'?_editor=1&edicion='+Date.now();},
    });
})();
