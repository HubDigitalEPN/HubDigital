(() => {
    if (window.hubTextosSistema) return;
    window.hubTextosSistema = true;
    let diccionario = Object.create(null), patron = null, pendientes = new Set(), programado = false;
    const originales = new WeakMap(), atributosOriginales = new WeakMap();
    const normalizar = texto => texto.replace(/\s+/gu, ' ').trim();
    const excluido = elemento => !elemento || elemento.closest('script,style,textarea,pre,code,dd,[scope=row],[data-copy-preserve],[contenteditable]');
    const ignorar = elemento => excluido(elemento) || (elemento.closest('td') && !elemento.closest('button,a,label') && elemento.closest('td').colSpan <= 1);
    const traducir = texto => {
        const exacto = diccionario[normalizar(texto)];
        if (exacto !== undefined) return texto.match(/^\s*/u)[0] + exacto + texto.match(/\s*$/u)[0];
        return patron ? texto.replace(patron, original => diccionario[normalizar(original)] ?? original) : texto;
    };
    window.hubTexto = traducir;
    const aplicar = raiz => {
        if (!raiz || excluido(raiz.nodeType === 3 ? raiz.parentElement : raiz)) return;
        const texto = nodo => {
            if (ignorar(nodo.parentElement)) return;
            const actual = nodo.nodeValue, guardado = originales.get(nodo);
            const original = guardado?.mostrado === actual ? guardado.original : actual;
            const mostrado = traducir(original);
            originales.set(nodo, {original, mostrado});
            if (mostrado !== actual) nodo.nodeValue = mostrado;
        };
        if (raiz.nodeType === 3) return texto(raiz);
        const walker = document.createTreeWalker(raiz, NodeFilter.SHOW_TEXT);
        while (walker.nextNode()) texto(walker.currentNode);
        for (const el of [raiz, ...raiz.querySelectorAll('[title],[aria-label],[placeholder],[alt]')]) {
            if (ignorar(el)) continue;
            for (const attr of ['title', 'aria-label', 'placeholder', 'alt']) {
                const actual = el.getAttribute(attr), anteriores = atributosOriginales.get(el) || {};
                const original = anteriores[attr]?.mostrado === actual ? anteriores[attr].original : actual;
                if (original) {
                    const siguiente = traducir(original); anteriores[attr] = {original, mostrado:siguiente};
                    if (actual !== siguiente) el.setAttribute(attr, siguiente);
                }
                atributosOriginales.set(el, anteriores);
            }
        }
    };
    const iniciar = () => {
        diccionario = Object.assign(Object.create(null), JSON.parse(document.getElementById('textos-sistema-datos')?.textContent || '{}'));
        const escapar = texto => texto.replace(/[.*+?^${}()|[\]\\]/gu, '\\$&').replace(/\s+/gu, '\\s+');
        const claves = Object.keys(diccionario).sort((a,b) => b.length-a.length).map(escapar);
        patron = claves.length ? new RegExp('(?<![\\p{L}\\p{N}_])(?:'+claves.join('|')+')(?![\\p{L}\\p{N}_])', 'gu') : null;
        aplicar(document.documentElement);
    };
    const observador = new MutationObserver(cambios => {
        for (const cambio of cambios) {
            if (cambio.type === 'childList') for (const nodo of cambio.addedNodes) pendientes.add(nodo);
            else pendientes.add(cambio.target);
        }
        if (programado) return;
        programado = true;
        requestAnimationFrame(() => {
            programado = false; observador.disconnect();
            for (const nodo of pendientes) if (nodo.isConnected && [1,3].includes(nodo.nodeType)) aplicar(nodo);
            pendientes.clear(); observar();
        });
    });
    const observar = () => observador.observe(document.documentElement, {subtree:true, childList:true, characterData:true, attributes:true, attributeFilter:['title','aria-label','placeholder','alt']});
    document.addEventListener('livewire:navigated', () => { observador.disconnect(); iniciar(); observar(); });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => { iniciar(); observar(); });
    else { iniciar(); observar(); }
})();
