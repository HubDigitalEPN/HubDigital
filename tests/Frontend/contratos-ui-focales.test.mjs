import test from 'node:test';
import assert from 'node:assert/strict';
import {mkdtempSync,mkdirSync,writeFileSync,readFileSync,rmSync} from 'node:fs';
import path from 'node:path';
import {tmpdir} from 'node:os';
import ui from '../../scripts/deployment/contratos-ui-focales.cjs';
test('una aprobación UI exige todos los controles y huellas idénticas sin renovar otros recorridos', () => {
    const root=mkdtempSync(path.join(tmpdir(),'hub-ui-contract-'));
    try {
        const files=[...new Set([...Object.values(ui.definitions).flatMap(x=>x.sources),...Object.keys(ui.owner),'public/build/manifest.json'])];
        for(const file of files){const target=path.join(root,file);mkdirSync(path.dirname(target),{recursive:true});writeFileSync(target,'fixture '+file);}
        const before=ui.inputs(root,'laboratorio');assert.equal(ui.status(root,'laboratorio'),false);
        assert.throws(()=>ui.approve(root,'laboratorio',{navigation:true},before),/incompleto/);
        const checks=Object.fromEntries(ui.definitions.laboratorio.checks.map(check=>[check,true]));
        ui.approve(root,'laboratorio',checks,before);assert.equal(ui.status(root,'laboratorio'),true);
        const receipt=path.join(root,'.local/validation/ui-focal/laboratorio.json'),original=readFileSync(receipt,'utf8');
        writeFileSync(path.join(root,'resources/js/portal-map-model.js'),'cambio ajeno');assert.equal(ui.status(root,'laboratorio'),true);assert.equal(readFileSync(receipt,'utf8'),original);
        writeFileSync(path.join(root,'public/css/pagina-laboratorio.css'),'modificado');assert.equal(ui.status(root,'laboratorio'),false);
        assert.throws(()=>ui.approve(root,'laboratorio',checks,before),/Cambió/);
    } finally {assert.equal(path.dirname(root),path.resolve(tmpdir()));assert.ok(path.basename(root).startsWith('hub-ui-contract-'));rmSync(root,{recursive:true,force:true});}
});
