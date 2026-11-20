(() => {
    if (window.hubDisposicionContenido) return;
    const hash = s => {let a=2166136261,b=3335557771;for(const c of s){a=Math.imul(a^c.charCodeAt(0),16777619);b=Math.imul(b^c.charCodeAt(0),2246822519);}return [a,b].map(n=>(n>>>0).toString(16).padStart(8,'0')).join('');};
    const path = (el,root) => {const p=[];for(let n=el;n&&n!==root;n=n.parentElement)p.unshift(n.tagName+':'+[...n.parentElement.children].filter(s=>!s.matches('script,style,iframe,input[type="hidden"]')).indexOf(n));return p.join('/');};
    const model = el => {
        if(el.dataset?.disposicionCampo)return 'dato:'+el.dataset.disposicionCampo;
        for(const n of [el,...el.querySelectorAll('input,select,textarea,[data-disposicion-model]')]){
            const a=[...n.attributes].find(a=>a.name.startsWith('wire:model')||a.name==='data-disposicion-model');if(a)return a.value;
        }
        return el.matches('.hub-lov-field') ? el.querySelector('[id^="lov-"]')?.id : el.querySelector('.hub-lov-field [id^="lov-"]')?.id;
    };
    const omitted = 'script,style,template,dialog,ui-modal,svg,[data-editor-fixed],[data-copy-preserve],[data-interactive-grid],[data-grid-frame],.leaflet-container,[data-disposicion-handle],[data-lab-editor-canvas]';
    const collect = (root, wizard=false) => {
        const groups=[];
        for(const parent of [root,...root.querySelectorAll('div,section,article,main,dl,ol,ul,nav,header,footer,form,fieldset,figure')]){
            if(parent.closest(omitted))continue;
            const display=getComputedStyle(parent).display;
            let items=[...parent.children].filter(el=>!el.matches(omitted+',header,main,footer')&&!el.hasAttribute('hidden')&&!el.querySelector('table,canvas,.leaflet-container,[data-editor-fixed]'));
            if(wizard){
                items=items.filter(el=>!el.matches('input[type="hidden"],hr,br') && (model(el)||el.textContent.trim()||el.querySelector('img')));
            }else{
                // Todas las secciones editoriales, incluidos sus contenedores,
                // listas y acciones, comparten el mismo contrato de orden.
                // Las tablas y visualizaciones científicas conservan su DOM.
                items=items.filter(el=>!el.matches('input[type="hidden"],hr,br') && (el.textContent.trim() || el.matches('img,picture') || el.querySelector('img,input,select,textarea')));
            }
            if(items.length<2)continue;
            for(const el of items)el.dataset.disposicionId ||= 'i-'+hash((wizard&&model(el))||path(el,wizard?root:(el.closest('main')||root)));
            const id=parent.dataset.disposicionGrupo || 'g-'+hash(items.map(el=>el.dataset.disposicionId).sort().join('|'));
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
    const configuration = (doc = document) => {try{return JSON.parse(doc.getElementById('disposicion-contenido-datos')?.textContent||'{}');}catch{return {};}};
    const limitarPosicion = valor => Math.min(100, Math.max(0, Math.round(valor)));
    function aplicarPosiciones(root, posiciones = {}) {
        for (const fila of root.querySelectorAll('[data-disposicion-libre]')) {
            const valor = posiciones[fila.dataset.disposicionLibre];
            if (Number.isInteger(valor)) fila.style.setProperty('--hub-posicion-horizontal', limitarPosicion(valor) + '%');
            else fila.style.removeProperty('--hub-posicion-horizontal');
        }
    }
    function desplazarObjeto(grupo, objeto, posicion) {
        objeto.removeAttribute('data-disposicion-desplazada');
        if (!posicion) return;
        const marco = grupo.parent.getBoundingClientRect();
        const natural = objeto.getBoundingClientRect();
        const limitarEje = (valor, dimension, minimo, maximo) =>
            minimo <= maximo ? Math.max(minimo, Math.min(maximo, valor * dimension / 1000)) : 0;
        const x = limitarEje(posicion.x, marco.width, marco.left - natural.left, marco.right - natural.right);
        const y = limitarEje(posicion.y, marco.height, marco.top - natural.top, marco.bottom - natural.bottom);
        objeto.style.setProperty('--hub-desplazamiento-x', x + 'px');
        objeto.style.setProperty('--hub-desplazamiento-y', y + 'px');
        if (x || y) objeto.setAttribute('data-disposicion-desplazada', '');
    }
    function aplicarDesplazamientos(grupos, posiciones = {}) {
        for (const grupo of grupos) for (const objeto of grupo.items) {
            const posicion = posiciones[objeto.dataset.disposicionId];
            if (posicion || objeto.hasAttribute('data-disposicion-desplazada')) desplazarObjeto(grupo, objeto, posicion);
        }
    }
    const distribuir = (root,data) => {
        const grupos = collect(root);
        for (const grupo of grupos) if (data[grupo.id]) arrange(grupo,data[grupo.id]);
        aplicarDesplazamientos(grupos,data.posiciones);
    };
    const aplicarGuardada = (root, scope, data = configuration(root.ownerDocument)[scope.replace(':', '.')]) => {
        aplicarPosiciones(root, data?.posiciones);
        if (!data || Array.isArray(data)) return;
        const grupos = collect(root, scope.startsWith('wizard:'));
        for (const grupo of grupos) {
            const ordenes = data[grupo.id]
                ? [data[grupo.id]]
                : Object.values(data).filter(orden => Array.isArray(orden) && orden.length > 1
                    && orden.every(id => grupo.items.some(elemento => elemento.dataset.disposicionId === id)));
            for (const orden of ordenes) arrange(grupo, orden);
        }
        aplicarDesplazamientos(grupos, data.posiciones);
    };
    const apply = root => {
        const scope = root.dataset.disposicionScope;
        if (scope && !root.closest('[data-contenidos-textos]')) aplicarGuardada(root, scope);
    };
    const start = () => {
        const page=document.documentElement.dataset.disposicionPortal;
        const main=document.querySelector('main');
        if(page&&main)document.body.dataset.disposicionScope='portal:'+page;
        document.querySelectorAll('[data-disposicion-scope]').forEach(apply);
    };
    window.hubDisposicionContenido = {hash, collect, arrange, apply, distribuir, aplicarGuardada};
    document.addEventListener('DOMContentLoaded',start);
    document.addEventListener('livewire:navigated',start);
    window.addEventListener?.('resize',()=>requestAnimationFrame(start));
    document.addEventListener('livewire:init',()=>window.Livewire.hook('morphed',()=>requestAnimationFrame(start)));
    if(document.readyState!=='loading')start();
})();
