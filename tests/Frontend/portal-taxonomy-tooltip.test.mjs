import test from 'node:test';
import assert from 'node:assert/strict';
import {crearConsultaLeyenda} from '../../resources/js/portal-taxonomy-tooltip.js';

function entorno(){
    const timers=new Map(),peticiones=[],vistos=[];let siguiente=0;
    const consulta=crearConsultaLeyenda({programar:fn=>{timers.set(++siguiente,fn);return siguiente;},cancelar:id=>timers.delete(id),
        solicitar:(nodo,signal)=>new Promise((resolve,reject)=>peticiones.push({nodo,signal,resolve,reject})),mostrar:texto=>vistos.push(texto)});
    return{consulta,timers,peticiones,vistos,iniciar(){const[id,fn]=timers.entries().next().value;timers.delete(id);return fn();}};
}

test('un movimiento rápido sólo solicita la leyenda del último taxón, una vez',async()=>{
    const e=entorno();e.consulta.consultar({clave:'araña',id:'a'});e.consulta.consultar({clave:'escarabajo',id:'b'});e.consulta.consultar({clave:'escarabajo',id:'b'});
    assert.equal(e.timers.size,1);const trabajo=e.iniciar();assert.equal(e.peticiones.length,1);assert.equal(e.peticiones[0].nodo.id,'b');
    e.peticiones[0].resolve('Leyenda del escarabajo.');await trabajo;assert.deepEqual(e.vistos,['Leyenda del escarabajo.']);
});

test('salir o cambiar de esfera descarta la respuesta anterior aunque el transporte ignore abort',async()=>{
    const e=entorno();e.consulta.consultar({clave:'a'});const anterior=e.iniciar();
    e.consulta.consultar({clave:'b'});assert.equal(e.peticiones[0].signal.aborted,true);const actual=e.iniciar();
    e.peticiones[0].resolve('No debe aparecer');await anterior;assert.deepEqual(e.vistos,[]);
    e.peticiones[1].resolve('Leyenda vigente');await actual;assert.deepEqual(e.vistos,['Leyenda vigente']);
    e.consulta.cancelar();e.consulta.consultar({clave:'c'});const tardia=e.iniciar();e.consulta.destruir();e.peticiones[2].resolve('Cerrado');await tardia;
    assert.deepEqual(e.vistos,['Leyenda vigente']);assert.equal(e.peticiones[2].signal.aborted,true);
});

test('una ficha no publicada o no disponible no sustituye la selección ni deja un rechazo sin manejar',async()=>{
    const e=entorno();e.consulta.consultar({clave:'a'});const trabajo=e.iniciar();e.peticiones[0].reject(new Error('404'));await trabajo;assert.deepEqual(e.vistos,['']);
    e.consulta.consultar({clave:'b'});const largo=e.iniciar();e.peticiones[1].resolve('x'.repeat(1000));await largo;assert.equal(e.vistos.at(-1).length,800);
});
