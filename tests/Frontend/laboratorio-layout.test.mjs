import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';

const source = readFileSync(new URL('../../public/js/laboratorio-layout.js', import.meta.url), 'utf8');

test('el editor conserva un mapa serializable y limita geometría antes de sincronizar', () => {
    const window = {}, calls = [];
    runInNewContext(source, {window, document: {addEventListener() {}}});
    const editor = window.laboratorioEditor({set(...args) {calls.push(args);}}, [], {foto: {tipo: 'foto'}});
    editor.posiciones.foto = {x: 95, y: -10, ancho: 40, alto: 0, fuente: 20, capa: 22};
    editor.seleccion = 'foto'; editor.pintar = () => {};
    editor.cambiar();
    assert.deepEqual(JSON.parse(JSON.stringify(editor.posiciones)), {foto: {x: 60, y: 0, ancho: 40, alto: 1, fuente: 8, capa: 20}});
    assert.equal(calls[0][0], 'posiciones'); assert.equal(calls[0][2], false);
    assert.equal(calls[0][1].foto.x, 60);
    editor.restaurar();
    assert.deepEqual(JSON.parse(JSON.stringify(editor.posiciones)), {});
    assert.deepEqual(JSON.parse(JSON.stringify(calls.at(-1))), ['posiciones', {}, false]);
});

test('leer más desplaza los elementos inferiores y móvil usa altura automática', () => {
    const events = {}, window = {innerWidth: 1200, addEventListener(name, fn) {events[name] = fn;}};
    const item = (y, height) => ({dataset: {labY: y}, style: {}, offsetHeight: height,
        get offsetTop() {return Number.parseFloat(this.style.top) || 0;}, hasAttribute() {return false;},
        querySelector() {return this.open || false;}, getBoundingClientRect() {return {bottom: this.offsetTop + this.offsetHeight};}});
    const text = item(0, 100), photo = item(20, 100);
    const canvas = {isConnected: true, dataset: {}, style: {}, children: [text, photo], clientWidth: 1000,
        closest() {return null;}, addEventListener(name, fn) {events[name] = fn;}, getBoundingClientRect() {return {top: 0};}};
    runInNewContext(source, {window, document: {querySelectorAll() {return [canvas];}, addEventListener(name, fn) {events[name] = fn;}}, ResizeObserver: class {observe() {}}});
    events.DOMContentLoaded(); assert.equal(photo.style.top, '200px');
    text.open = true; text.offsetHeight = 180; events.toggle();
    assert.equal(photo.style.top, '280px'); assert.equal(canvas.style.height, '412px');
    window.innerWidth = 430; events.resize(); assert.equal(canvas.style.height, 'auto');
});
