/** Prepara exclusivamente los siete paneles, conservando gráficos y sus explicaciones. */
export async function prepararPdfPaneles() {
    const paneles = [...document.querySelectorAll('.atlas-dashboard > .atlas-stage > .atlas-panel, .atlas-dashboard > .atlas-analysis-row > .atlas-panel')];
    if (paneles.length !== 7) throw new Error('La vista debe tener sus siete paneles cargados antes de exportar.');
    const marco = document.createElement('iframe');
    marco.title = 'Exportación de los siete paneles de la colección';
    marco.className = 'atlas-pdf-frame';
    marco.style.cssText = 'position:fixed;left:-12000px;top:0;width:1080px;height:800px;border:0';
    document.body.append(marco);
    try {
        const doc = marco.contentDocument;
        doc.open();
        doc.write('<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Colección Biológica — siete figuras</title></head><body class="atlas-export"><main></main></body></html>');
        doc.close();
        const estilosCargados = [];
        for (const hoja of document.querySelectorAll('link[rel="stylesheet"], style')) {
            const copia = hoja.cloneNode(true);
            if (copia.tagName === 'LINK') estilosCargados.push(new Promise((resolve, reject) => {
                const limite = setTimeout(() => reject(new Error('No se pudieron cargar los estilos para exportar las figuras.')), 25000);
                copia.addEventListener('load', () => { clearTimeout(limite); resolve(); }, {once: true});
                copia.addEventListener('error', () => { clearTimeout(limite); reject(new Error('No se pudieron cargar los estilos para exportar las figuras.')); }, {once: true});
            }));
            doc.head.append(copia);
        }
        const estilo = doc.createElement('style');
        estilo.textContent = `@page {size:A4 landscape;margin:12mm} body{margin:0;background:white;color:#18354d;font:12px Arial,sans-serif} .atlas-panel{display:block;width:100%;box-shadow:none;break-after:page;border:0;border-radius:0;overflow:visible} .atlas-panel:last-child{break-after:auto} .atlas-panel-header{padding:8px 0} .atlas-panel-tools,.atlas-map-actions,.leaflet-control,.atlas-cell-error{display:none!important} .atlas-map-shell{overflow:hidden;background:#eef4f8} .atlas-ranked-chart,.atlas-taxon-body{max-height:none;overflow:visible} .atlas-chart-canvas{height:440px} .atlas-figure-caption{margin:14px 0 0;font-size:12px;line-height:1.5} .atlas-export-chart{display:block;max-height:440px;max-width:100%;margin:auto} .atlas-taxon-photograph{display:none} button{pointer-events:none}`;
        doc.head.append(estilo);
        for (const panel of paneles) {
            const copia = panel.cloneNode(true);
            copia.classList.remove('atlas-map-maximized');
            const originalesVisibles = [...panel.querySelectorAll('[x-show]')];
            [...copia.querySelectorAll('[x-show]')].forEach((nodo, i) => {
                if (getComputedStyle(originalesVisibles[i]).display === 'none') nodo.remove();
            });
            // Los lienzos conservan su índice antes de retirar elementos ocultos.
            const originales = [...panel.querySelectorAll('canvas')];
            [...copia.querySelectorAll('canvas')].forEach(lienzo => {
                const original = originales.find(candidato => candidato.getAttribute('aria-label') === lienzo.getAttribute('aria-label'));
                const img = doc.createElement('img'); img.src = original.toDataURL('image/png'); img.alt = original.getAttribute('aria-label') || 'Gráfico del panel'; img.className = 'atlas-export-chart'; lienzo.replaceWith(img);
            });
            copia.querySelectorAll('.atlas-panel-tools, .atlas-map-actions, .atlas-chart-data-toggle, .atlas-cell-error, dialog, [hidden]').forEach(nodo => nodo.remove());
            copia.querySelectorAll('button').forEach(boton => { const texto = doc.createElement('div'); texto.className = boton.className; texto.innerHTML = boton.innerHTML; boton.replaceWith(texto); });
            const mapa = copia.querySelector('.atlas-map');
            if (mapa) {
                const rect = panel.querySelector('.atlas-map').getBoundingClientRect();
                const escala = Math.min(1, 1000 / rect.width, 440 / rect.height);
                mapa.style.width = `${rect.width}px`; mapa.style.height = `${rect.height}px`; mapa.style.minHeight = '0'; mapa.style.zoom = String(escala);
                mapa.parentElement.style.height = `${rect.height * escala}px`;
            }
            copia.querySelectorAll('.leaflet-control-container').forEach(nodo => nodo.remove());
            // El documento imprimible conserva solo HTML/CSS: no vuelve a inicializar Alpine ni Livewire.
            for (const nodo of [copia, ...copia.querySelectorAll('*')]) {
                for (const atributo of nodo.getAttributeNames()) {
                    if (/^(?:x-|wire:|:|@)/.test(atributo)) nodo.removeAttribute(atributo);
                }
            }
            doc.querySelector('main').append(copia);
        }
        await Promise.all(estilosCargados);
        await doc.fonts.ready;
        await Promise.all([...doc.images].map(img => img.decode().catch(() => {})));
        return marco;
    } catch (error) {
        marco.remove();
        throw error;
    }
}

export async function exportarPdfPaneles() {
    const marco = await prepararPdfPaneles();
    marco.contentWindow.addEventListener('afterprint', () => marco.remove(), {once: true});
    marco.contentWindow.focus();
    marco.contentWindow.print();
}
