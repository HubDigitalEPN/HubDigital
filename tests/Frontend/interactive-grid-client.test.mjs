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

test('mover y ocultar columnas solo cambia su posición visual; conserva controles y DOM canónico', () => {
    const g = grid();
    g.rows.forEach(row => { row.cells.forEach(cell => cell.style = {}); });
    const before = [...g.rows[0].cells];
    g.state.moveColumn(1, -1);
    assert.deepEqual([...g.state.columnOrder], [1, 0]);
    assert.equal(g.rows[0].cells[0], before[0]); assert.equal(g.rows[0].cells[1], before[1]);
    assert.equal(before[1].style.gridColumn, '1'); assert.equal(before[0].style.gridColumn, '2');
    g.state.hidden = [0]; g.state.applyColumns();
    assert.equal(before[0].hidden, true); assert.equal(before[1].style.gridColumn, '1');
    g.state.moveColumn(1, -1); assert.deepEqual([...g.state.columnOrder], [1, 0]);
    g.state.reset(); assert.deepEqual([...g.state.columnOrder], [0, 1]);
    assert.equal(before[0].hidden, false);
});

test('el filtro de cabecera combina columnas y busca también filas fuera de la página visible', async () => {
    const g = grid();
    g.state.activeColumn = 1; await g.state.applyColumn('único', 'contains');
    assert.equal(g.visible().length, 1); assert.equal(g.visible()[0].cells[0].innerText, 'Registro 41');
    g.state.activeColumn = 0; await g.state.applyColumn('Registro 4', 'starts');
    assert.equal(g.visible().length, 1);
    await g.state.applyColumn('Registro 1', 'equals'); assert.equal(g.visible().length, 0);
    await g.state.applyColumn('', 'contains'); assert.equal(g.visible().length, 1);
    assert.equal(g.state.matchesColumn('—', {value:'', operator:'empty'}), true);
    assert.equal(g.state.matchesColumn('1.2026', {value:'1.20', operator:'starts'}), true);
});

test('descargar respeta el orden visual de filas y columnas y neutraliza fórmulas CSV', async () => {
    let content;
    const g = grid({location:{pathname:'/administracion'}, Blob, URL:{createObjectURL(blob){content=blob;return 'blob:qa';},revokeObjectURL() {}},
        document:{createElement:()=>({click() {}})},setTimeout:()=>0});
    g.rows.forEach((row,index) => {row.style={order:42-index};row.cells.forEach(cell=>cell.dataset={});row.hidden=index>1;});
    g.rows[0].cells[1].innerText='=SUM(A1:A2)';g.state.moveColumn(1,-1);g.state.downloadPage();
    const csv=await content.text();
    assert.match(csv, /"Taxón general","Registro 2"\r\n"'=SUM\(A1:A2\)","Registro 1"/);
});
