import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';

const source = readFileSync(new URL('../../public/js/laboratorio-layout.js', import.meta.url), 'utf8');

test('la maqueta del laboratorio conserva su DOM y abre textos y fotos por doble clic o Intro', () => {
    const calls = [], events = {}, window = {prepararPortalEditorial() {}};
    const elemento = id => ({dataset: {labElement: id}, closest(selector) {return selector === '[data-lab-element]' ? this : null;}});
    const titulo = elemento('texto-titulo'), foto = elemento('foto-adrian'), article = {dataset: {}};
    const doc = {
        querySelector(selector) {return selector === '.lab-page' ? article : null;},
        querySelectorAll(selector) {return selector === '[data-lab-free]' ? [] : [titulo, foto];},
        addEventListener(name, handler) {events[name] = handler;},
    };
    runInNewContext(source, {window, document: {addEventListener() {}}, clearTimeout});
    const editor = window.laboratorioEditor({
        textos: {titulo: 'El Laboratorio'},
        seleccionarElemento: id => calls.push(['texto', id]),
        seleccionarFoto: id => calls.push(['foto', id]),
    });
    const modales = [];
    editor.$flux = {modal: nombre => ({show: () => modales.push(nombre)})};
    const frame = {contentDocument: doc, inert: true, style: {}};
    editor.$refs = {vista: frame};
    editor.iniciar();
    const activar = (target, key) => ({target, key, preventDefault() {}, stopImmediatePropagation() {}});
    events.dblclick(activar(titulo));
    events.keydown(activar(foto, 'Enter'));
    events.keydown(activar(foto, 'ArrowRight'));
    assert.equal(editor.textoLocal, 'El Laboratorio');
    assert.deepEqual(modales, ['laboratorio-texto-inmediato']);
    assert.deepEqual(calls, [['foto', 'foto-adrian']]);
    assert.deepEqual(Object.keys(events).sort(), ['click', 'dblclick', 'keydown', 'submit']);
    assert.equal(titulo.tabIndex, 0);
    assert.equal(foto.tabIndex, 0);
    assert.equal(article.dataset.labEditorReady, 'true');
    assert.equal(frame.inert, false);
    assert.equal(editor.posiciones, undefined);
});

test('leer más desplaza los elementos inferiores y móvil usa altura automática', () => {
    const events = {}, window = {innerWidth: 1200, addEventListener(name, fn) {events[name] = fn;}, ResizeObserver: class {observe() {}}};
    const item = (y, height) => ({dataset: {labY: y}, style: {}, offsetHeight: height,
        get offsetTop() {return Number.parseFloat(this.style.top) || 0;}, hasAttribute() {return false;},
        querySelector() {return this.open || false;}, getBoundingClientRect() {return {bottom: this.offsetTop + this.offsetHeight};}});
    const text = item(0, 100), photo = item(20, 100);
    const document = {defaultView: window, querySelectorAll() {return [canvas];}, addEventListener(name, fn) {events[name] = fn;}};
    const canvas = {isConnected: true, dataset: {}, style: {}, children: [text, photo], clientWidth: 1000, ownerDocument: document,
        addEventListener(name, fn) {events[name] = fn;}, getBoundingClientRect() {return {top: 0};}};
    runInNewContext(source, {window, document});
    events.DOMContentLoaded(); assert.equal(photo.style.top, '200px');
    text.open = true; text.offsetHeight = 180; events.toggle();
    assert.equal(photo.style.top, '280px'); assert.equal(canvas.style.height, '412px');
    window.innerWidth = 430; events.resize(); assert.equal(canvas.style.height, 'auto');
});

test('los perfiles se abren después de navegar y el cierre devuelve el foco al botón', () => {
    const events={}, window={}, opened=[];
    const opener={dataset:{labOpenProfile:'perfil-prueba'},focus(options){opened.push(options.preventScroll?'focus':'scroll');}};
    const modal={open:false,showModal(){this.open=true;},close(){this.open=false;events.close({target:this});},
        matches(){return true;},getBoundingClientRect(){return {left:10,right:100,top:10,bottom:100};}};
    const document={addEventListener(name,fn){events[name]=fn;},getElementById(id){assert.equal(id,'perfil-prueba');return modal;}};
    runInNewContext(source,{window,document});
    events.click({target:{closest(selector){return selector==='[data-lab-open-profile]'?opener:null;}}});
    assert.equal(modal.open,true);
    // Un clic sobre el contenido no cierra; uno fuera del rectángulo sí.
    events.click({target:{closest(){return null;}}});
    events.click({target:Object.assign(modal,{closest(){return null;}}),clientX:2,clientY:2});
    assert.equal(modal.open,false);assert.deepEqual(opened,['focus']);
});
