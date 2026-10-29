import test from 'node:test';
import assert from 'node:assert/strict';
import {alturaDisponibleRegistros, crearRegistrosInfinitos} from '../../resources/js/portal-record-scroll.js';
test('el borde inferior coincide con la ventana en escalas 50 75 100 y 125 por ciento', () => {
    for (const scale of [.5,.75,1,1.25]) {
        const rect={width:1200*scale,top:180*scale};
        const h=alturaDisponibleRegistros(rect,1200,900);
        assert.ok(Math.abs(rect.top+h*scale-900)<.01);
    }
    assert.equal(alturaDisponibleRegistros({width:100,top:1100},100,900),0);
});
test('el scroll solicita una vez y conserva ambas posiciones tras incorporar filas', async () => {
    const previous=globalThis.requestAnimationFrame;globalThis.requestAnimationFrame=fn=>fn();
    try {
        let finish,calls=0;
        const s=crearRegistrosInfinitos();s.raiz={isConnected:true,dataset:{seleccion:'filtro',paginas:'1',hayMas:'true'}};
        s.tabla={scrollTop:540,scrollLeft:230};s.$nextTick=async()=>{};
        s.$wire={cargarMasRegistros:async(key,page)=>{calls++;assert.equal(key,'filtro');assert.equal(page,1);await new Promise(r=>finish=r);s.tabla.scrollTop=0;s.tabla.scrollLeft=0;}};
        const pending=s.cargarMas();await s.cargarMas();assert.equal(calls,1);finish();await pending;
        assert.equal(s.tabla.scrollTop,540);assert.equal(s.tabla.scrollLeft,230);assert.equal(s.solicitando,false);
    } finally {globalThis.requestAnimationFrame=previous;}
});
test('el fallo frena la carga automática y no se piden lotes cuando ya no hay más', async () => {
    let calls=0;const s=crearRegistrosInfinitos();s.raiz={isConnected:true,dataset:{seleccion:'a',paginas:'1',hayMas:'true'}};
    s.tabla={clientHeight:100,scrollHeight:100,scrollTop:0};s.$wire={cargarMasRegistros:async()=>{calls++;throw Error('Sin red');}};
    await s.cargarMas();assert.match(s.errorCarga,/No se pudieron/);s.comprobarCarga();assert.equal(calls,1);
    s.raiz.dataset.hayMas='false';await s.cargarMas();assert.equal(calls,1);
});
