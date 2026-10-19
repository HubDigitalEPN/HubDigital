import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
const source = await readFile(new URL('../../public/js/interactive-grid.js', import.meta.url), 'utf8');
function grid(extra = {}) {
    const rows = Array.from({length: 42}, (_, i) => {
        const cells = ['Registro '+(i+1), i === 40 ? 'Taxón único' : 'Taxón general'].map(innerText => ({innerText, hidden: false, colSpan: 1}));
        return {cells, children: cells, hidden: false, textContent: cells.map(c=>c.innerText).join(' '), querySelector: () => null};
    });
    const body = {rows, append(row) { const i=this.rows.indexOf(row); this.rows.splice(i,1); this.rows.push(row); }};
    const watches = {}, window = {};
    vm.runInNewContext(source, {window, MutationObserver: class {observe() {} disconnect() {}}, Intl, ...extra});
    const state = window.hubInteractiveGrid(['Registro', 'Taxón'], false, 'Prueba', true);
    state.$el = {querySelector: selector => selector === 'table tbody' ? body : {}, querySelectorAll: selector => selector === 'table tr' ? rows : []};
    state.$watch = (key, fn) => watches[key]=fn;
    state.$refs = {actionMenu: {}, actionsButton: {focus() {}}, columnsDialog: {close() {}}, sortDialog: {close() {}}};
    state.$wire = new Proxy({}, {get() { throw new Error('La grilla local no debe invocar al servidor'); }});
    state.init();
    return {state, rows, watches, visible: () => rows.filter(r=>!r.hidden)};
}
test('la búsqueda abarca las filas no visibles y el paginado conserva todos los registros', () => {
    const g=grid();
    assert.equal(g.visible().length,15); assert.equal(g.state.clientLastPage,3);
    g.state.clientGo(3); assert.equal(g.visible().length,12);
    g.state.searchText='UNICO'; g.state.searchPage();
    assert.equal(g.visible().length,1); assert.equal(g.visible()[0].cells[0].innerText,'Registro 41');
    assert.equal(g.state.clientPage,1); assert.equal(g.state.clientTotal,1);
    g.state.reset(); assert.equal(g.rows.length,42); assert.equal(g.visible().length,15);
});
test('el menú espera a ser visible, admite Fin y Escape y queda dentro de una pantalla estrecha', () => {
    const frames=[], document={activeElement: null};
    const g=grid({document, requestAnimationFrame: fn=>frames.push(fn), innerWidth:390, innerHeight:740});
    const buttons=['Columnas','Restablecer'].map(textContent=>({textContent, focus() {document.activeElement=this;}, getClientRects:()=>[{}]}));
    let opened=false;
    const menu={style:{},offsetWidth:264,offsetHeight:284,showPopover() {opened=true;},hidePopover() {opened=false;},matches:()=>opened,querySelector:()=>buttons[0],querySelectorAll:()=>buttons};
    const trigger={getBoundingClientRect:()=>({left:350,bottom:720}),focus() {document.activeElement=this;}};
    g.state.$refs.actionMenu=menu;g.state.$refs.actionsButton=trigger;g.state.$nextTick=fn=>fn();
    g.state.openMenu();assert.equal(document.activeElement,null);frames.shift()();
    assert.equal(document.activeElement,buttons[0]);
    assert.ok(parseFloat(menu.style.left)+264<=382 && parseFloat(menu.style.top)+284<=732);
    const event=key=>({key,preventDefault() {},stopPropagation() {}});
    g.state.menuKey(event('End'));assert.equal(document.activeElement,buttons[1]);
    g.state.menuKey(event('Escape'));assert.equal(opened,false);assert.equal(document.activeElement,trigger);
});
test('ordenar es numérico y estable; cambiar filas por página y restaurar no pierde selección ni datos', async () => {
    const g=grid(); g.rows[0].selected=true;
    g.state.sortField='0'; g.state.sortDirection='desc'; await g.state.applySort();
    assert.equal(g.visible()[0].cells[0].innerText,'Registro 42');
    g.state.clientGo(3); g.state.clientPerPage=25; g.watches.clientPerPage();
    assert.equal(g.state.clientPage,1); assert.equal(g.visible().length,25);
    g.state.reset(); assert.equal(g.visible()[0].cells[0].innerText,'Registro 1');
    assert.equal(g.visible()[0].selected,true); assert.equal(g.rows.length,42);
    g.state.hidden=[1]; g.state.applyColumns();
    assert.equal(g.rows.every(row=>row.cells[1].hidden),true);
});
