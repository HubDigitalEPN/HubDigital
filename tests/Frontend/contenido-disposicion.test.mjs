import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';
const source=readFileSync(new URL('../../public/js/contenido-disposicion.js',import.meta.url),'utf8');
function runtime(){const window={};runInNewContext(source,{window,document:{readyState:'loading',addEventListener(){}},getComputedStyle:()=>({display:'block'})});return window;}
function tree(){
    const parent={children:[]};
    function node(id){return {dataset:{disposicionId:id},parentElement:parent,before(slot){slot.parentElement=parent;parent.children.splice(parent.children.indexOf(this),0,slot);},replaceWith(other){const previous=parent.children.indexOf(other);if(previous>=0)parent.children.splice(previous,1);parent.children.splice(parent.children.indexOf(this),1,other);}};}
    const a=node('a'),b=node('b'),c=node('c'),fixed=node('fixed');parent.children=[a,fixed,b,c];
    a.ownerDocument=b.ownerDocument=c.ownerDocument={createComment:()=>node('slot')};return {parent,items:[a,b,c]};
}
test('reorganizar conserva nodos valores y objetos ajenos en sus posiciones',()=>{
    const w=runtime(),g=tree(),original=g.items.slice();w.hubDisposicionContenido.arrange(g,['c','b','a']);
    assert.deepEqual(g.parent.children.map(n=>n.dataset.disposicionId),['c','fixed','b','a']);
    assert.ok(original.every(n=>g.parent.children.includes(n)));
    w.hubDisposicionContenido.arrange(g,['a','b','c']);assert.deepEqual(g.parent.children.map(n=>n.dataset.disposicionId),['a','fixed','b','c']);
});
test('una configuración con identidades obsoletas no pierde ni duplica objetos',()=>{
    const w=runtime(),g=tree();w.hubDisposicionContenido.arrange(g,['unknown','c','c']);
    assert.deepEqual(g.parent.children.map(n=>n.dataset.disposicionId),['c','fixed','a','b']);
});
test('la vista aplica la posición publicada desde su documento sin controles de movimiento', () => {
    const w = runtime(), estilos = new Map();
    const posicion = {dataset: {disposicionLibre: 'inicio-hero-accion'}, style: {
        setProperty: (nombre, valor) => estilos.set(nombre, valor),
        removeProperty: nombre => estilos.delete(nombre),
    }};
    const root = {
        children: [],
        closest: () => null,
        querySelectorAll: selector => selector === '[data-disposicion-libre]' ? [posicion] : [],
        ownerDocument: {getElementById: id => id === 'disposicion-contenido-datos'
            ? {textContent: '{"portal.inicio":{"posiciones":{"inicio-hero-accion":20}}}'} : null},
    };
    w.hubDisposicionContenido.aplicarGuardada(root, 'portal:inicio');
    assert.equal(estilos.get('--hub-posicion-horizontal'), '20%');
    assert.equal(w.editorDisposicionContenido, undefined);
});

test('Cargo pierde solo la compensacion de su posicion original al reorganizar el wizard',()=>{
    const w=runtime(),g=tree(),values=[];const cargo=g.items[2];cargo.matches=s=>s==='.hub-request-cell--cargo';cargo.style={setProperty:(...args)=>values.push(args)};
    w.hubDisposicionContenido.arrange(g,['c','a','b']);assert.equal(values.length,0);
    g.wizard=true;w.hubDisposicionContenido.arrange(g,['c','a','b']);assert.deepEqual(values,[['--hub-cargo-expansion','0rem']]);
    assert.deepEqual(g.parent.children.map(n=>n.dataset.disposicionId),['c','fixed','a','b']);
});
