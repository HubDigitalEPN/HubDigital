import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const source = readFileSync(new URL('../../public/js/chat-shell.js', import.meta.url), 'utf8');
test('ocultar elimina icono y panel, persiste al volver y permite recuperar desde el pie', () => {
    let stored = '0', hidden = false, focus = '';
    const document = {documentElement:{toggleAttribute: (_,value)=>hidden=value}, querySelector:()=>({focus:()=>focus='recuperar'})};
    const window = {};
    vm.runInNewContext(source, {window, document, localStorage:{getItem:()=>stored,setItem:(_,value)=>stored=value}});
    function shell() {const s=window.hubChatShell();s.$nextTick=fn=>fn();s.$refs={input:{focus:()=>focus='entrada'}};s.init();return s;}
    const s=shell();s.abierto=true;s.ocultar();assert.equal(s.abierto,false);assert.equal(hidden,true);assert.equal(focus,'recuperar');
    const next=shell();assert.equal(next.minimizado,true);next.alternar();
    assert.equal(hidden,false);assert.equal(next.abierto,true);assert.equal(focus,'entrada');assert.equal(stored,'0');
});
test('un navegador sin almacenamiento sigue permitiendo ocultar y recuperar', () => {
    const window = {}, document={documentElement:{toggleAttribute() {}},querySelector:()=>null};
    vm.runInNewContext(source,{window, document, localStorage:{getItem(){throw Error();},setItem(){throw Error();}}});
    const s=window.hubChatShell();s.$nextTick=fn=>fn();s.$refs={input:{focus(){}}};s.init();s.ocultar();assert.equal(s.minimizado,true);s.alternar();assert.equal(s.minimizado,false);
});
