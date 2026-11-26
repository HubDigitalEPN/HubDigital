// Este cuerpo se ejecuta en la pestaña QA de localhost mediante MCP opera_neon.
// Inspecciona y opera el DOM real; no sustituye datos, respuestas ni código de la app.
export async function comprobarUIFocal(caso) {
    const q=selector=>document.querySelector(selector), all=selector=>[...document.querySelectorAll(selector)];
    const wait=async fn=>{const end=Date.now()+(['portal_editor_visual','laboratorio_editor','catalogo_borrado','editor_wizard','textos_contexto','grillas_continuas','chat_tablas_simples','ficha_editor_directo','inventario_continuo'].includes(caso)?45000:15000);while(Date.now()<end){if(fn())return;await new Promise(r=>setTimeout(r,80));}throw Error('No terminó el estado esperado del contrato '+caso);};
    const frame=()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(()=>requestAnimationFrame(r))));
    const recargarFrame=async(iframe,comprobar)=>{const anterior=iframe.contentDocument;iframe.contentWindow.location.reload();await wait(()=>iframe.contentDocument!==anterior && iframe.contentDocument?.readyState==='complete' && comprobar());await frame();};
    if (!['localhost','127.0.0.1'].includes(location.hostname)) throw Error('Solo localhost.');
    const checks={};let metrics={};
    const textoLimpio=el=>{const clone=el.cloneNode(true);clone.querySelectorAll('[data-disposicion-handle]').forEach(n=>n.remove());return clone.textContent.trim();};
    const doble=el=>el.dispatchEvent(new MouseEvent('dblclick',{bubbles:true,cancelable:true}));
    const llenarEditor=value=>{const el=q('dialog[open] textarea');Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype,'value').set.call(el,value);el.dispatchEvent(new Event('input',{bubbles:true}));};
    const model=(key,tag='input')=>all(tag).find(x=>x.getAttribute('wire:model')===key);
    const fillModel=(key,value)=>{const el=model(key)||model(key,'textarea');if(!el)throw Error('No existe el campo '+key);const proto=el.tagName==='TEXTAREA'?HTMLTextAreaElement.prototype:HTMLInputElement.prototype;Object.getOwnPropertyDescriptor(proto,'value').set.call(el,value);el.dispatchEvent(new Event('input',{bubbles:true}));el.dispatchEvent(new Event('change',{bubbles:true}));};
    const sinMovimiento = root => !root.querySelector('[data-disposicion-handle],[data-disposicion-editable],[data-publicar-posiciones],[data-restaurar-posiciones],[data-disposicion-resize],.lab-layout-controls,.lab-editor-resize');
    const abrirAccionesInventario=async()=>{
        const grid=q('[data-grid-scroll-only]');
        grid.querySelector('.hub-ig-actions > button').click();
        await wait(()=>grid.querySelector('.hub-ig-menu')?.getClientRects().length);
        return grid.querySelector('.hub-ig-menu');
    };
    const buscarEnInventario=async texto=>{
        const menu=await abrirAccionesInventario();
        menu.querySelector('[wire\\:click="abrirBusqueda"]').click();
        await wait(()=>q('dialog[open] form[wire\\:submit="buscarRapido"]'));
        fillModel('consultaRapida',texto);
        q('dialog[open] form').requestSubmit();
        await wait(()=>!q('dialog[open]'));
    };
    const comprobarAyudas=async cantidad=>{
        const botones=all('[data-catalogo-ayuda] > button');
        let textos=botones.length===cantidad,foco=true,ajuste=true,posicion=true;
        for(const boton of botones) {
            const fila=boton.closest('[data-catalogo-control-fila]'),control=fila?.querySelector('[data-catalogo-control]');
            const b=boton.getBoundingClientRect(),c=control?.getBoundingClientRect(),f=fila?.getBoundingClientRect();
            posicion=posicion && !!control && !control.contains(boton) && b.left>=c.right+4 && Math.abs(b.right-f.right)<=1 && Math.abs(b.top-c.top-6)<=1;
            boton.focus();boton.click();
            const dialogo=document.getElementById(boton.getAttribute('aria-controls'));
            await wait(()=>dialogo?.open);await frame();
            textos=textos && dialogo.textContent.includes('Ejemplo:') && dialogo.querySelector('h3')?.textContent.trim().length>0;
            const rect=dialogo.getBoundingClientRect();
            ajuste=ajuste && dialogo.scrollWidth<=dialogo.clientWidth+1 && rect.width<=document.documentElement.clientWidth && rect.left>=-1;
            dialogo.querySelector('button[aria-label^="Cerrar ayuda:"]').click();
            await wait(()=>!dialogo.open);foco=foco && document.activeElement===boton;
        }
        return {textos,foco,ajuste,posicion};
    };
    if(caso==='editor_wizard') {
        const root=()=>q('[data-editor-wizard]'),vista=()=>q('[data-wizard-vista]');
        await wait(()=>vista());
        const original=vista().querySelector('h2').textContent.trim(),nuevo='Solicitud QA del editor contextual';
        doble(vista().querySelector('h2'));await wait(()=>q('dialog[open] textarea:enabled'));
        checks.selected=q('dialog[open]').textContent.includes('Ubicación:') && q('dialog[open] textarea').value===original;
        checks.quiet=!q('dialog[open]').textContent.includes('Cargando el texto guardado') && !('seleccionOcupada' in root()._x_dataStack[0]);
        llenarEditor(nuevo);q('dialog[open] button[type=submit]').click();
        await wait(()=>!q('dialog[open]') && vista()?.querySelector('h2').textContent.trim()===nuevo);
        checks.saved=true;
        await Livewire.find(root().getAttribute('wire:id')).$refresh();await frame();
        checks.reloaded=vista().querySelector('h2').textContent.trim()===nuevo;
        doble(vista().querySelector('[data-wizard-copy="general.titulo"]'));await wait(()=>q('dialog[open] textarea:enabled')?.value===nuevo);llenarEditor(original);q('dialog[open] button[type=submit]').click();
        await wait(()=>!q('dialog[open]') && vista()?.querySelector('h2').textContent.trim()===original);checks.restored=true;
        checks.fixedLayout=sinMovimiento(root());
        const comprobarEtiqueta=async (objeto,abrir,leer,nuevo)=>{
            const original=leer(objeto());
            for(const texto of [nuevo,original]){
                abrir(objeto());await wait(()=>q('dialog[open] textarea')&&!root()._x_dataStack[0].seleccionOcupada);
                llenarEditor(texto);q('dialog[open] button[type=submit]').click();
                await wait(()=>!q('dialog[open]')&&leer(objeto())===texto);
            }
            return true;
        };
        const titular=()=>vista().querySelector('[data-wizard-copy="solicitud.nombre_permiso"] [data-flux-label]');
        const leerEtiqueta=el=>el?.textContent.trim();
        await wait(titular);
        checks.fieldText=await comprobarEtiqueta(titular,doble,leerEtiqueta,'Titular QA del permiso de colección');
        const primerPaso=()=>q('[data-wizard-paso="0"][data-wizard-copy="pasos.solicitud"]');
        const leerPaso=el=>[...el?.childNodes??[]].filter(n=>n.nodeType===Node.TEXT_NODE).map(n=>n.textContent).join('').trim();
        checks.stepText=await comprobarEtiqueta(primerPaso,el=>el.dispatchEvent(new KeyboardEvent('keydown',{key:'F2',bubbles:true,cancelable:true})),leerPaso,'Solicitud QA');
        checks.actions=true;
        for(const clave of ['solicitud.generar_pdf','general.atras']){
            const objeto=vista().querySelector('[data-wizard-copy="'+clave+'"]');
            doble(objeto);await wait(()=>q('dialog[open] textarea')&&!root()._x_dataStack[0].seleccionOcupada);
            checks.actions=checks.actions&&q('dialog[open] textarea').value===objeto.textContent.trim();
            [...q('dialog[open]').querySelectorAll('button')].find(b=>b.textContent.trim()==='Cancelar').click();
            await wait(()=>!q('dialog[open]'));
        }
        let completos=true,inertes=true,portables=true,scrollCorrecto=true,regionesConScroll=0;
        for(const paso of [0,1,2,3,4,7,8]){
            const button=q('[data-wizard-paso="'+paso+'"]');
            button.click();await wait(()=>q('nav[aria-label="Pasos para editar textos"] [aria-current="step"]')?.textContent===button.textContent);await frame();
            const fragmento=q('[data-wizard-fragmento]');
            completos=completos && !!fragmento && fragmento.textContent.trim().length>20;
            const copia=fragmento.cloneNode(true);copia.querySelectorAll('[data-disposicion-handle]').forEach(n=>n.remove());copia.querySelectorAll('[data-disposicion-id]').forEach(n=>n.removeAttribute('data-disposicion-id'));portables=portables&&hubDisposicionContenido.collect(fragmento,true).map(g=>g.id).join()===hubDisposicionContenido.collect(copia,true).map(g=>g.id).join();
            inertes=inertes && !fragmento.querySelector('script,dialog,ui-modal,[href],[action],[wire\\:click],[wire\\:submit],[wire\\:model],[required],[aria-required],[pattern],[data-flux-loading-indicator],.animate-spin');
            const v=vista();scrollCorrecto=scrollCorrecto&&getComputedStyle(v).overflowY==='auto'&&v.getBoundingClientRect().bottom<=innerHeight+1;
            if(v.scrollHeight>v.clientHeight+1){regionesConScroll++;v.scrollTop=0;v.scrollBy(0,200);await frame();scrollCorrecto=scrollCorrecto&&v.scrollTop>0;}
        }
        checks.steps=completos;checks.inert=inertes;checks.portable=portables;
        checks.scroll=scrollCorrecto;metrics.scrollRegions=regionesConScroll;
        checks.apex=!q('[data-editor-wizard] > .hub-editor-filtros') && !q('dialog[data-hub-apex-field]');
        checks.direct=!root().textContent.includes('Reorganizar posiciones')&&!root().textContent.includes('Otros estados y textos de este paso')&&!root().querySelector('header');
        q('[data-wizard-paso="1"]').click();
        await wait(()=>q('[data-wizard-modalidad="Donación"]'));
        q('[data-wizard-modalidad="Donación"]').click();await wait(()=>q('[data-wizard-modalidad="Donación"]')?.getAttribute('aria-checked')==='true');
        q('[data-wizard-modalidad="Depósito"]').click();await wait(()=>q('[data-wizard-modalidad="Depósito"]')?.getAttribute('aria-checked')==='true');checks.modality=true;
        checks.fit=document.documentElement.scrollWidth<=document.documentElement.clientWidth+1;
    } else if(caso==='textos_contexto') {
        await wait(()=>q('[data-filtros-textos-sistema]'));
        const filtros=q('[data-filtros-textos-sistema]'),inputs=[...filtros.querySelectorAll('input,select')];
        checks.apex=inputs.length===2 && inputs.every(e=>e.hasAttribute('data-hub-apex-control'));
        checks.aligned=innerWidth<640 || Math.abs(inputs[0].getBoundingClientRect().top-inputs[1].getBoundingClientRect().top)<=1;
        const tabla=()=>q('[data-hub-grid][aria-label="Textos de todas las pantallas"]');
        const scroll=tabla().querySelector('.hub-ig-scroll'),cantidad=tabla().querySelectorAll('tbody tr').length;
        scroll.scrollTop=scroll.scrollHeight;
        scroll.dispatchEvent(new WheelEvent('wheel',{deltaY:700,bubbles:true,cancelable:true}));
        await wait(()=>tabla().querySelectorAll('tbody tr').length>cantidad);
        checks.infinite=!tabla().querySelector('.hub-ig-footer')&&!document.body.textContent.includes('Páginas visibles');
        checks.noActions=!tabla().querySelector('.hub-ig-actions')&&!!tabla().querySelector('tbody button');
        const buscador=inputs.find(e=>e.tagName==='INPUT');
        Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(buscador,'Identificador del lote / recipiente');buscador.dispatchEvent(new Event('input',{bubbles:true}));
        await wait(()=>all('table tbody tr').length===1 && q('table tbody').textContent.includes('Identificador del lote / recipiente'));
        doble(q('table tbody tr td[wire\\:dblclick]'));await wait(()=>model('contenido','textarea'));
        checks.location=document.body.textContent.includes('Estás cambiando este texto en:') && document.body.textContent.includes('Etiqueta del campo') && document.body.textContent.includes('Contenido');
        const original=model('contenido','textarea').value,nuevo='Identificador QA de recipiente';
        fillModel('contenido',nuevo);q('form[wire\\:submit="guardar"] button[type=submit]').click();
        await wait(()=>!model('contenido','textarea') && q('table tbody')?.textContent.includes(nuevo));checks.saved=true;
        q('table tbody button').click();await wait(()=>model('contenido','textarea')?.value===nuevo);
        checks.reloaded=true;fillModel('contenido',original);q('form[wire\\:submit="guardar"] button[type=submit]').click();
        await wait(()=>!model('contenido','textarea') && !q('table tbody')?.textContent.includes(nuevo));checks.restored=true;
        checks.fit=document.documentElement.scrollWidth<=document.documentElement.clientWidth+1;
    } else if(caso==='grillas_continuas') {
        await wait(()=>q('[data-hub-grid="server"]'));
        const root=q('[data-hub-grid="server"]'),state=Alpine.$data(root),scroll=root.querySelector('.hub-ig-scroll');
        const rows=()=>[...root.querySelectorAll('tbody tr')].filter(r=>!r.querySelector('[colspan]'));
        const search=root.querySelector('input[wire\\:model="busqueda"]');
        Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(search,'QA-UI-GRID-');search.dispatchEvent(new Event('input',{bubbles:true}));search.closest('form').requestSubmit();
        await wait(()=>rows().length===15 && rows().every(r=>r.textContent.includes('QA-UI-GRID-')));
        scroll.scrollIntoView({block:'center'});await frame();
        const cargar=async(modo,esperado,direccion=1)=>{
            scroll.scrollTop=direccion>0?scroll.scrollHeight:0;
            const event=modo==='rueda'?new WheelEvent('wheel',{deltaY:direccion*160,bubbles:true,cancelable:true}):new KeyboardEvent('keydown',{key:direccion>0?'PageDown':'PageUp',bubbles:true,cancelable:true});
            scroll.dispatchEvent(event);
            await wait(()=>!state.cargandoContinuidad && Number(state.datosVentana()?.hasta)===esperado);await frame();
        };
        await cargar('rueda',2);checks.wheel=rows().length===30;
        await cargar('teclado',3);checks.keyboard=rows().length===45;
        await cargar('rueda',4);checks.bounded=rows().length===45 && state.datosVentana().desde===2 && scroll.clientHeight<=innerHeight && scroll.scrollHeight>scroll.clientHeight;
        await cargar('teclado',3,-1);checks.previous=state.datosVentana().desde===1 && rows()[0].textContent.includes('QA-UI-GRID-000');
        const header=root.querySelector('thead th[data-grid-field="nombre"]');header.click();await wait(()=>q('dialog[open].hub-ig-column-popup'));
        checks.menu=q('dialog[open]').textContent.includes('Buscar en esta columna') && q('dialog[open] input')===document.activeElement;
        const popup=q('dialog[open]');[...popup.querySelectorAll('button')].find(b=>b.title==='Orden descendente').click();
        await wait(()=>!q('dialog[open]') && rows()[0].textContent.includes('QA-UI-GRID-065'));checks.sorted=true;
        header.click();await wait(()=>q('dialog[open]'));
        const term=q('dialog[open] input[type=search]');Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(term,'QA-UI-GRID-042');term.dispatchEvent(new Event('input',{bubbles:true}));
        [...q('dialog[open]').querySelectorAll('button')].find(b=>b.textContent.trim()==='Aplicar').click();await wait(()=>!q('dialog[open]') && rows().length===1);
        checks.filtered=rows()[0].textContent.includes('QA-UI-GRID-042') && state.datosVentana().desde===1 && state.datosVentana().hasta===1;
        checks.fit=document.documentElement.scrollWidth<=document.documentElement.clientWidth+1 && !q('dialog[data-hub-apex-field]');
        metrics={height:scroll.clientHeight,viewport:innerHeight,window:state.datosVentana()};
    } else if(caso==='menu_curaduria') {
        const sidebar=q('nav[data-flux-sidebar-nav]');
        const logical=[...sidebar.querySelectorAll('ui-disclosure a')];
        const paths=['/divulgacion','/administracion/contenidos','/prestamos/curador/depositos','/prestamos/receptor/depositos'];
        checks.unique=paths.every(path=>logical.filter(a=>new URL(a.href).pathname===path).length===1);
        checks.administration=['/administracion/catalogos','/administracion/matrices','/administracion/usuarios','/administracion/contenidos'].every(path=>{const links=logical.filter(a=>new URL(a.href).pathname===path);return links.length===1 && links[0].closest('ui-disclosure')?.textContent.includes('Administración');});
        checks.retired=!all('a').some(a=>/\/(?:administracion\/(?:textos-(?:portal|depositos|sistema))|divulgacion\/sincronizar|inventario\/taxonomia\/(?:revision|muestras|[^/]+\/revision|especimenes\/duplicados))$/.test(new URL(a.href).pathname));
        checks.labels=['Revisión documental y actas','Recepción física','Recordatorios de préstamos','Publicación del catálogo','Contenidos y textos'].every(text=>sidebar.textContent.includes(text)) && !sidebar.textContent.includes('Control de calidad');
        const shortcuts=q('nav[aria-label="Accesos principales"]');
        checks.shortcuts=!!shortcuts && ![...shortcuts.querySelectorAll('a')].some(a=>a.textContent.trim()==='Buscar') && [...shortcuts.querySelectorAll('a')].filter(a=>new URL(a.href).pathname==='/inventario/taxonomia/especimenes').length===1;
        checks.locality=!!all('section[aria-labelledby="coleccion-title"] a').find(a=>{
            const enlace=new URL(a.href);
            return a.textContent.includes('Localidades') && enlace.pathname==='/administracion/catalogos' && enlace.searchParams.get('tabla')==='localidades-coleccion';
        });
        const account=all('[data-flux-sidebar] a').find(a=>a.getAttribute('aria-label')==='Mi cuenta');
        account.click();await wait(()=>q('dialog[open]'));
        checks.account=q('dialog[open]').textContent.includes('Configuración de cuenta');
        const accountDialog=q('dialog[data-modal="account-settings"][open]');
        const close=[...accountDialog.querySelectorAll('button')].find(b=>b.getClientRects().length && b.closest('dialog')===accountDialog && b.getAttribute('aria-label')==='Cerrar ventana');
        if(!close)throw Error('No se encontró el cierre de Mi cuenta');close.click();await wait(()=>!q('dialog[open]'));await frame();
        checks.responsive=document.documentElement.scrollWidth<=document.documentElement.clientWidth+1;
    } else if(caso==='publicacion_unificada') {
        await wait(()=>q('[data-publicacion-catalogo]'));
        const tabs=()=>q('nav[aria-label="Estado de publicación"]');
        checks.page=all('h1').length===1 && q('h1').textContent.trim()==='Publicación del catálogo';
        const pending=[...tabs().querySelectorAll('a')].find(a=>new URL(a.href).searchParams.get('vista')==='pendientes');
        pending.click();await wait(()=>new URL(location.href).searchParams.get('vista')==='pendientes' && document.body.textContent.includes('Incorporar especímenes pendientes'));await frame();
        checks.pending=!!q('[data-publicacion-catalogo]') && tabs().querySelector('[aria-current="page"]').textContent.trim()==='Pendientes de divulgar' && all('h1').length===1;
        [...tabs().querySelectorAll('a')].find(a=>new URL(a.href).searchParams.get('vista')==='divulgados').click();await wait(()=>document.body.textContent.includes('Especímenes divulgados'));await frame();
        checks.disclosed=tabs().querySelector('[aria-current="page"]').textContent.trim()==='Divulgados' && all('h1').length===1;
        checks.noReview=!all('th').some(e=>e.textContent.includes('Revisión de datos'));
        checks.contrast=getComputedStyle(tabs().querySelector('[aria-current="page"]')).color==='rgb(255, 255, 255)';
        const configure=all('button').find(b=>b.getAttribute('wire:click')?.startsWith('abrirConfiguracion('));
        if(configure){configure.click();await wait(()=>q('dialog[open]'));checks.visibility=q('dialog[open]').textContent.includes('visibilidad');const cancel=all('dialog[open] button').find(b=>b.textContent.trim()==='Cancelar');cancel.click();await wait(()=>!q('dialog[open]'));}
        else checks.visibility=document.body.textContent.includes('No hay especímenes divulgados aún.');
        checks.responsive=document.documentElement.scrollWidth<=document.documentElement.clientWidth+1;
    } else if(caso==='contenidos_unificados') {
        await wait(()=>q('[data-contenidos-textos]'));
        const tabs=()=>q('nav[aria-label="Secciones de contenidos"]');
        checks.page=!q('[data-contenidos-textos] > h1') && document.body.textContent.includes('Contenidos y textos');
        const ayuda=()=>q('[data-contenidos-textos] .hub-editor-ayuda');
        const ayudaVisible=()=>!!ayuda()?.getClientRects().length&&ayuda().textContent.trim()==='* Doble clic para modificar un texto o imagen';
        checks.guide=ayudaVisible();
        checks.portal=!!q('[data-editor-visual-portal] iframe') && !q('[data-editor-visual-portal] > header');
        const pf=q('[data-editor-visual-portal] iframe');await wait(()=>pf.contentDocument?.body?.dataset.portalEditorReady==='true');[...pf.contentDocument.querySelectorAll('header nav a')].find(a=>new URL(a.href).pathname==='/el-laboratorio').click();await wait(()=>q('[data-editor-laboratorio] iframe'));
        checks.laboratorio=!q('[data-contenidos-textos] > h1') && !!q('[x-data^="laboratorioEditor"]');
        checks.guide=checks.guide&&ayudaVisible();
        [...tabs().querySelectorAll('a')].find(a=>new URL(a.href).searchParams.get('seccion')==='fichas').click();await wait(()=>document.body.textContent.includes('Fichas del explorador taxonómico'));
        checks.fichas=!q('[data-contenidos-textos] > h1') && !!q('[data-ficha-fila]');
        checks.guide=checks.guide&&ayuda()?.textContent.trim()==='* Doble clic en una fila para editar la ficha';
        await wait(()=>q('[data-fichas-editor] .hub-ig')?._x_dataStack?.[0]?.superficie);
        const fichaGrid=q('[data-fichas-editor] .hub-ig'),fichaPanel=q('[data-fichas-editor] section[aria-label="Editar ficha"]');
        checks.fichaClarity=document.body.textContent.includes('Busca el taxón por nombre') && (innerWidth<1100 || fichaGrid.getBoundingClientRect().width>fichaPanel.getBoundingClientRect().width*1.4);
        const filaInicial=fichaGrid.querySelectorAll('tbody tr').length,scroller=fichaGrid.querySelector('.hub-ig-scroll');
        checks.noActions=!fichaGrid.querySelector('.hub-ig-actions')&&!!fichaGrid.querySelector('tbody button');
        scroller.scrollTop=scroller.scrollHeight;scroller.dispatchEvent(new WheelEvent('wheel',{deltaY:700,bubbles:true,cancelable:true}));
        await wait(()=>q('[data-fichas-editor] .hub-ig')?.querySelectorAll('tbody tr').length>filaInicial);checks.fichaInfinite=!q('[data-fichas-editor] .hub-ig-footer');
        doble(q('[data-fichas-editor] [data-ficha-fila] td:nth-child(2)'));await wait(()=>q('dialog[open] #ficha-descripcion'));
        const descripcion=q('#ficha-descripcion').value;fillModel('descripcion','Leyenda QA del editor integrado');q('dialog[open] button[type=submit]').click();
        await wait(()=>document.body.textContent.includes('Transacción Efectuada') && q('#ficha-descripcion')?.value==='Leyenda QA del editor integrado');
        doble(q('[data-ficha-vista] p'));await wait(()=>q('dialog[open] #ficha-descripcion'));fillModel('descripcion',descripcion);q('dialog[open] button[type=submit]').click();await wait(()=>q('#ficha-descripcion')?.value===descripcion);checks.fichaSaved=true;
        [...tabs().querySelectorAll('a')].find(a=>new URL(a.href).searchParams.get('seccion')==='depositos').click();await wait(()=>q('[data-editor-wizard]'));
        checks.depositos=all('nav[aria-label="Pasos para editar textos"] button').length===7 && !!q('[data-wizard-vista]') && !q('[data-contenidos-textos] > h1');
        checks.guide=checks.guide&&ayudaVisible();
        [...tabs().querySelectorAll('a')].find(a=>new URL(a.href).searchParams.get('seccion')==='sistema').click();await wait(()=>q('[data-filtros-textos-sistema]'));
        checks.sistema=!q('[data-contenidos-textos] > h1') && !all('a').some(a=>/\/administracion\/textos-/.test(a.href));
        checks.guide=checks.guide&&!ayuda();
        checks.contrast=getComputedStyle(tabs().querySelector('[aria-current="page"]')).color==='rgb(255, 255, 255)';
        checks.responsive=document.documentElement.scrollWidth<=document.documentElement.clientWidth+1;
    } else if(caso==='portal_editor_visual') {
        let preparar=q('[data-editor-visual-portal]');if(preparar._x_dataStack[0].wire.grupo!=='inicio') await Livewire.find(preparar.getAttribute('wire:id')).$set('grupo','inicio');await wait(()=>{const e=q('[data-editor-visual-portal]'),f=e?.querySelector('iframe');return e?._x_dataStack?.[0]?.scope==='portal:inicio'&&f?.contentDocument?.location.pathname==='/'&&f.contentDocument.body?.dataset.portalEditorReady==='true';});
        const root=q('[data-editor-visual-portal]'),iframe=root.querySelector('iframe');
        const fillPortal=value=>{const el=q('dialog[open] textarea');Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype,'value').set.call(el,value);el.dispatchEvent(new Event('input',{bubbles:true}));};
        await wait(()=>iframe.contentDocument?.body?.dataset.portalEditorReady==='true' && iframe.contentDocument?.querySelector('h1'));
        checks.realPage=iframe.contentWindow.location.origin===location.origin && iframe.contentDocument.querySelector('h1').textContent.trim().length>10;
        checks.noChat=!iframe.contentDocument.querySelector('#chat-bot-trigger,#portal-chat-mobile-trigger,.portal-chat-shell');
        const titulo=iframe.contentDocument.querySelector('h1');
        const original=textoLimpio(titulo),nuevo='PRUEBA QA LOCAL — edición visual de Inicio';
        const contorno=getComputedStyle(titulo).outline;
        const t=performance.now();doble(titulo);checks.immediate=!!q('dialog[open]');metrics.modalOpenMs=Math.round(performance.now()-t);
        await wait(()=>q('dialog[open] textarea')&&!root._x_dataStack[0].seleccionOcupada&&!q('dialog[open] textarea').disabled);
        await frame();
        checks.selected=q('dialog[open] textarea').value===original;
        checks.noOutline=getComputedStyle(titulo).outline===contorno;
        checks.quiet=!q('dialog[open]').textContent.includes('Cargando el texto guardado');
        fillPortal(nuevo);q('dialog[open] button[type=submit]').click();
        await wait(()=>!q('dialog[open]') && iframe.contentDocument?.body?.dataset.portalEditorReady==='true' && textoLimpio(iframe.contentDocument.querySelector('h1'))===nuevo);
        checks.saved=true;await recargarFrame(iframe,()=>iframe.contentDocument?.body?.dataset.portalEditorReady==='true' && textoLimpio(iframe.contentDocument.querySelector('h1'))===nuevo);
        checks.reloaded=true;
        doble(iframe.contentDocument.querySelector('h1'));await wait(()=>q('dialog[open] textarea')?.value===nuevo&&!root._x_dataStack[0].seleccionOcupada&&!q('dialog[open] textarea').disabled);await frame();
        const rect=q('dialog[open]').getBoundingClientRect();checks.fit=rect.left>=-1 && rect.right<=innerWidth+1 && q('dialog[open]').scrollWidth<=q('dialog[open]').clientWidth+1;
        fillPortal(original);q('dialog[open] button[type=submit]').click();
        await wait(()=>!q('dialog[open]') && iframe.contentDocument?.body?.dataset.portalEditorReady==='true' && textoLimpio(iframe.contentDocument.querySelector('h1'))===original);checks.restored=true;
        checks.fixedLayout=sinMovimiento(root)&&sinMovimiento(iframe.contentDocument);
        const before=iframe.contentWindow.location.href;iframe.contentDocument.querySelector('a[href^="mailto:"]').click();await new Promise(r=>setTimeout(r,400));await frame();
        checks.noNavigation=iframe.contentWindow.location.href===before;
        const cancel=all('dialog[open] button').find(x=>x.textContent.trim()==='Cancelar');cancel?.click();
        const grupos=['depositos','catalogo','inicio'],cambios=[];
        let legales=iframe.contentDocument.querySelectorAll('a[data-platform-information]').length===5;
        for(const grupo of grupos){
            const anterior=q('[data-editor-visual-portal]');
            const estado=anterior._x_dataStack[0],enlace=[...anterior.querySelector('iframe').contentDocument.querySelectorAll('header nav a[href]')].find(a=>estado.paginas[new URL(a.href).pathname]===grupo);
            if(!enlace)throw Error('La página no tiene navegación editorial a '+grupo);enlace.click();
            await wait(()=>{const r=q('[data-editor-visual-portal]'),f=r?.querySelector('iframe');return f?.contentDocument?.body?.dataset.portalEditorReady==='true'&&f.contentDocument.location.pathname===new URL(f.src).pathname&&r._x_dataStack?.[0]?.paginas[new URL(f.src).pathname]===grupo&&r._x_dataStack?.[0]?.scope==='portal:'+grupo;});
            const actual=q('[data-editor-visual-portal]'),vista=actual.querySelector('iframe');
            cambios.push(actual._x_dataStack[0].paginas[new URL(vista.src).pathname]===grupo && actual._x_dataStack[0].scope==='portal:'+grupo);
            legales=legales&&vista.contentDocument.querySelectorAll('a[data-platform-information]').length===(grupo==='inicio'?5:0);
            if(grupo==='catalogo'){
                const catalogo=vista.contentDocument, aviso=catalogo.querySelector('.atlas-map-shell[data-copy-preserve] [data-mapa-editorial-aviso]');
                checks.mapNotice=aviso?.textContent.trim()==='El mapa se muestra en el Explorador público.'
                    &&!catalogo.querySelector('.atlas-map,.leaflet-container')&&!!catalogo.querySelector('#titulo-filos,[data-vista-catalogo="tarjetas"]');
            }
        }
        checks.sectionSwitch=cambios.every(Boolean);
        checks.legalLinks=legales;
        const er=q('[data-editor-visual-portal]'),doc=er.querySelector('iframe').contentDocument;
        const sections=[...doc.querySelectorAll('main section[id]')];
        checks.allSections=sections.length>1 && sections.every(el=>!!el.querySelector('h1,h2,h3,p,[data-portal-copy]')) && sinMovimiento(doc);
        checks.fixed=[...doc.querySelectorAll('[data-editor-fixed]')].every(el=>!el.dataset.disposicionEditable&&!el.querySelector('[data-disposicion-handle]'));
        checks.inert=doc.documentElement.dataset.vistaEditorial==='true'&&!doc.querySelector('script:not([type="application/json"]),[required],[pattern],[wire\\:click],[wire\\:submit],[data-disposicion-resize]');
        let fotos=true;for(const img of [...doc.querySelectorAll('[data-editorial-foto]')]){doble(img);await wait(()=>q('dialog[open] input[type=file]'));fotos=fotos&&er._x_dataStack[0].wire.fotoEditorialId===img.dataset.editorialFoto;[...q('dialog[open]').querySelectorAll('button')].find(b=>b.textContent.trim()==='Cancelar').click();await wait(()=>!q('dialog[open]'));}checks.photos=fotos;
        doble(doc.querySelector('[data-portal-copy="navegacion.institucion"]'));await wait(()=>q('dialog[open] textarea')&&!er._x_dataStack[0].seleccionOcupada);checks.fixedText=er._x_dataStack[0].claveLocal==='navegacion.institucion';[...q('dialog[open]').querySelectorAll('button')].find(b=>b.textContent.trim()==='Cancelar').click();await wait(()=>!q('dialog[open]'));
        const ruta=doc.location.href;doc.querySelector('.portal-curator-access').click();await new Promise(r=>setTimeout(r,400));checks.fixedText=checks.fixedText&&doc.location.href===ruta&&!q('dialog[open]');

        checks.direct=!q('[data-editor-visual-portal]').querySelector('header,select')&&!root.textContent.includes('Reorganizar posiciones')&&!root.textContent.includes('Todos los campos de esta sección');
    } else if(caso==='posicion_accion_inicio') {
        const editor=q('[data-editor-visual-portal]'),iframe=editor.querySelector('iframe');
        const doc=()=>iframe.contentDocument;
        const fila=()=>doc()?.querySelector('[data-disposicion-libre="inicio-hero-accion"]');
        const accion=()=>fila()?.querySelector('[data-disposicion-objeto]');
        await wait(()=>doc()?.body?.dataset.portalEditorReady==='true' && accion());
        const disposicion=d=>JSON.parse(d.querySelector('#disposicion-contenido-datos')?.textContent||'{}')['portal.inicio']||[];
        const publicada=disposicion(doc()),inicio=accion().getBoundingClientRect().left-fila().getBoundingClientRect().left;
        const porcentaje=publicada.posiciones?.['inicio-hero-accion'];
        accion().dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowRight',shiftKey:true,bubbles:true,cancelable:true}));
        await frame();
        checks.fixedLayout=sinMovimiento(editor)&&sinMovimiento(doc())&&Math.abs(accion().getBoundingClientRect().left-fila().getBoundingClientRect().left-inicio)<1;
        checks.savedLayout=!Number.isInteger(porcentaje)||fila().style.getPropertyValue('--hub-posicion-horizontal')===porcentaje+'%';
        const respuesta=await fetch('/',{cache:'no-store'});
        const publico=new DOMParser().parseFromString(await respuesta.text(),'text/html');
        checks.public=respuesta.ok && !!publico.querySelector('[data-disposicion-libre="inicio-hero-accion"] [data-disposicion-objeto]') && JSON.stringify(disposicion(publico))===JSON.stringify(publicada);
        const imagen=doc().querySelector('[data-editorial-foto="inicio"]');
        checks.preload=!!imagen && [...doc().querySelectorAll('link[rel=preload][as=image]')].some(link=>link.href===imagen.src);
    } else if(caso==='ficha_editor_directo') {
        await wait(()=>q('[data-fichas-editor] [data-ficha-fila]'));
        doble(q('[data-ficha-fila] td:nth-child(2)'));
        await wait(()=>q('dialog[open] #ficha-foto'));
        const root=q('[data-ficha-visual]');
        checks.fixedLayout=sinMovimiento(root)&&!!root.querySelector('[data-ficha-vista] figure, [data-ficha-vista] p');
        const preview=()=>q('[data-ficha-vista]');
        checks.photo=q('dialog[open] input[type=file]').getAttribute('wire:model')==='foto';
        const ayuda=q('.hub-editor-ayuda');
        const fuente=getComputedStyle(ayuda);
        checks.help=ayuda.textContent.includes('Doble clic en una fila') && Number(fuente.fontWeight)>=700
            && Math.abs(parseFloat(fuente.fontSize)-(.7*parseFloat(getComputedStyle(document.documentElement).fontSize)+4/3))<.1;
        const dialog=q('dialog[open]');
        checks.scientificName=q('#ficha-nombre-cientifico')?.value===q('[data-ficha-visual] h2').textContent.trim();
        checks.wideForm=innerWidth<1100 || dialog.getBoundingClientRect().width>=800 && dialog.scrollHeight<=dialog.clientHeight+1;
        [...q('dialog[open]').querySelectorAll('button')].find(b=>b.textContent.trim()==='Cancelar').click();await wait(()=>!q('dialog[open]'));
        doble(preview().querySelector('p'));await wait(()=>q('dialog[open] #ficha-descripcion'));checks.text=true;
        [...q('dialog[open]').querySelectorAll('button')].find(b=>b.textContent.trim()==='Cancelar').click();await wait(()=>!q('dialog[open]'));
        checks.noResize=!q('[data-disposicion-resize],.lab-editor-resize');
        checks.fit=document.documentElement.scrollWidth<=document.documentElement.clientWidth+1;
    } else if(caso==='laboratorio_editor') {
        const root=q('[data-editor-laboratorio]'),iframe=root.querySelector('iframe');
        const estado=()=>root._x_dataStack[0];
        const pagina=()=>iframe.contentDocument?.querySelector('.lab-page[data-lab-editor-ready="true"]');
        const item=(id)=>pagina()?.querySelector('[data-lab-element="'+id+'"]');
        const acciones=()=>[...q('dialog[open]')?.querySelectorAll('button')??[]];
        const accion=texto=>acciones().find(b=>b.textContent.trim()===texto);
        const listo=()=>pagina()&&!iframe.inert;
        await wait(listo);
        checks.page=!!item('texto-titulo')&&!!item('foto-adrian')&&!iframe.contentDocument.querySelector('.lab-editor-canvas');
        checks.fixedLayout=sinMovimiento(root)&&sinMovimiento(iframe.contentDocument);
        const original=estado().wire.textos.titulo,nuevo='Laboratorio — PRUEBA QA LOCAL';
        doble(item('texto-titulo'));await wait(()=>q('dialog[open] textarea:enabled'));
        checks.textEditor=q('dialog[open] textarea').value===original;
        const aviso=root.querySelector('[wire\\:target="guardar,publicarTextoVisual"][role="status"]');
        checks.quiet=!!aviso&&!aviso.getClientRects().length;
        llenarEditor(nuevo);accion('Guardar y publicar').click();
        await wait(()=>!q('dialog[open]')&&listo()&&item('texto-titulo')?.textContent.includes(nuevo));
        checks.saved=document.body.textContent.includes('Transacción Efectuada');
        await recargarFrame(iframe,()=>listo()&&item('texto-titulo')?.textContent.includes(nuevo));
        checks.reloaded=true;
        doble(item('texto-titulo'));await wait(()=>q('dialog[open] textarea'));
        llenarEditor(original);accion('Guardar y publicar').click();
        await wait(()=>!q('dialog[open]')&&listo()&&item('texto-titulo')?.textContent.trim()===original);
        checks.restored=true;checks.reconnected=!!item('foto-adrian')&&!!item('foto-vladimir')&&!!item('foto-david');
        let fotos=0;
        for(const id of ['foto-adrian','foto-vladimir','foto-david']){
            doble(item(id).querySelector('img')||item(id));await wait(()=>q('dialog[open] input[type=file]'));
            if(q('dialog[open] input[type=file]').getAttribute('wire:model')!=='fotos.'+id.slice(5))throw Error('Foto incorrecta '+id);
            fotos++;accion('Cancelar').click();await wait(()=>!q('dialog[open]')&&listo());
        }
        checks.photoEditor=fotos>0;checks.allPhotos=fotos===3;
        doble(iframe.contentDocument.querySelector('[data-editorial-foto="logo"]'));await wait(()=>q('dialog[open] input[wire\\:model="fotoEditorial"]'));
        checks.headerPhoto=true;accion('Cancelar').click();await wait(()=>!q('dialog[open]')&&listo());
        const count=estado().wire.bloques.length,agregar=()=>root.querySelector('button[wire\\:click="crearBloqueVisual"]');
        agregar().click();await wait(()=>q('dialog[open]')?.textContent.includes('Nuevo contenido'));
        checks.inlineCreate=estado().wire.bloques.length===count+1;
        accion('Cancelar').click();await wait(()=>!q('dialog[open]')&&estado().wire.bloques.length===count);
        agregar().click();await wait(()=>q('dialog[open]')?.textContent.includes('Nuevo contenido'));
        const creado=estado().wire.bloques.at(-1).id;
        fillModel('bloques.'+count+'.titulo','Bloque QA de edición directa');
        fillModel('bloques.'+count+'.texto','Contenido temporal para comprobar el editor.');
        accion('Guardar y publicar').click();
        const tituloBloque=()=>item('texto-bloque-'+creado+'-titulo');
        await wait(()=>!q('dialog[open]')&&listo()&&tituloBloque()?.textContent.includes('Bloque QA'));
        checks.inlinePublish=estado().wire.bloques.length===count+1&&sinMovimiento(iframe.contentDocument);
        doble(tituloBloque());await wait(()=>accion('Quitar este contenido'));
        accion('Quitar este contenido').click();await wait(()=>!q('dialog[open]')&&estado().wire.bloques.length===count);
        root.querySelector('form[wire\\:submit="guardar"] button[type=submit]').click();
        await wait(()=>listo()&&!tituloBloque()&&document.body.textContent.includes('Transacción Efectuada'));checks.inlineRemove=true;
        checks.fit=document.documentElement.scrollWidth<=document.documentElement.clientWidth+1;
        checks.direct=!!agregar()&&!root.querySelector('fieldset')&&sinMovimiento(root);
    } else if(caso==='inventario_continuo') {
        const grid=()=>q('[data-grid-scroll-only]');
        const scroller=()=>grid()?.querySelector('.hub-ig-scroll');
        const estado=()=>Alpine.$data(grid());
        await wait(()=>grid() && estado().superficie);
        const filas=()=>[...grid().querySelectorAll('tbody tr[wire\\:key]')];
        const inicial=filas().length;
        checks.sinPaginacion=!grid().querySelector('.hub-ig-footer, .hub-ig-page-button');
        scroller().scrollTop=scroller().scrollHeight;
        await wait(()=>filas().length>inicial && !estado().cargandoContinuidad);
        const ampliado=filas().length;
        checks.scroll=ampliado>inicial && new Set(filas().map(f=>f.getAttribute('wire:key'))).size===ampliado;
        scroller().scrollTop=scroller().scrollHeight;
        scroller().dispatchEvent(new KeyboardEvent('keydown',{key:'PageDown',bubbles:true,cancelable:true}));
        await wait(()=>filas().length>ampliado && !estado().cargandoContinuidad);
        checks.teclado=filas().length>ampliado;

        let menu=await abrirAccionesInventario();
        const accion=texto=>[...menu.querySelectorAll('[role="menuitem"]')].find(e=>e.textContent.trim()===texto);
        checks.menu=['Columnas','Buscar','Crear o eliminar columnas','Descargar Excel del catálogo','Descargar Excel IPT (GBIF)','Nuevo espécimen','Administrar publicación'].every(texto=>[...menu.querySelectorAll('[role="menuitem"]')].some(e=>e.textContent.replace(/\s+/g,' ').trim().replace(/\s*…$/,'')===texto));
        const cerrar=async()=>{
            q('dialog[open]').dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true,cancelable:true}));
            const cerrarBoton=q('dialog[open] [data-flux-modal-close] button') || q('dialog[open] button[aria-label="Close"]');
            cerrarBoton?.click();
            await wait(()=>!q('dialog[open]'));
        };
        accion('Filtros').click();await wait(()=>q('dialog[open] #inventario-filtros-titulo'));checks.filtros=true;
        await cerrar();
        menu=await abrirAccionesInventario();
        menu.querySelector('[wire\\:click*="panelColumnasAbierto"]').click();
        await wait(()=>q('dialog[open] #inventario-columnas-titulo'));checks.columnas=true;
        await cerrar();
        await buscarEnInventario('SIN_COINCIDENCIA_QA_INVENTARIO');
        checks.busqueda=filas().length===0 && scroller().scrollTop===0;
        await buscarEnInventario('');
        checks.restaurado=filas().length===inicial && estado().datosVentana().desde===1;
        metrics={inicial,ampliado};
    } else if(caso==='catalogo_borrado') {
        const filas=()=>all('[data-especimen-menu-fila]').filter(x=>x.getClientRects().length && /^QA-BORRADO-UI-20261118-[AB]$/.test(x.dataset.codigo));
        const borrarFila=async ()=>{
            const fila=filas()[0], codigo=fila.dataset.codigo;
            fila.click();
            const menu=()=>q('[data-especimen-acciones-fila]:popover-open');
            await wait(menu);
            [...menu().querySelectorAll('button')].find(x=>x.textContent.trim()==='Borrar definitivamente').click();
            await wait(()=>q('dialog[open]')?.textContent.includes(codigo));
        };
        await wait(()=>filas().length===2);
        await borrarFila();
        checks.individual=q('dialog[open]').textContent.includes('definitivamente');
        all('dialog[open] button').find(x=>x.textContent.trim()==='Cancelar').click();await wait(()=>!q('dialog[open]'));checks.cancelled=filas().length===2;
        await borrarFila();
        all('dialog[open] button').find(x=>x.textContent.trim()==='Eliminar definitivamente').click();await wait(()=>!q('dialog[open]') && filas().length===1);checks.deleted=true;
        [...(await abrirAccionesInventario()).querySelectorAll('button')].find(x=>x.textContent.trim()==='Marcar filas cargadas (1)').click();
        const borrar=menu=>[...menu.querySelectorAll('button')].find(x=>x.textContent.trim()==='Borrar selección');
        borrar(await abrirAccionesInventario()).click();await wait(()=>q('dialog[open]'));
        all('dialog[open] button').find(x=>x.textContent.trim()==='Eliminar definitivamente').click();await wait(()=>!q('dialog[open]') && filas().length===0);
        checks.batch=true;checks.selectionCleared=borrar(await abrirAccionesInventario()).disabled;
        checks.fit=document.documentElement.scrollWidth<=document.documentElement.clientWidth+1;
    } else if(caso==='tablas_catalogos') {
        const selector=()=>q('[data-tablas-catalogos] button[role="combobox"]');
        const tabla=()=>q('[data-registros-catalogos]');
        const menu=all('nav[data-flux-sidebar-nav] ui-disclosure a');
        await wait(()=>selector() && tabla());
        const opciones=()=>all('dialog.hub-lov-overlay[open] button[role="option"]');
        const abrir=async()=>{selector().click(); await wait(()=>opciones().length>0);};
        const elegir=async (nombre, clave)=>{
            opciones().find(opcion=>opcion.textContent.trim()===nombre).click();
            await wait(()=>q('[data-tabla-catalogo]')?.dataset.tablaCatalogo===clave && !q('dialog.hub-lov-overlay[open]'));
        };
        const root=q('[data-tablas-catalogos]');
        await abrir();
        const nombres=opciones().map(o=>o.textContent.trim());
        checks.menu=['catalogos','matrices','usuarios','contenidos'].every(p=>menu.filter(a=>new URL(a.href).pathname==='/administracion/'+p).length===1);
        checks.selector=selector().closest('[data-flux-field]').querySelector('label')?.textContent.trim()==='Tabla'
            && ['Instituciones','Cargos','Revocación de firmas','Localidades de Ecuador','Siglas de expedientes','Publicación GBIF','Correo y descargas · Correo'].every(nombre=>nombres.includes(nombre))
            && !nombres.some(nombre=>/Usuarios y perfiles|Botones y permisos|Matriz de depósitos/.test(nombre));
        const columnas=()=>[...tabla().querySelectorAll('thead th')].map(h=>h.textContent.trim());
        await elegir('Correo y descargas · Correo','sistema');
        const correo=columnas();
        await abrir();await elegir('Cargos','cargos');
        const cargos=columnas();
        checks.editors=correo.includes('password') && cargos.includes('nombre') && cargos.includes('activo')
            && cargos.includes('N.º') && !cargos.some(h=>['id','created_at','updated_at','Tabla','Detalle','Estado'].includes(h))
            && all('[data-registros-catalogos]').length===1;
        checks.navigation=root===q('[data-tablas-catalogos]') && location.pathname==='/administracion/catalogos'
            && all('[data-catalogo-fila]').length>0 && all('[data-catalogo-fila]').every(fila=>fila.dataset.catalogoFila==='cargos');
        q('[data-catalogo-fila="cargos"] td:first-child [data-catalogo-menu-fila]').click();
        await wait(()=>q('[role="menu"]:popover-open [role="menuitem"]'));
        const menuFila=q('[role="menu"]:popover-open');
        const eliminar=[...menuFila.querySelectorAll('[role="menuitem"]')].some(item=>item.textContent.trim()==='Eliminar');
        [...menuFila.querySelectorAll('[role="menuitem"]')].find(item=>item.textContent.trim()==='Editar').click();
        await wait(()=>model('datos.nombre')?.closest('dialog[open]'));
        const dialogo=model('datos.nombre').closest('dialog');
        checks.action=eliminar && dialogo.textContent.includes('Editar registro') && !dialogo.querySelector('table')
            && [...dialogo.querySelector('select').options].map(o=>o.textContent.trim()).join() === 'Sí,No';
        [...dialogo.querySelectorAll('button')].find(b=>b.textContent.trim()==='Cancelar').click();
        await wait(()=>!q('dialog[open]'));
        checks.fit=document.documentElement.scrollWidth<=document.documentElement.clientWidth+1;
    } else if(caso==='instituciones_unificadas') {
        await wait(()=>q('[data-registros-catalogos]'));
        checks.canonical=location.pathname==='/administracion/catalogos' && q('[data-tabla-catalogo]')?.dataset.tablaCatalogo==='instituciones';
        const canonico='/administracion/catalogos',antiguo='/inventario/taxonomia/entidades-depositantes';
        const enlaces=selector=>all(selector).filter(x=>new URL(x.href).pathname===canonico);
        checks.menu=enlaces('nav[data-flux-sidebar-nav] ui-disclosure a').length===1 && enlaces('nav[data-flux-sidebar-nav] ui-menu a').length===1 && !all('nav[data-flux-sidebar-nav] a').some(x=>new URL(x.href).pathname===antiguo);
        const abrirNuevo=async()=>{
            all('button').find(x=>x.textContent.trim()==='Nuevo registro').click();
            await wait(()=>q('dialog[open]') && model('nombre'));
        };
        const nombre='PRUEBA QA LOCAL INSTITUCION '+Date.now(),contacto='Contacto ficticio QA; sin actividad real';
        await abrirNuevo();
        checks.labels=all('dialog[open] input,dialog[open] select').filter(x=>x.type!=='hidden').every(x=>x.labels?.length>0 || !!x.getAttribute('aria-label') || x.getAttribute('aria-labelledby')?.split(' ').every(id=>document.getElementById(id)?.textContent.trim()));
        fillModel('nombre',nombre);fillModel('contacto',contacto);
        all('dialog[open] button').find(x=>x.getAttribute('wire:click')==='guardar').click();
        const filas=()=>all('[data-catalogo-fila="instituciones"]').filter(x=>x.textContent.includes(nombre));
        await wait(()=>!q('dialog[open]') && filas().length===1);
        checks.saved=filas()[0].textContent.includes(contacto) && (filas()[0].textContent.match(/Disponible/g)||[]).length===2;
        checks.acta=[...filas()[0].querySelectorAll('button')].some(b=>b.textContent.trim()==='Generar acta');
        [...filas()[0].querySelectorAll('button')].find(b=>b.textContent.trim()==='Editar').click();await wait(()=>q('dialog[open]') && model('contacto')?.value===contacto);
        const coleccion=q('dialog[open] ui-checkbox[wire\\:model="usarEnColeccion"]');
        const conservaReferencia=!!coleccion && (coleccion.hasAttribute('disabled') || coleccion.getAttribute('aria-disabled')==='true');
        fillModel('contacto',contacto+'; edición verificada');
        all('dialog[open] button').find(x=>x.getAttribute('wire:click')==='guardar').click();
        await wait(()=>!q('dialog[open]') && filas()[0]?.textContent.includes('edición verificada'));checks.edited=conservaReferencia;
        [...filas()[0].querySelectorAll('button')].find(b=>b.textContent.trim()==='Desactivar').click();
        await wait(()=>filas()[0]?.textContent.includes('Desactivado'));
        checks.disabled=filas()[0].textContent.includes('Disponible') && [...filas()[0].querySelectorAll('button')].some(b=>b.textContent.trim()==='Generar acta');
        await abrirNuevo();
        fillModel('nombre',nombre.toLowerCase());
        all('dialog[open] button').find(x=>x.getAttribute('wire:click')==='guardar').click();
        await wait(()=>model('nombre')?.getAttribute('aria-invalid')==='true');
        checks.duplicate=filas().length===1 && q('dialog[open]').textContent.includes('ya está registrado');
        const dialog=q('dialog[open]');checks.responsive=dialog.scrollWidth<=dialog.clientWidth+1 && document.documentElement.scrollWidth<=document.documentElement.clientWidth+1;
        all('dialog[open] button').find(x=>x.textContent.trim()==='Cancelar').click();metrics={nombre};
    } else if(caso==='mensajes_sistema') {
        const grilla = q('[aria-label="Mensajes del Sistema"][data-grid-scroll-only]');
        checks.navegacion = location.pathname === '/administracion/mensajes'
            && all('nav a, aside a').some(enlace => enlace.getAttribute('href')?.endsWith('/administracion/mensajes'));
        checks.grilla = !!grilla?.querySelector('tbody tr [data-catalogo-menu-fila]') && !grilla.querySelector('.hub-ig-footer');
        const nuevo = [...grilla.querySelectorAll('button')].find(boton => boton.textContent.trim() === 'Nuevo mensaje');
        nuevo.click();
        await wait(() => q('dialog[open] form[wire\\:submit="guardar"] textarea[wire\\:model="textoOriginal"]'));
        const dialogo = q('dialog[open]');
        checks.editor = dialogo.querySelectorAll('textarea[required]').length === 2
            && dialogo.querySelectorAll('[data-catalogo-ayuda] > button').length === 2;
        checks.ajuste = dialogo.scrollWidth <= dialogo.clientWidth + 1;
        [...dialogo.querySelectorAll('button')].find(boton => boton.textContent.trim() === 'Cancelar').click();
        await wait(() => !dialogo.open);
        checks.cancelar = true;
    } else if(caso==='catalogo_importacion') {
        checks.page=!!all('h1,h2').find(e=>e.textContent.includes('Importar'));
        checks.canonical=all('a').some(a=>{const u=new URL(a.href);return u.pathname==='/administracion/contenidos' && u.searchParams.get('grupo')==='importacion';});
        checks.noReview=!document.body.textContent.includes('Control de calidad') && !all('a').some(a=>new URL(a.href).pathname.endsWith('/revision'));
        const archivo=q('#matriz-catalogo-archivo');
        const requerido=archivo?.closest('[data-hub-apex-field]');
        checks.input=archivo?.getAttribute('aria-required')==='true' && !!requerido
            && parseFloat(getComputedStyle(requerido,'::before').borderTopWidth)>0
            && getComputedStyle(requerido,'::before').borderTopColor==='rgb(198, 40, 40)';
        const elegirLov=async (campo, etiqueta)=>{
            const boton=()=>q('#lov-'+campo+'-boton');
            await wait(()=>boton()?.hasAttribute('data-hub-apex-control'));
            boton().click();
            const dialogo=()=>q('#lov-'+campo+'-dialogo');
            await wait(()=>dialogo()?.open);
            const opcion=[...dialogo().querySelectorAll('[role=option]')].find(e=>e.textContent.trim()===etiqueta);
            opcion.click();
            await wait(()=>!dialogo()?.open && boton()?.textContent.includes(etiqueta));
            return boton().getAttribute('aria-haspopup')==='dialog';
        };
        checks.modoLov=await elegirLov('modo','Actualizar datos con la matriz');
        const validar=()=>all('button').find(b=>b.getAttribute('wire:click')==='validar');
        validar().click();
        await wait(()=>all('[data-flux-error]').some(e=>e.textContent.includes('Selecciona la matriz') && e.getClientRects().length));
        await new Promise(resolve=>setTimeout(resolve,4500));
        checks.errorVisible=all('[data-flux-error]').some(e=>e.textContent.includes('Selecciona la matriz') && e.getClientRects().length && !e.hidden);
        const nombre='qa-ayudas-'+Date.now()+'.csv',codigo='QA-UI-ARCHIVO-'+Date.now();
        await new Promise((resolve,reject)=>{
            const limpiar=()=>{clearTimeout(timer);archivo.removeEventListener('livewire-upload-finish',fin);archivo.removeEventListener('livewire-upload-error',error);};
            const fin=()=>{limpiar();resolve();},error=()=>{limpiar();reject(Error('Falló la subida local de la matriz QA'));};
            const timer=setTimeout(()=>{limpiar();reject(Error('La subida local no finalizó dentro de 20 segundos'));},20000);
            archivo.addEventListener('livewire-upload-finish',fin);archivo.addEventListener('livewire-upload-error',error);
            const data=new DataTransfer();data.items.add(new File(['occurrenceID;individualCount;eventDate\r\n'+codigo+';dos;sin fecha registrada\r\n'],nombre,{type:'text/csv'}));archivo.files=data.files;archivo.dispatchEvent(new Event('change',{bubbles:true}));
        });
        await wait(()=>q('#lov-delimitador-valor')?.textContent.trim()==='Punto y coma');
        checks.separator=document.body.textContent.includes('Separador detectado: Punto y coma');
        validar().click();await wait(()=>q('[aria-label="Vista previa de la matriz"]')?.textContent.includes(codigo));
        checks.preview=q('[aria-label="Vista previa de la matriz"]').textContent.includes('Observaciones de importación') && !!q('[aria-label="Vista previa de la matriz"] details[open]') && !q('[aria-label="Estado de la importación"]');
        const ayuda=await comprobarAyudas(4);checks.help=ayuda.textos;checks.helpFocus=ayuda.foco;checks.helpFits=ayuda.ajuste;checks.helpPosition=ayuda.posicion;
        const abrirGuia=all('button').find(b=>b.textContent.includes('Ayuda de las columnas de la matriz'));abrirGuia.focus();abrirGuia.click();
        const guia=q('#guia-columnas-catalogo');await wait(()=>guia.open);await frame();
        const filasGuia=()=>[...guia.querySelectorAll('[data-busqueda]')],cuantas=filasGuia().length,buscar=q('#buscar-guia-catalogo');
        buscar.value='decimalLatitude';buscar.dispatchEvent(new Event('input',{bubbles:true}));await frame();
        checks.guide=cuantas>0 && filasGuia().filter(e=>e.getClientRects().length).some(e=>e.textContent.includes('decimalLatitude')) && filasGuia().every(e=>e.querySelector('dd')?.textContent.trim());
        checks.guideFits=guia.scrollWidth<=guia.clientWidth+1 && guia.getBoundingClientRect().width<=document.documentElement.clientWidth;
        guia.querySelector('button[aria-label="Cerrar guía de columnas"]').click();await wait(()=>!guia.open);checks.guide=checks.guide && document.activeElement===abrirGuia;
        checks.separadorLov=await elegirLov('delimitador','Coma');
        await wait(()=>!q('[aria-label="Vista previa de la matriz"]'));
        checks.separadorLov=checks.separadorLov && await elegirLov('delimitador','Punto y coma');
        all('button').find(b=>b.getAttribute('wire:click')==='limpiar').click();await wait(()=>!q('[aria-label="Vista previa de la matriz"]'));
        metrics={columnas:cuantas,archivo:nombre,soloValidacion:true};
        checks.responsive=document.documentElement.scrollWidth<=document.documentElement.clientWidth+1;
    } else if(caso==='catalogo_taxones') {
        const campoBusqueda=()=>q('[data-registros-catalogos] input[wire\\:model="busqueda"]');
        const buscar=valor=>{const el=campoBusqueda();el.value=valor;el.dispatchEvent(new Event('input',{bubbles:true}));el.closest('form').requestSubmit();};
        const nombre='Dolichoderus qaprueba'+Date.now().toString().replace(/\d/g,n=>'abcdefghij'[Number(n)]);
        all('button').find(x=>x.textContent.trim()==='Nuevo registro').click();
        await wait(()=>model('padreId','select') && q('dialog[open]'));
        const padre=model('padreId','select'),opcion=[...padre.options].find(x=>x.textContent==='Dolichoderus (genero)');
        checks.parents=!!opcion && padre.options.length>2;
        padre.value=opcion.value;padre.dispatchEvent(new Event('change',{bubbles:true}));
        const rango=model('rango','select');rango.value='especie';rango.dispatchEvent(new Event('change',{bubbles:true}));
        fillModel('nombreCientifico',nombre);fillModel('autor','PRUEBA QA - SIN ESPECIE REAL');
        all('dialog[open] button').find(x=>x.getAttribute('wire:click')==='registrarTaxon').click();
        await wait(()=>!q('dialog[open]'));
        buscar(nombre);
        const filas=()=>all('[data-catalogo-fila="taxones"]').filter(x=>x.getClientRects().length && x.textContent.includes(nombre));await wait(()=>filas().length===1);
        checks.created=true;checks.search=filas().length===1;
        [...filas()[0].querySelectorAll('button')].find(b=>b.textContent.trim()==='Editar').click();await wait(()=>q('dialog[open]') && model('editAutor'));
        fillModel('editAutor','PRUEBA QA - EDICION VERIFICADA');
        all('dialog[open] button').find(x=>x.getAttribute('wire:click')==='actualizarTaxon').click();
        await wait(()=>!q('dialog[open]') && filas()[0]?.textContent.includes('EDICION VERIFICADA'));checks.edited=true;
        // El MCP atiende la confirmación nativa de este taxón sintético.
        [...filas()[0].querySelectorAll('button')].find(b=>b.textContent.trim()==='Desactivar').click();
        await wait(()=>filas()[0]?.textContent.includes('Inactivo'));checks.inactive=true;
        buscar('SIN_COINCIDENCIA_QA');await wait(()=>document.body.textContent.includes('No hay registros para esta búsqueda.'));checks.empty=true;
        buscar(nombre);await wait(()=>filas().length===1);
        checks.responsive=document.documentElement.scrollWidth<=document.documentElement.clientWidth+1;
        metrics={nombre};
    } else if(caso==='catalogo_alta') {
        const botonLista=campo=>document.getElementById('captura-'+campo);
        const alta=()=>q('dialog[data-modal="alta-especimen"]');
        const peticiones=()=>performance.getEntriesByType('resource').filter(r=>new URL(r.name).pathname.endsWith('/update') && r.name.includes('/livewire')).length;
        const menu=await abrirAccionesInventario();
        const nuevo=[...menu.querySelectorAll('button')].find(x=>x.textContent.trim()==='Nuevo espécimen');
        const grid=q('[data-grid-scroll-only]'), scroll=grid.querySelector('.hub-ig-scroll');
        const antes=peticiones(), inicio=performance.now();
        nuevo.click();
        checks.open=alta().open;
        const aperturaMs=performance.now()-inicio;
        await frame();
        checks.openWithoutRequest=peticiones()===antes;
        checks.noRevision=!all('th,[role="columnheader"]').some(x=>/Revisión del nombre|Estado revisión|Motivo revisión/.test(x.textContent));
        checks.dateLabels=all('th,[role="columnheader"]').some(x=>x.textContent.includes('eventDate · normalizada')) && all('th,[role="columnheader"]').some(x=>x.textContent.includes('eventDate · original'));
        checks.ecuador=model('fechaColecta').value===new Intl.DateTimeFormat('en-CA',{timeZone:'America/Guayaquil',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
        checks.empty=!q('#captura-codigoCatalogo') && !alta().textContent.includes('Los campos con *')
            && !model('colector').value && botonLista('taxonId').textContent.includes('Selecciona un taxón');
        checks.lovs=['taxonId','localidad','localidad2','localidad3','country','stateProvince','municipality','localityName']
            .every(campo=>botonLista(campo)?.matches('button[role="combobox"]'));
        const ayuda=await comprobarAyudas(23);checks.help=ayuda.textos;checks.helpFocus=ayuda.foco;checks.helpFits=ayuda.ajuste;checks.helpPosition=ayuda.posicion;
        const popup=campo=>document.getElementById('lov-'+campo+'-dialogo');
        botonLista('taxonId').click();
        await wait(()=>popup('taxonId')?.querySelector('button[role="option"]:enabled'));
        const opcionTaxon=popup('taxonId').querySelector('button[role="option"]:enabled'), nombreTaxon=opcionTaxon.textContent;
        opcionTaxon.click();await wait(()=>!popup('taxonId')?.open && botonLista('taxonId').textContent.includes(nombreTaxon));
        const usarTexto=async(campo,valor)=>{
            botonLista(campo).click();await wait(()=>popup(campo)?.open);
            const input=popup(campo).querySelector('input[type=search]');
            input.value=valor;input.dispatchEvent(new Event('input',{bubbles:true}));
            const usar=()=>[...popup(campo).querySelectorAll('button')].find(x=>x.textContent.trim()==='Usar texto ingresado' && x.getClientRects().length);
            await wait(usar);usar().click();await wait(()=>!popup(campo)?.open);await frame();
        };
        const marca='QA-UI-CATALOGO-'+Date.now();
        await usarTexto('localidad3',marca+' sector');
        checks.freeText=botonLista('localidad3').textContent.includes(marca+' sector');
        fillModel('fechaColecta','2026-11-10');fillModel('colector','PRUEBA QA - Colector ficticio');
        fillModel('decimalLatitude','91');fillModel('decimalLongitude','-78.47');fillModel('individualCount','-1');
        const registrar=()=>alta().querySelector('button[wire\\:click="registrarEspecimen"]');
        registrar().click();await wait(()=>model('decimalLatitude')?.getAttribute('aria-invalid')==='true' && model('individualCount')?.getAttribute('aria-invalid')==='true');
        checks.invalid=true;
        checks.errorVisible=['decimalLatitude','individualCount'].every(campo=>{const field=model(campo)?.closest('[data-catalogo-campo]');return [...(field?.querySelectorAll('[data-flux-error]')||[])].some(e=>e.textContent.trim() && e.getClientRects().length && !e.hidden);});
        const enlace=alta().querySelector('[data-catalogo-resumen-errores] a[href="#captura-decimalLatitude"]');enlace?.click();
        checks.errorFocus=document.activeElement===model('decimalLatitude');
        checks.labels=[...alta().querySelectorAll('input,select,textarea')].filter(x=>x.type!=='hidden').every(x=>x.labels?.length>0 || !!x.getAttribute('aria-label') || x.getAttribute('aria-labelledby')?.split(' ').every(id=>document.getElementById(id)?.textContent.trim()));
        checks.responsive=alta().scrollWidth<=alta().clientWidth+1 && document.documentElement.scrollWidth<=document.documentElement.clientWidth+1;
        fillModel('decimalLatitude','-0.09');fillModel('individualCount','5');fillModel('specimenNotes','PRUEBA QA. SIN MATERIAL REAL. NO DIVULGAR.');
        const filasAntes=[...scroll.querySelectorAll('tr[wire\\:key]')], scrollAntes=scroll.scrollTop, peticionesAntes=peticiones();
        registrar().click();
        const creada=()=>all('button').find(b=>b.textContent.trim()==='Ver última ficha creada');
        await wait(()=>!alta().open && creada());await frame();
        checks.saved=true;
        checks.saveWithoutReload=peticiones()===peticionesAntes+1 && grid.querySelector('.hub-ig-scroll')===scroll
            && filasAntes.every((fila,i)=>scroll.querySelectorAll('tr[wire\\:key]')[i]===fila) && scroll.scrollTop===scrollAntes;
        const codigo=q('[data-hub-feedback="success"]').textContent.match(/MEPN-INV-[0-9A-Z]{26}/)?.[0];
        checks.generated=!!codigo;
        creada().click();await wait(()=>q('dialog[open]')?.textContent.includes(codigo));
        checks.createdLink=!!q('dialog[open]');all('dialog[open] button').find(b=>b.getAttribute('wire:click')==='cerrarFicha').click();await wait(()=>!q('dialog[open]'));
        await buscarEnInventario(codigo);
        const fila=()=>all('[data-especimen-menu-fila]').find(x=>x.dataset.codigo===codigo && x.getClientRects().length);
        const editar=async()=>{
            await wait(fila);fila().click();
            const menuFila=()=>q('[data-especimen-acciones-fila]:popover-open');
            await wait(menuFila);
            [...menuFila().querySelectorAll('button')].find(x=>x.textContent.trim()==='Editar').click();
            await wait(()=>botonLista('editLocalidad3')?.checkVisibility());
        };
        await wait(fila);
        checks.rowMenuLeft=fila().closest('tr')?.firstElementChild?.contains(fila()) ?? true;
        const sectorCorregido=marca+' sector corregido';
        await editar();await usarTexto('editLocalidad3',sectorCorregido);
        all('dialog[open] button').find(x=>x.getAttribute('wire:click')==='actualizarEspecimen').click();
        await wait(()=>!q('dialog[open]'));await frame();await editar();
        checks.edited=botonLista('editLocalidad3').textContent.includes(sectorCorregido);
        all('dialog[open] button').find(x=>x.textContent.trim()==='Cancelar').click();
        metrics={codigo,aperturaMs};
    } else if(caso==='laboratorio') {
        await wait(()=>q('[data-lab-personal]'));
        const nav=all('nav[aria-label="Navegación principal"] a').map(x=>x.textContent.trim());
        checks.navigation=nav.slice(0,4).join('|')==='Inicio|El Laboratorio|Explorar la colección|Depósitos';
        checks.copy=q('.lab-introduction').textContent.includes('Generamos conocimiento sobre taxonomía-sistemática') && q('.lab-introduction').textContent.includes('para la conservación de la naturaleza.');
        const frames=all('[data-lab-profile] > .lab-portrait-frame');
        checks.photos=frames.length===3;
        checks.branches=all('[data-lab-branch]').length===1 && q('[data-lab-branch]')?.dataset.labBranch==='entomologia' && q('.lab-branch-title')?.textContent.includes('Entomología') && all('.lab-branch-nav a').map(a=>a.getAttribute('href')).join('|')==='#rama-entomologia';
        for(const f of frames){f.scrollIntoView({block:'center'});await frame();await wait(()=>f.querySelector('img')?.complete && f.querySelector('img').naturalWidth>0);}
        checks.originals=frames.every(f=>f.querySelector('img[src*="/images/laboratorio/equipo/"]'));
        const sizes=frames.map(f=>f.getBoundingClientRect());
        checks.uniform=sizes.every(r=>Math.abs(r.width-sizes[0].width)<1 && Math.abs(r.height-sizes[0].height)<1);
        checks.noAnimals=!q('.lab-science-card,.lab-personal-banner') && !all('.lab-page a').some(a=>a.textContent.includes('Perfiles institucionales'));
        checks.summaries=all('.lab-profile-summary > p').every(p=>p.textContent.trim().split(/\s+/u).length<=40);
        const button=q('[data-lab-open-profile]');button.click();await frame();
        const modal=document.getElementById(button.dataset.labOpenProfile);
        checks.modal=modal.open && !!modal.querySelector('.lab-portrait-frame img') && modal.querySelector('.lab-profile-biography > p').textContent.trim().split(/\s+/u).length>40;
        checks.wrap=getComputedStyle(modal.querySelector('.lab-portrait-frame')).float===(innerWidth>600?'left':'none');
        modal.querySelector('[data-lab-close-profile]').click();await frame();
        checks.modalClose=!modal.open && document.activeElement===button;
        window.scrollTo(0,0);await frame();
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
        const sizes=all('.grid > .collection-taxon-card').map(x=>x.getBoundingClientRect());
        checks.uniform=sizes.length>1 && sizes.every(x=>Math.abs(x.width-sizes[0].width)<1 && Math.abs(x.height-sizes[0].height)<1);
        const target=all('.collection-taxon-main').at(-1),previous=q('[data-catalogo-seleccion]').dataset.catalogoSeleccion;
        target.scrollIntoView({block:'center'});target.click();await wait(()=>q('[data-catalogo-seleccion]').dataset.catalogoSeleccion!==previous);await frame();
        await wait(()=>window.scrollY<1 && document.activeElement.matches('.collection-main h1,.collection-main h2'));
        checks.start=true;
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
        const mapa=q('.collection-species-map') || q('[x-data^="portalMapaEspecie"]');
        mapa?.scrollIntoView({block:'center'});await frame();
        await wait(()=>all('.collection-main .leaflet-overlay-pane path.leaflet-interactive').length>0);
        checks.markerContrast=all('.collection-main .leaflet-overlay-pane path.leaflet-interactive').every(x=>x.getAttribute('stroke')==='#704500' && Number(x.getAttribute('stroke-width'))>=1.8 && Number(x.getAttribute('fill-opacity'))===1);
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
    } else if(caso==='chat_tablas_simples') {
        const componente=()=>all('[wire\\:id]').find(el=>JSON.parse(el.getAttribute('wire:snapshot')||'{}').memo?.name?.endsWith('chat-bot-widget'));
        await wait(()=>componente());
        const wire=Livewire.find(componente().getAttribute('wire:id'));
        await wire.nuevaConversacion();q('button[aria-label="Abrir asistente HubDigital"]').click();await frame();
        const preguntar=async(pregunta,seleccion=[])=>{wire.$set('pregunta',pregunta,false);await wire.enviar(seleccion);await frame();};
        await preguntar('Genera una matriz de datos de esta selección',{fc:'QA-SIN-DATOS-EDITOR-20261119'});
        checks.empty=!componente().querySelector('table') && componente().textContent.includes('No hay registros públicos');
        await preguntar('Genera una matriz de datos del catálogo completo');
        const table=componente().querySelector('.portal-chat-table');
        checks.simple=!!table&&table.querySelectorAll('tbody tr').length>0&&!table.closest('[data-hub-grid],[data-grid-frame],.hub-ig')&&!table.querySelector('thead button');
        await preguntar('Diseña un experimento para distinguir correlación y causalidad entre iluminación nocturna y actividad de escarabajos. Incluye controles, réplicas y sesgos; es hipotético.');
        const texto=wire.mensajes.at(-1).texto;
        checks.guidance=/control/i.test(texto)&&/r[eé]plicas/i.test(texto)&&/sesgos/i.test(texto)&&/causal/i.test(texto)&&!texto.includes('No encontré una fuente pública');
        checks.fit=document.documentElement.scrollWidth<=innerWidth+1&&q('#chat-bot-panel').getBoundingClientRect().width<=innerWidth;
        await wire.nuevaConversacion();q('button[aria-label="Cerrar chat"]').click();
    } else if(caso==='chat') {
        await wait(()=>q('.portal-chat-shell'));
        checks.fresh=!document.documentElement.hasAttribute('data-chat-hidden') && q('#chat-bot-trigger').getClientRects().length>0 && localStorage.getItem('hubdigital.chat.oculto')===null;
        checks.catalogOnly=q('.portal-chat-shell').dataset.chatOcultable==='true' && !!q('.portal-chat-hide');
        checks.install=all('button[title="Instalar HubDigital en este dispositivo"]').length>0;
        if(document.documentElement.hasAttribute('data-chat-hidden'))q('[data-chat-restore]').click();
        else if(!q('#chat-bot-panel').getClientRects().length)q('#chat-bot-trigger').click();
        await wait(()=>q('#chat-bot-panel').getClientRects().length>0);
        all('#chat-bot-panel button').find(x=>x.textContent.trim()==='Ocultar asistente').click();await frame();
        checks.hide=getComputedStyle(q('.portal-chat-shell')).display==='none';
        checks.catalogRestore=!!q('[data-chat-restore-catalogo]') && getComputedStyle(q('[data-chat-restore-catalogo]')).display!=='none' && q('[data-chat-restore-catalogo]').getClientRects().length>0;
        q('[data-chat-restore]').click();await frame();checks.restore=!document.documentElement.hasAttribute('data-chat-hidden') && q('#chat-bot-panel').getClientRects().length>0;
        checks.bothHidden=all('[data-chat-restore]').every(x=>getComputedStyle(x).display==='none');
        checks.focus=document.activeElement===q('#chat-bot-panel input');
        q('#chat-bot-panel button[aria-label="Cerrar chat"]').click();
    } else if(caso==='depositos_documento') {
        await wait(()=>q('[data-documento-ingreso-listo]'));
        const root=q('[data-documento-ingreso-listo]'),link=root.querySelector('a[download]');
        checks.ready=!!link && root.textContent.includes('LOTE-');
        checks.automatic=root.getAttribute('x-init').includes('descargar.click()');
        checks.fallback=link.textContent.trim()==='Descargar documento de ingreso' && link.href.includes('/documento.pdf');
        checks.status=!!root.querySelector('[role=status]');
    } else if(caso==='depositos_firma') {
        await wait(()=>q('[data-ver-documento-firmado]'));
        const control=q('[data-ver-documento-firmado]'),group=control.parentElement;
        checks.left=control.getBoundingClientRect().left<=group.getBoundingClientRect().left+3;
        checks.label=control.textContent.trim()==='Ver documento firmado';
        control.click();await wait(()=>all('iframe').some(x=>x.title==='PDF completo de la solicitud de depósito' && x.getClientRects().length));
        const pdf=all('iframe').find(x=>x.title==='PDF completo de la solicitud de depósito' && x.getClientRects().length);
        checks.signedPreview=!new URL(pdf.src).searchParams.has('original') && pdf.src.includes('/documento.pdf');
        checks.frame=pdf.getBoundingClientRect().height>200;
    } else if(caso==='depositos_selector_anidado') {
        const button=label=>all('button').find(x=>x.textContent.trim()===label);
        const editorButton=all('button').find(x=>x.getAttribute('wire:click')==="abrirEditorManual('Grupo Animal')");
        if(!editorButton)throw Error('No existe el editor del grupo animal');
        editorButton.click();await wait(()=>q('dialog[data-modal="editar-dato-deposito"][open]'));
        q('#lov-valorEditorManual-boton').click();await wait(()=>q('#lov-valorEditorManual-dialogo[open]'));
        checks.nestedOpen=!!q('dialog[data-modal="editar-dato-deposito"][open]');
        const option=all('#lov-valorEditorManual-lista [role=option]').find(x=>x.textContent==='Insectos');option.click();
        await wait(()=>!q('#lov-valorEditorManual-dialogo[open]') && q('#lov-valorEditorManual-valor')?.textContent==='Insectos');
        await frame();
        checks.mouseKeepsEditor=!!q('dialog[data-modal="editar-dato-deposito"][open]');
        await wait(()=>document.activeElement?.id==='lov-valorEditorManual-boton');
        checks.focus=document.activeElement?.id==='lov-valorEditorManual-boton';
        button('Guardar').click();await wait(()=>!q('dialog[data-modal="editar-dato-deposito"][open]'));
        checks.saved=all('dd,p').some(x=>x.textContent.trim()==='Insectos');
        all('button').find(x=>x.getAttribute('wire:click')==="abrirEditorManual('Grupo Animal')").click();await wait(()=>q('dialog[data-modal="editar-dato-deposito"][open]'));
        q('#lov-valorEditorManual-boton').click();await wait(()=>q('#lov-valorEditorManual-dialogo[open]'));
        const search=q('#lov-valorEditorManual-buscar');search.value='Insectos';search.dispatchEvent(new Event('input',{bubbles:true}));await frame();
        search.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true,cancelable:true}));
        await wait(()=>!q('#lov-valorEditorManual-dialogo[open]'));
        checks.keyboardKeepsEditor=!!q('dialog[data-modal="editar-dato-deposito"][open]') && q('#lov-valorEditorManual-valor')?.textContent==='Insectos';
        button('Guardar').click();await wait(()=>!q('dialog[data-modal="editar-dato-deposito"][open]'));
    } else if(caso==='depositos_matriz') {
        await wait(()=>q('[wire\\:key^="matriz-estado-"]'));
        const buttons=()=>all('button').filter(x=>/^(Conservar nombre original|Usar )/.test(x.textContent.trim()));
        checks.compiled=buttons().length>1 && buttons().every(x=>!x.getAttribute('wire:click').includes('@js'));
        all('button').find(x=>x.textContent.trim()==='Ir al Paso 7 Envío').click();await frame();
        await wait(()=>document.body.textContent.includes('Busca y selecciona un taxón'));
        checks.pendingBlocked=!!q('input#matriz-dwc');
        q('details').open=true;
        buttons().find(x=>x.textContent.includes('Conservar nombre original')).click();
        await wait(()=>all('[wire\\:key^="matriz-estado-"] p').some(x=>x.textContent.includes('Validación Manual por Curaduría')));
        checks.original=true;
        buttons().find(x=>x.textContent.trim().startsWith('Usar ')).click();
        await wait(()=>buttons().length===0);
        checks.suggestion=all('[wire\\:key^="matriz-estado-"] p').some(x=>x.textContent.includes('Corregido por Sugerencia'));
        all('button').find(x=>x.textContent.trim()==='Ir al Paso 7 Envío').click();
        await wait(()=>document.body.textContent.includes('Declaración'));
        checks.ready=!q('input#matriz-dwc');
    } else if(caso==='depositos_origen') {
        await wait(()=>q('#finalidad-actividad'));
        const purpose=q('#finalidad-actividad');
        purpose.value='Consultoría';purpose.dispatchEvent(new Event('change',{bubbles:true}));
        await wait(()=>!q('[data-situacion]') && document.body.textContent.includes('La consultoría requiere permisos'));
        checks.purpose=[...purpose.options].some(x=>x.value==='Investigación') && [...purpose.options].some(x=>x.value==='Consultoría');
        checks.policy=!q('[data-situacion]') && document.body.textContent.includes('La consultoría requiere permisos de recolección y movilización.');
        checks.required=purpose.required && q('#lov-localidadOrigenCodigo-boton')?.getAttribute('aria-required')==='true';
        q('#lov-localidadOrigenCodigo-boton').click();await wait(()=>q('#lov-localidadOrigenCodigo-dialogo[open]'));
        const search=q('#lov-localidadOrigenCodigo-buscar');search.value='QA ARQ INEC';search.dispatchEvent(new Event('input',{bubbles:true}));
        await wait(()=>all('#lov-localidadOrigenCodigo-lista [role=option]').some(x=>x.textContent.includes('QA ARQ INEC')));
        all('#lov-localidadOrigenCodigo-lista [role=option]').find(x=>x.textContent.includes('QA ARQ INEC')).click();
        await wait(()=>!q('#lov-localidadOrigenCodigo-dialogo[open]') && q('#localidad-recoleccion')?.value.startsWith('QA-ARQ-UI'));
        await frame();
        checks.inec=q('#localidad-recoleccion').value.startsWith('QA-ARQ-UI') && q('#lov-localidadOrigenCodigo-valor').textContent.includes('QA ARQ INEC');
        checks.focus=document.activeElement?.id==='lov-localidadOrigenCodigo-boton';
        q('[data-origen-continuar]').click();await wait(()=>!q('#finalidad-actividad') && document.body.textContent.includes('Copia de la autorización'));
        checks.saved=true;
    } else if(caso==='depositos_rechazo') {
        await wait(()=>q('[data-validar-ingreso-qr]'));
        all('button').find(x=>x.textContent.trim()==='No recibir entrega').click();await wait(()=>model('motivo','textarea'));
        checks.reason=model('motivo','textarea').required;
        fillModel('motivo','QA: caja con material distinto; coordinar entrega corregida.');
        all('button').find(x=>x.textContent.trim()==='Confirmar entrega no recibida').click();
        await wait(()=>q('[data-validar-ingreso-qr]').textContent.includes('Entrega no recibida') && !all('button').some(x=>x.textContent.trim()==='Recibir material'));
        checks.refused=q('[data-validar-ingreso-qr]').textContent.includes('Se requiere autorización del Curador');
        checks.blocked=!all('button').some(x=>x.textContent.trim()==='Recibir material');
        checks.history=!!all('details li').find(x=>x.textContent.includes('caja con material distinto'));
        checks.technicalCannotRetry=!all('button').some(x=>x.textContent.trim()==='Autorizar nuevo intento');
    } else if(caso==='depositos_reintento') {
        await wait(()=>all('button').some(x=>x.textContent.trim()==='Autorizar nuevo intento'));
        checks.curator=!!model('motivo','textarea') && !all('button').some(x=>x.textContent.trim()==='Recibir material');
        fillModel('motivo','QA: Curador autoriza la entrega corregida.');
        all('button').find(x=>x.textContent.trim()==='Autorizar nuevo intento').click();await wait(()=>all('button').some(x=>x.textContent.trim()==='Recibir material'));
        checks.authorized=!q('[data-validar-ingreso-qr]').textContent.includes('Se requiere autorización del Curador');
        checks.history=all('details li').some(x=>x.textContent.includes('caja con material distinto')) && all('details li').some(x=>x.textContent.includes('Curador autoriza'));
        checks.receiveEnabled=all('button').find(x=>x.textContent.trim()==='Recibir material')?.disabled===false;
    } else if(caso==='depositos_cerrado') {
        await wait(()=>document.body.textContent.includes('Finalizada · acta firmada'));
        checks.closed=document.body.textContent.includes('Finalizada · acta firmada');
        checks.noIntake=!all('button,a').some(x=>/^(Anunciar entrega|Actualizar entrega|Recibir material|Validar documento de ingreso)$/.test(x.textContent.trim()));
        checks.documents=!!all('a').find(x=>x.textContent.trim()==='Consultar documento de ingreso del expediente');
        checks.status=!!q('[data-estado-aviso-ingreso]');
    } else if(caso==='depositos_ingreso') {
        await wait(()=>q('[data-validar-ingreso-qr]'));
        const root=q('[data-validar-ingreso-qr]');
        checks.authentic=root.textContent.includes('QR auténtico') && root.textContent.includes('LOTE-');
        checks.characteristics=['Titular','Proyecto','Institución','Lotes','Individuos'].every(t=>all('dt').some(x=>x.textContent===t));
        [...root.querySelectorAll('button')].find(x=>x.textContent.trim()==='No recibir entrega').click();await wait(()=>model('motivo','textarea'));
        checks.reason=model('motivo','textarea').required && all('label,[data-flux-label]').some(x=>x.textContent.includes('Motivo de no recepción'));
        [...root.querySelectorAll('button')].find(x=>x.textContent.trim()==='Cancelar').click();await wait(()=>!model('motivo','textarea'));
        all('button').find(x=>x.textContent.trim()==='Recibir material').click();await wait(()=>root.textContent.includes('pendiente de validar contenido'));
        checks.received=!!all('a').find(x=>x.textContent.trim()==='Registrar y validar contenido');
        checks.noDuplicate=!all('button').some(x=>x.textContent.trim()==='Recibir material');
    } else if(caso==='depositos_contenido') {
        await wait(()=>q('[data-contenido-recepcion]'));
        const fill=fillModel;
        fill('identificador','QA-RECIPIENTE-UI');fill('cantidad','2');fill('scientificName','Neoponera apicalis');fill('recordNumber','QA0001');
        all('button').find(x=>x.textContent.trim()==='Guardar registro validado').click();await wait(()=>all('tbody tr').some(x=>x.textContent.includes('QA-RECIPIENTE-UI')));
        checks.tabulated=all('tbody tr').some(x=>x.textContent.includes('Neoponera apicalis') && x.textContent.includes('2'));
        for(const checkbox of q('[data-contenido-recepcion]').querySelectorAll('[role=checkbox]')) if(checkbox.getAttribute('aria-checked')!=='true') checkbox.click();
        all('button').find(x=>x.textContent.trim()==='Marcar recibida y validada').click();await wait(()=>q('[data-contenido-recepcion]').textContent.includes('Contenido cerrado.'));
        checks.validated=q('[data-contenido-recepcion]').textContent.includes('Recibida y validada');
        checks.closed=!all('button').some(x=>x.textContent.trim()==='Guardar registro validado' || x.textContent.trim()==='Marcar recibida y validada');
    } else if(caso==='captura_dwc') {
        await wait(()=>q('[data-captura-dwc]'));
        all('button').find(x=>x.textContent.trim()==='Nuevo registro').click();await wait(()=>model('datos.taxonNombre'));
        const models=all('input').map(x=>x.getAttribute('wire:model')).filter(Boolean);
        checks.reserved=!models.some(x=>/occurrenceId|occurrenceID|recordCreatedBy|iptUpload|estadoRevision|responsibleResearcherExport/.test(x));
        const fill=(key,value)=>fillModel('datos.'+key,value);
        fill('taxonNombre','Neoponera apicalis');fill('colector','Colector QA de captura');fill('country','Ecuador');
        all('button').find(x=>x.textContent.trim()==='Guardar datos').click();await wait(()=>q('[data-captura-dwc] [role=status]')?.textContent.includes('Datos Darwin Core guardados'));
        checks.saved=true;
        checks.labels=all('input').filter(x=>x.getAttribute('wire:model')?.startsWith('datos.')).every(x=>x.labels?.length>0 || x.getAttribute('aria-label') || (x.getAttribute('aria-labelledby')?.split(' ').every(id=>document.getElementById(id)?.textContent.trim())));
    } else if(caso==='perfiles_catalogo') {
        await wait(()=>q('[data-menu-lateral-arbol]'));
        checks.pantalla = location.pathname === '/administracion/menu-lateral' && new URL(location.href).searchParams.get('rol') === 'todas';
        const catalogo = q('[aria-controls="menu-hijos-catalogo"]');
        if(catalogo.getAttribute('aria-expanded')!=='true') catalogo.click();
        await wait(()=>catalogo.querySelector('span[aria-hidden="true"]')?.classList.contains('rotate-90'));
        checks.indicador=true;
        const abrirEdicion = async clave => {
            const fila=q('[data-menu-nodo="'+clave+'"]');
            fila.querySelector('[aria-haspopup="menu"]').click();
            const editar=[...fila.querySelectorAll('[role="menuitem"]')].find(elemento=>elemento.textContent.trim()==='Editar');
            await wait(()=>editar.getClientRects().length);
            editar.click();
            await wait(()=>q('dialog[open] fieldset'));
            return q('dialog[open]');
        };
        const elegirPasante = async dialogo => {
            const rol=dialogo.querySelector('[value="PASANTE"]');
            if(rol.getAttribute('aria-checked')!=='true' && !rol.checked) rol.click();
            await wait(()=>[...q('dialog[open]').querySelectorAll('legend')].some(leyenda=>leyenda.textContent.trim()==='Funciones de Pasante'));
            return q('dialog[open]');
        };
        const cancelar = async dialogo => {
            [...dialogo.querySelectorAll('button')].find(boton=>boton.textContent.trim()==='Cancelar').click();
            await wait(()=>!q('dialog[open]'));
        };
        let dialogo=await elegirPasante(await abrirEdicion('especimenes'));
        checks.consulta=dialogo.textContent.includes('Consultar especímenes');
        checks.edicion=dialogo.textContent.includes('Crear especímenes') && dialogo.textContent.includes('Editar especímenes');
        await cancelar(dialogo);
        dialogo=await elegirPasante(await abrirEdicion('importar-catalogo'));
        checks.validacion=dialogo.textContent.includes('Validar matrices');
        checks.importacion=dialogo.textContent.includes('Importar al catálogo');
        checks.rolesActuales=!!dialogo.querySelector('[value="DEPOSITANTE"]');
        checks.dialogo=dialogo.scrollWidth<=dialogo.clientWidth+1;
        await cancelar(dialogo);
        const prestamos = q('[aria-controls="menu-hijos-prestamos-equipo"]');
        if(prestamos.getAttribute('aria-expanded')!=='true') prestamos.click();
        dialogo=await elegirPasante(await abrirEdicion('solicitudes'));
        checks.registroPrestamos=dialogo.textContent.includes('Revisar solicitudes de préstamo');
        await cancelar(dialogo);
        dialogo=await elegirPasante(await abrirEdicion('usuarios'));
        checks.administracion=dialogo.textContent.includes('Abrir la pantalla')
            && ![...dialogo.querySelectorAll('input')].some(input=>input.name?.startsWith('permisos.'));
        await cancelar(dialogo);
        checks.cancelado=!q('dialog[open]');
    } else throw Error('Contrato desconocido.');
    if(caso.startsWith('depositos_') || caso==='captura_dwc') checks.responsive=document.documentElement.scrollWidth<=document.documentElement.clientWidth+1;
    return {caso,checks,metrics};
}
