// Este cuerpo se ejecuta en la pestaña QA de localhost mediante MCP opera_neon.
// Inspecciona y opera el DOM real; no sustituye datos, respuestas ni código de la app.
export async function comprobarUIFocal(caso) {
    const q=selector=>document.querySelector(selector), all=selector=>[...document.querySelectorAll(selector)];
    const wait=async fn=>{const end=Date.now()+15000;while(Date.now()<end){if(fn())return;await new Promise(r=>setTimeout(r,80));}throw Error('No terminó el estado esperado del contrato '+caso);};
    const frame=()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(()=>requestAnimationFrame(r))));
    if (!['localhost','127.0.0.1'].includes(location.hostname)) throw Error('Solo localhost.');
    const checks={};let metrics={};
    if(caso==='laboratorio') {
        await wait(()=>q('[data-lab-personal]'));
        const nav=all('nav[aria-label="Navegación principal"] a').map(x=>x.textContent.trim());
        checks.navigation=nav.slice(0,4).join('|')==='Inicio|El Laboratorio|Explorar la colección|Depósitos';
        checks.copy=q('.lab-introduction').textContent.includes('Generamos conocimiento sobre taxonomía-sistemática') && q('.lab-introduction').textContent.includes('para la conservación de la naturaleza.');
        checks.photos=all('[data-lab-personal] .lab-photo').length===10;
        checks.responsive=document.documentElement.scrollWidth<=document.documentElement.clientWidth+1;
    } else if(caso==='tarjetas') {
        await wait(()=>q('[data-card-progress]'));
        checks.filtered=q('.collection-active-filters')?.textContent.includes('Pichincha')===true;
        checks.noSiblingRail=!q('.collection-main aside');
        checks.noPageButtons=!all('.collection-card-pagination button').some(x=>/^(Anterior|Siguiente)$/.test(x.textContent.trim()));
        const count=()=>all('article.collection-taxon-card').length,before=count();
        q('[x-data^="hubCardScroll"]')?.scrollIntoView({block:'center'});await wait(()=>count()>before);
        checks.scroll=count()>before;
        const names=all('.collection-taxon-main').map(x=>x.textContent.trim());checks.unique=new Set(names).size===names.length;
        metrics={before,after:count()};
    } else if(caso==='registros') {
        await wait(()=>q('.portal-records-viewport')?.style.height);
        const root=q('.portal-records-viewport'),table=root.querySelector('.atlas-record-table-scroll');
        const edges=[];for(const zoom of ['50%','75%','100%','125%']){document.documentElement.style.zoom=zoom;window.dispatchEvent(new Event('resize'));await frame();edges.push(Math.abs(visualViewport.height-root.getBoundingClientRect().bottom));}
        document.documentElement.style.zoom='75%';window.dispatchEvent(new Event('resize'));await frame();checks.edge=edges.every(value=>value<1);
        const before=table.querySelectorAll('tbody tr').length;table.scrollLeft=80;table.scrollTop=table.scrollHeight;const position={x:table.scrollLeft,y:table.scrollTop};table.dispatchEvent(new Event('scroll'));
        await wait(()=>table.querySelectorAll('tbody tr').length>before);await frame();
        checks.scroll=table.querySelectorAll('tbody tr').length>before;
        checks.preservedPosition=Math.abs(table.scrollTop-position.y)<2 && Math.abs(table.scrollLeft-position.x)<2;
        const head=root.querySelector('thead th'),original=head.style.gridColumn;head.click();await wait(()=>root.querySelector('.hub-ig-column-popup')?.open);
        checks.columnMenu=!!root.querySelector('.hub-ig-column-popup input[type=search]');
        root.querySelector('.hub-ig-column-popup [aria-label="Mover columna a la derecha"]').click();await frame();checks.columnMove=head.style.gridColumn!==original;
        root.querySelector('.hub-ig-column-popup [aria-label="Cerrar columna"]').click();
        checks.noPageButtons=!root.querySelector('.hub-ig-page-button');metrics={edges,before,after:table.querySelectorAll('tbody tr').length};
    } else if(caso==='fichas') {
        await wait(()=>q('.collection-specimen-card'));checks.summary=!!q('.collection-species-summary');
        q('.collection-specimen-card').click();await wait(()=>q('.portal-record-dialog')?.open);
        const dialog=q('.portal-record-dialog'),head=dialog.querySelector('header'),close=head.querySelector('button');
        const labels=[...dialog.querySelectorAll('.atlas-record-card > dl dt')].map(x=>x.textContent.trim());
        checks.labels=labels.includes('Colector') && labels.includes('País') && !labels.includes('eventDate');
        checks.closeRight=close.getBoundingClientRect().right>head.getBoundingClientRect().left+head.getBoundingClientRect().width*.8;
        close.click();await wait(()=>!dialog.open);checks.recordClose=!dialog.open;
        // Fixture visual explícito: fotografía de referencia local y créditos sintéticos.
        window.dispatchEvent(new CustomEvent('lightbox-open',{detail:{url:'/images/taxonomia/fotografias/linepithema-humile.webp',alt:'Referencia para QA de presentación',nombreArchivo:'linepithema-humile.webp',atribuciones:{photographer:'Prueba visual',license:'CC BY 4.0 · datos sintéticos para QA',license_holder:'Prueba visual',license_institution:'Institución de prueba',contact:'qa@example.test'}}}));
        await wait(()=>q('.collection-image-dialog')?.open);await frame();const photo=q('.collection-image-dialog');
        checks.photoCreditsFit=photo.scrollHeight<=photo.clientHeight+1 && photo.textContent.includes('Institución');
        photo.querySelector('[aria-label="Cerrar fotografía"]').click();await wait(()=>!photo.open);checks.photoClose=!photo.open;
    } else if(caso==='chat') {
        await wait(()=>q('.portal-chat-shell'));
        checks.install=all('button[title="Instalar HubDigital en este dispositivo"]').length>0;
        if(document.documentElement.hasAttribute('data-chat-hidden'))q('[data-chat-restore]').click();
        else if(!q('#chat-bot-panel').getClientRects().length)q('#chat-bot-trigger').click();
        await wait(()=>q('#chat-bot-panel').getClientRects().length>0);
        all('#chat-bot-panel button').find(x=>x.textContent.trim()==='Ocultar asistente').click();await frame();
        checks.hide=getComputedStyle(q('.portal-chat-shell')).display==='none';checks.stored=localStorage.getItem('hubdigital.chat.oculto')==='1';
        q('[data-chat-restore]').click();await frame();checks.restore=!document.documentElement.hasAttribute('data-chat-hidden') && q('#chat-bot-panel').getClientRects().length>0;
        checks.focus=document.activeElement===q('#chat-bot-panel input');
        q('#chat-bot-panel button[aria-label="Cerrar chat"]').click();
    } else throw Error('Contrato desconocido.');
    return {caso,checks,metrics};
}
