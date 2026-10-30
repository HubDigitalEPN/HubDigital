import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const source = readFileSync(new URL('../../public/js/chat-shell.js', import.meta.url), 'utf8');

function entorno({stored = '1', unavailable = false} = {}) {
    let hidden = true, focus = '';
    const document = {documentElement:{toggleAttribute: (_,value)=>hidden=value}, querySelector:()=>({focus:()=>focus='recuperar'})};
    const window = {}, storage = {getItem:()=>stored, removeItem(){if(unavailable) throw Error();stored=null;}};
    vm.runInNewContext(source, {window, document, localStorage:storage});
    function shell(puedeOcultar = true) {
        const s=window.hubChatShell({puedeOcultar});s.$nextTick=fn=>fn();
        s.$refs={input:{focus:()=>focus='entrada'},trigger:{offsetParent:{},focus:()=>focus='icono'}};
        s.init();return s;
    }
    return {shell,get hidden(){return hidden;},get stored(){return stored;},get focus(){return focus;}};
}
test('el catálogo puede ocultar icono y panel y recuperarlos con foco en la entrada', () => {
    const env=entorno(),s=env.shell();s.abierto=true;s.ocultar();
    assert.equal(s.abierto,false);assert.equal(env.hidden,true);assert.equal(env.focus,'recuperar');
    s.alternar();assert.equal(env.hidden,false);assert.equal(s.abierto,true);assert.equal(env.focus,'entrada');
});
test('una apertura nueva siempre ofrece el icono aunque antes se hubiera ocultado', () => {
    const env=entorno();const first=env.shell();assert.equal(first.minimizado,false);assert.equal(env.hidden,false);assert.equal(env.stored,null);
    first.ocultar();assert.equal(env.hidden,true);
    const next=env.shell();assert.equal(next.minimizado,false);assert.equal(env.hidden,false);
});
test('fuera del catálogo ni una acción antigua puede ocultar el asistente', () => {
    const env=entorno(),s=env.shell(false);s.ocultar();
    assert.equal(s.minimizado,false);assert.equal(env.hidden,false);
    s.alternar();assert.equal(s.abierto,true);s.alternar();assert.equal(s.abierto,false);assert.equal(env.focus,'icono');
});
test('sin almacenamiento sigue funcionando la apertura y la recuperación', () => {
    const env=entorno({unavailable:true}),s=env.shell();assert.equal(env.hidden,false);
    s.ocultar();assert.equal(s.minimizado,true);s.alternar();assert.equal(s.minimizado,false);
});
