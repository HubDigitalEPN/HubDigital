import test from 'node:test';
import assert from 'node:assert/strict';
import {nombreDescargaImagen, crearVisorImagen} from '../../resources/js/portal-image-model.js';

test('la descarga conserva PNG, WebP y AVIF desde el nombre registrado de la fotografía', () => {
    for (const nombre of ['hormiga.PNG', 'ejemplar.webp', 'detalle.avif', 'muestra.jpeg']) {
        assert.equal(nombreDescargaImagen(nombre), nombre);
    }
});

test('un metadato ausente no inventa una extensión JPEG', () => {
    for (const nombre of [null, undefined, '', '   ', '.', '..', false, {}]) {
        assert.equal(nombreDescargaImagen(nombre), 'imagen');
    }
});

test('el nombre de descarga elimina rutas y caracteres de control sin cambiar el formato', () => {
    assert.equal(nombreDescargaImagen('../../fotos/hormiga.webp'), 'hormiga.webp');
    assert.equal(nombreDescargaImagen('C:\\fotos\\hormiga.PNG'), 'hormiga.PNG');
    assert.equal(nombreDescargaImagen('hormiga\u0000:detalle.avif'), 'hormiga__detalle.avif');
});

test('cerrar desde el botón y Escape opera sobre el diálogo original y devuelve el foco', () => {
    const dialog = {open:false,showModal(){this.open=true;},close(){this.open=false;}};
    let focus = '';
    const state=crearVisorImagen();state.$el=dialog;state.$nextTick=fn=>fn();state.$refs={cerrar:{focus:()=>focus='cerrar'}};state.init();
    const trigger={isConnected:true,focus:()=>focus='foto'};
    state.abrir({url:'/images/ant.webp',invocador:trigger,atribuciones:{photographer:'Autora',view:'Dorsal'}});
    assert.equal(dialog.open,true);assert.equal(focus,'cerrar');assert.equal(state.vista,'Dorsal');
    state.$el={tagName:'BUTTON'};state.cerrar();assert.equal(dialog.open,false);
    state.restaurarFoco();assert.equal(focus,'foto');
});
