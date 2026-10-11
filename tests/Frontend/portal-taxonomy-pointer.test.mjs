import test from 'node:test';
import assert from 'node:assert/strict';
import {crearConsultaPuntero} from '../../resources/js/portal-taxonomy-pointer.js';

function entorno() {
    const frames = new Map(), consultas = [], vistos = []; let id = 0;
    const puntero = crearConsultaPuntero({
        programar: fn => {frames.set(++id, fn); return id;}, cancelar: id => frames.delete(id),
        consultar: posicion => new Promise(resolve => consultas.push({posicion, resolve})), mostrar: i => vistos.push(i),
    });
    return {puntero, frames, consultas, vistos, siguiente() {const [id, fn] = frames.entries().next().value; frames.delete(id); return fn();}};
}

test('señalar una especie al detener el ratón consulta la última posición, sin duplicar solicitudes pendientes', async () => {
    const e = entorno();
    e.puntero.mover({clientX: 1, clientY: 2}); const primera = e.siguiente();
    for (let i = 3; i <= 20; i++) e.puntero.mover({clientX: i, clientY: 8});
    assert.equal(e.consultas.length, 1); assert.equal(e.frames.size, 0);
    e.consultas[0].resolve(1); await primera;
    assert.deepEqual(e.vistos, []); assert.equal(e.frames.size, 1);
    const ultima = e.siguiente();
    assert.deepEqual(e.consultas[1].posicion, {clientX: 20, clientY: 8});
    e.consultas[1].resolve(9); await ultima;
    assert.deepEqual(e.vistos, [9]); assert.equal(e.frames.size, 0);
});

test('salir del canvas o cambiar de raíz descarta la respuesta de la rama anterior y conserva un movimiento nuevo', async () => {
    const e = entorno(); e.puntero.mover({clientX: 1, clientY: 2}); const anterior = e.siguiente();
    e.puntero.cancelar(); e.puntero.mover({clientX: 30, clientY: 40});
    e.consultas[0].resolve(1); await anterior; assert.deepEqual(e.vistos, []);
    const actual = e.siguiente(); e.consultas[1].resolve(7); await actual;
    assert.deepEqual(e.vistos, [7]);
});

test('destruir el visor cancela fotogramas y no aplica consultas que terminan después', async () => {
    const e = entorno(); e.puntero.mover({clientX: 1, clientY: 2}); const tarea = e.siguiente();
    e.puntero.mover({clientX: 30, clientY: 40}); e.puntero.destruir();
    e.consultas[0].resolve(1); await tarea; e.puntero.mover({clientX: 50, clientY: 60});
    assert.deepEqual(e.vistos, []); assert.equal(e.frames.size, 0); assert.equal(e.consultas.length, 1);
});
