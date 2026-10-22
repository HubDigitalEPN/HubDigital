import test from 'node:test';
import assert from 'node:assert/strict';
import {distribuirEtiquetasTaxonomicas} from '../../resources/js/portal-taxonomy-label-layout.js';

test('las etiquetas densas no tapan yemas, otras etiquetas ni la cabecera y conservan su unión',()=>{
    const yemas=Array.from({length:16},(_,i)=>({i,x:180+(i%4)*62,y:130+Math.floor(i/4)*72,tamano:14}));
    const reservada={x:0,y:0,w:640,h:55};
    const etiquetas=distribuirEtiquetasTaxonomicas(yemas.map(p=>({...p,w:120})),yemas,700,600,[reservada]);
    assert.ok(etiquetas.length>=8);
    for(const [i,a] of etiquetas.entries()){
        assert.ok(a.y>=60&&a.y+a.h<=594&&a.x>=6&&a.x+a.w<=694);
        for(const b of yemas)assert.ok(Math.hypot(b.x-Math.max(a.x,Math.min(a.x+a.w,b.x)),b.y-Math.max(a.y,Math.min(a.y+a.h,b.y)))>=b.tamano+7);
        for(const b of etiquetas.slice(i+1))assert.ok(a.x+a.w<=b.x||b.x+b.w<=a.x||a.y+a.h<=b.y||b.y+b.h<=a.y);
        const yema=yemas[a.i];
        assert.ok(Math.abs(Math.hypot(a.inicio.x-yema.x,a.inicio.y-yema.y)-yema.tamano)<1e-8);
        assert.ok(a.extremo.x===a.x||a.extremo.x===a.x+a.w||a.extremo.y===a.y||a.extremo.y===a.y+a.h);
    }
});
