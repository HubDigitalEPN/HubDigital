export function alturaDisponibleRegistros(rect, clientWidth, viewportHeight) {
    const scale=rect.width/clientWidth || 1;
    return Math.max(0,(viewportHeight-Math.max(0,rect.top))/scale);
}
export function crearRegistrosInfinitos() {
    return {
        raiz:null,tabla:null,observador:null,cambios:null,cuadro:null,solicitando:false,errorCarga:'',seleccionActual:'',
        init() {
            this.raiz=this.$el;
            this.alCambiar=()=>{cancelAnimationFrame(this.cuadro);this.cuadro=requestAnimationFrame(()=>this.ajustar());};
            this.alDesplazar=()=>this.comprobarCarga();
            this.observador=new ResizeObserver(this.alCambiar);this.observador.observe(this.raiz);
            window.addEventListener('resize',this.alCambiar);window.visualViewport?.addEventListener('resize',this.alCambiar);
            this.cambios=new MutationObserver(this.alCambiar);this.cambios.observe(this.raiz.parentElement,{childList:true,subtree:true});
            this.$nextTick(()=>{this.tabla=this.raiz.querySelector('.atlas-record-table-scroll');this.tabla?.addEventListener('scroll',this.alDesplazar,{passive:true});this.alCambiar();});
        },
        ajustar() {
            if (!this.raiz.isConnected) return;
            const height=alturaDisponibleRegistros(this.raiz.getBoundingClientRect(),this.raiz.clientWidth,window.visualViewport?.height || window.innerHeight);
            if (this.raiz.style.height!==`${height}px`) this.raiz.style.height=`${height}px`;
            if (this.seleccionActual!==this.raiz.dataset.seleccion) {this.seleccionActual=this.raiz.dataset.seleccion;this.errorCarga='';if (this.tabla) this.tabla.scrollTop=0;}
            this.comprobarCarga();
        },
        comprobarCarga() {
            if (!this.tabla || this.tabla.clientHeight<1 || this.errorCarga) return;
            if (this.tabla.scrollHeight-this.tabla.scrollTop-this.tabla.clientHeight<160) this.cargarMas();
        },
        async cargarMas() {
            if (this.solicitando || !this.raiz.isConnected || this.raiz.dataset.hayMas!=='true') return;
            this.solicitando=true;this.errorCarga='';
            const {seleccion,paginas}=this.raiz.dataset,position={top:this.tabla?.scrollTop || 0,left:this.tabla?.scrollLeft || 0};let timer;
            try {
                await Promise.race([this.$wire.cargarMasRegistros(seleccion,Number(paginas)),new Promise((_,reject)=>{timer=setTimeout(()=>reject(Error('Tiempo agotado')),45000);})]);
                await this.$nextTick();await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
                if (this.raiz.isConnected && this.raiz.dataset.seleccion===seleccion && this.tabla) {this.tabla.scrollTop=position.top;this.tabla.scrollLeft=position.left;}
            } catch {if (this.raiz.dataset.seleccion===seleccion) this.errorCarga='No se pudieron cargar más registros. Puedes reintentar.';}
            finally {clearTimeout(timer);this.solicitando=false;}
        },
        destroy() {
            cancelAnimationFrame(this.cuadro);this.observador?.disconnect();this.cambios?.disconnect();this.tabla?.removeEventListener('scroll',this.alDesplazar);
            window.removeEventListener('resize',this.alCambiar);window.visualViewport?.removeEventListener('resize',this.alCambiar);
        },
    };
}
