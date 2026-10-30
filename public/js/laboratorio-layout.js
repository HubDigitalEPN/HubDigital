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

    window.laboratorioEditor = (wire, originales, catalogo) => ({
        posiciones: Object.assign({}, JSON.parse(JSON.stringify(originales))), catalogo, seleccion: '', mensaje: 'Selecciona y arrastra un texto o una fotografía.', nodos: {}, wire,
        iniciar() {
            const doc = this.$refs.vista.contentDocument;
            const article = doc?.querySelector('.lab-page');
            if (!article) { this.mensaje = 'No se pudo cargar la vista. Recarga la página.'; return; }
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
            doc.body.replaceChildren(canvas); doc.body.style.cssText = 'margin:0;background:white'; this.nodos = {};
            canvas.addEventListener('click',event=>{if(event.target.closest('a'))event.preventDefault();});
            canvas.addEventListener('toggle',()=>this.pintar(),true);
            for (const {id,clone,css,p} of capturas) {
                this.posiciones[id] = p;
                const item = doc.createElement('div'); item.className = 'lab-free-item lab-editor-item'; item.dataset.elemento = id;
                item.tabIndex = 0; item.setAttribute('role','group'); item.setAttribute('aria-label',this.catalogo[id].etiqueta);
                item.style.color = css.color; item.style.fontWeight = css.fontWeight; item.style.lineHeight = css.lineHeight;
                item.append(clone);
                const resize = doc.createElement('button'); resize.type = 'button'; resize.className = 'lab-editor-resize'; resize.setAttribute('aria-label','Redimensionar '+this.catalogo[id].etiqueta); item.append(resize);
                item.addEventListener('pointerdown', event => {
                    this.elegir(id);
                    if (event.target.closest('summary')) return;
                    event.preventDefault(); item.setPointerCapture(event.pointerId);
                    const start = {x:event.clientX,y:event.clientY,scroll:doc.defaultView.scrollY,p:{...this.posiciones[id]}};
                    const redimensionar = event.target === resize;
                    const mover = e => {
                        const dx = (e.clientX-start.x)/width*100, dy = (e.clientY-start.y+doc.defaultView.scrollY-start.scroll)/width*100;
                        const actual = this.posiciones[id];
                        if (redimensionar) { actual.ancho=Math.max(5,Math.min(100-actual.x,start.p.ancho+dx)); if(this.catalogo[id].tipo==='foto')actual.alto=Math.max(1,Math.min(500,start.p.alto+dy)); }
                        else { actual.x=Math.max(0,Math.min(100-actual.ancho,start.p.x+dx));actual.y=Math.max(0,Math.min(5000,start.p.y+dy)); }
                        this.pintar();
                    };
                    const terminar = () => {item.removeEventListener('pointermove',mover);item.removeEventListener('pointerup',terminar);item.removeEventListener('pointercancel',terminar);this.sincronizar();};
                    item.addEventListener('pointermove',mover);item.addEventListener('pointerup',terminar);item.addEventListener('pointercancel',terminar);
                });
                item.addEventListener('focus',()=>this.elegir(id));
                item.addEventListener('keydown',event=>{
                    const delta=event.shiftKey?1:.1,p=this.posiciones[id];
                    if(!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key))return;
                    event.preventDefault();if(event.key==='ArrowLeft')p.x-=delta;if(event.key==='ArrowRight')p.x+=delta;if(event.key==='ArrowUp')p.y-=delta;if(event.key==='ArrowDown')p.y+=delta;
                    p.x=Math.max(0,Math.min(100-p.ancho,p.x));p.y=Math.max(0,Math.min(5000,p.y));this.pintar();this.sincronizar();
                });
                canvas.append(item);this.nodos[id]=item;
            }
            this.pintar();
            this.mensaje = 'Arrastra para mover. Usa el tirador inferior para cambiar el tamaño. Las flechas ajustan la posición con precisión.';
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
            const canvas=this.$refs.vista.contentDocument?.querySelector('.lab-editor-canvas');if(canvas)canvas.style.height=`${Math.ceil(bottom+100)}px`;
        },
        sincronizar() {this.wire.set('posiciones',JSON.parse(JSON.stringify(this.posiciones)),false);this.mensaje='Posición ajustada. Pulsa «Guardar y publicar» para aplicarla.';},
        cambiar() {
            const p=this.posiciones[this.seleccion];if(!p)return;
            for(const key of ['x','y','ancho','alto','fuente','capa'])p[key]=Number(p[key])||0;
            p.ancho=Math.max(5,Math.min(100,p.ancho));p.x=Math.max(0,Math.min(100-p.ancho,p.x));p.y=Math.max(0,Math.min(5000,p.y));p.alto=Math.max(1,Math.min(500,p.alto));p.fuente=Math.max(.5,Math.min(8,p.fuente));p.capa=Math.max(0,Math.min(20,Math.round(p.capa)));
            this.pintar();this.sincronizar();
        },
        restaurar() {this.posiciones={};this.wire.set('posiciones',{},false);this.mensaje='Se restaurará el diseño original al guardar.';},
        recargar() {this.posiciones=Object.assign({},JSON.parse(JSON.stringify(this.wire.posiciones)));this.$refs.vista.src=this.$refs.vista.src.split('?')[0]+'?edicion='+Date.now();},
    });
})();
