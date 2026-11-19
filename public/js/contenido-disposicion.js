(() => {
    if (window.hubDisposicionContenido) return;
    const hash = s => {let a=2166136261,b=3335557771;for(const c of s){a=Math.imul(a^c.charCodeAt(0),16777619);b=Math.imul(b^c.charCodeAt(0),2246822519);}return [a,b].map(n=>(n>>>0).toString(16).padStart(8,'0')).join('');};
    const path = (el,root) => {const p=[];for(let n=el;n&&n!==root;n=n.parentElement)p.unshift(n.tagName+':'+[...n.parentElement.children].indexOf(n));return p.join('/');};
    const model = el => {
        if(el.dataset?.disposicionCampo)return 'dato:'+el.dataset.disposicionCampo;
        for(const n of [el,...el.querySelectorAll('input,select,textarea,[data-disposicion-model]')]){
            const a=[...n.attributes].find(a=>a.name.startsWith('wire:model')||a.name==='data-disposicion-model');if(a)return a.value;
        }
        return el.matches('.hub-lov-field') ? el.querySelector('[id^="lov-"]')?.id : el.querySelector('.hub-lov-field [id^="lov-"]')?.id;
    };
    const omitted = 'script,style,template,dialog,ui-modal,nav,header,footer,svg,[data-copy-preserve],[data-interactive-grid],[data-grid-frame],.leaflet-container,[data-disposicion-handle]';
    const collect = (root, wizard=false) => {
        const groups=[];
        for(const parent of [root,...root.querySelectorAll('div,section,article,main,dl')]){
            if(parent.closest(omitted))continue;
            const display=getComputedStyle(parent).display;
            let items=[...parent.children].filter(el=>!el.matches(omitted)&&!el.hasAttribute('hidden')&&!el.querySelector('table,canvas,.leaflet-container'));
            if(wizard){
                if(!['grid','flex'].includes(display))continue;
                items=items.filter(el=>model(el));
            }else{
                if(parent.closest('form,[wire\\:id]') || !(display==='grid'||display==='flex'||parent.matches('main,section,article,.portal-container,.max-w-2xl')))continue;
                items=items.filter(el=>el.matches('h1,h2,h3,h4,p,figure,img,article,section,dl,[data-portal-copy],.portal-responsive-actions,.portal-hero-copy')||el.querySelector(':scope > [data-portal-copy]'));
            }
            if(items.length<2)continue;
            for(const el of items)el.dataset.disposicionId ||= 'i-'+hash(wizard?model(el):path(el,root));
            const id='g-'+hash(items.map(el=>el.dataset.disposicionId).sort().join('|'));
            if(new Set(items.map(el=>el.dataset.disposicionId)).size===items.length)groups.push({id,parent,items,wizard});
        }
        return groups;
    };
    const arrange = (group, order=[]) => {
        // Cargo compensaba el hueco de Identificación en el diseño original.
        // Esa compensación no corresponde a una distribución reorganizada.
        if(group.wizard)for(const el of group.items)if(el.matches?.('.hub-request-cell--cargo'))el.style.setProperty('--hub-cargo-expansion','0rem');
        const lookup=new Map(group.items.map(el=>[el.dataset.disposicionId,el]));
        const sorted=[...new Set(order)].map(id=>lookup.get(id)).filter(Boolean);
        sorted.push(...group.items.filter(el=>!sorted.includes(el)));
        const current=[...group.parent.children].filter(el=>group.items.includes(el));
        if(current.every((el,i)=>sorted[i]===el))return;
        const slots=current.map(el=>{const slot=el.ownerDocument.createComment('posición editorial');el.before(slot);return slot;});
        slots.forEach((slot,i)=>slot.replaceWith(sorted[i]));
    };
    const configuration = () => {try{return JSON.parse(document.getElementById('disposicion-contenido-datos')?.textContent||'{}');}catch{return {};}};
    const apply = root => {
        // El editor usa su versión viva de Livewire; el JSON del documento
        // público solo se aplica a los formularios y portales operativos.
        if(root.closest('[data-contenidos-textos]'))return;
        const scope=root.dataset.disposicionScope;if(!scope)return;
        const data=configuration()[scope.replace(':','.')];if(!data||Array.isArray(data))return;
        for(const g of collect(root,scope.startsWith('wizard:')))if(data[g.id])arrange(g,data[g.id]);
    };
    const start = () => {
        const page=document.documentElement.dataset.disposicionPortal;
        const main=document.querySelector('main');
        if(page&&main){main.dataset.disposicionScope='portal:'+page;apply(main);}
        document.querySelectorAll('[data-disposicion-scope]').forEach(apply);
    };
    window.hubDisposicionContenido={hash,collect,arrange,apply};
    window.editorDisposicionContenido=(wire,scope,version,published={})=>({
        wire,scope,version,published,activa:false,pendiente:false,guardando:false,mensaje:'',grupos:[],root:null,
        conectar(root){this.root=root;if(!root)return;for(const g of collect(root,this.scope.startsWith('wizard:')))if(this.published[g.id])arrange(g,this.published[g.id]);if(this.activa)this.habilitar();},
        activar(){this.activa=!this.activa;this.mensaje=this.activa?'Arrastra un asa hasta otro objeto del mismo bloque. También puedes enfocar el asa y usar las flechas.':'';if(this.activa)this.habilitar();else this.quitarAsas();},
        quitarAsas(){this.root?.querySelectorAll('[data-disposicion-handle]').forEach(el=>el.remove());this.root?.querySelectorAll('[data-disposicion-editable]').forEach(el=>{el.removeAttribute('data-disposicion-editable');el.classList.remove('hub-disposicion-destino');});},
        habilitar(){
            if(!this.root)return;this.quitarAsas();this.grupos=collect(this.root,this.scope.startsWith('wizard:'));
            for(const g of this.grupos)for(const el of g.items){
                el.dataset.disposicionEditable='true';
                const asa=el.ownerDocument.createElement('button');asa.type='button';asa.draggable=true;asa.dataset.disposicionHandle=el.dataset.disposicionId;
                const nombre=(el.querySelector('label')?.textContent||el.getAttribute('alt')||el.textContent||'Objeto').replace(/\s+/g,' ').trim().slice(0,75);
                asa.textContent='⠿';asa.title='Mover: '+nombre;asa.setAttribute('aria-label','Mover: '+nombre);
                asa.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();});
                asa.addEventListener('dragstart',e=>{e.stopPropagation();this.arrastrado={g,id:el.dataset.disposicionId};e.dataTransfer.setData('text/plain',el.dataset.disposicionId);e.dataTransfer.effectAllowed='move';});
                asa.addEventListener('dragend',()=>{this.arrastrado=null;this.root.querySelectorAll('.hub-disposicion-destino').forEach(n=>n.classList.remove('hub-disposicion-destino'));});
                asa.addEventListener('keydown',e=>{if(!['ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].includes(e.key))return;e.preventDefault();e.stopPropagation();const items=[...g.parent.children].filter(n=>g.items.includes(n)),i=items.indexOf(el),j=i+(['ArrowUp','ArrowLeft'].includes(e.key)?-1:1);if(items[j]){[items[i],items[j]]=[items[j],items[i]];arrange(g,items.map(n=>n.dataset.disposicionId));this.pendiente=true;asa.focus();}});
                el.prepend(asa);
                if(!el.__hubLayoutDrop){
                    el.__hubLayoutDrop=true;
                    el.addEventListener('dragover',e=>{if(this.arrastrado?.g.id===g.id&&this.activa){e.preventDefault();el.classList.add('hub-disposicion-destino');}});
                    el.addEventListener('dragleave',()=>el.classList.remove('hub-disposicion-destino'));
                    el.addEventListener('drop',e=>{if(this.arrastrado?.g.id!==g.id||!this.activa)return;e.preventDefault();e.stopPropagation();const ids=[...g.parent.children].filter(n=>g.items.includes(n)).map(n=>n.dataset.disposicionId),from=ids.indexOf(this.arrastrado.id),to=ids.indexOf(el.dataset.disposicionId);ids.splice(to,0,...ids.splice(from,1));arrange(g,ids);el.classList.remove('hub-disposicion-destino');this.pendiente=true;});
                }
            }
            if(!this.grupos.length)this.mensaje='En esta vista no hay objetos hermanos para reorganizar. Selecciona otra página o paso.';
        },
        async guardar(restaurar=false){
            if(this.guardando)return;this.guardando=true;
            const data=restaurar?{}:{...this.published};
            if(!restaurar)for(const g of this.grupos)data[g.id]=[...g.parent.children].filter(n=>g.items.includes(n)).map(n=>n.dataset.disposicionId);
            try{this.version=await this.wire.guardarDisposicion(data,this.version);this.published=data;this.pendiente=false;this.mensaje='Posiciones publicadas en PostgreSQL.';if(restaurar)location.reload();}catch{this.mensaje='No se guardaron las posiciones. Otra persona pudo haber cambiado la distribución; recarga antes de volver a editar.';}finally{this.guardando=false;}
        },
    });
    document.addEventListener('DOMContentLoaded',start);
    document.addEventListener('livewire:navigated',start);
    document.addEventListener('livewire:init',()=>window.Livewire.hook('morphed',()=>requestAnimationFrame(start)));
    if(document.readyState!=='loading')start();
})();
