import test from 'node:test';
import assert from 'node:assert/strict';
import {readdirSync, readFileSync} from 'node:fs';
import {join, relative} from 'node:path';
const root = new URL('../../', import.meta.url).pathname.replace(/^\/(\w:)/, '$1');
function files(dir) { return readdirSync(dir, {withFileTypes:true}).flatMap(entry => entry.isDirectory() ? files(join(dir, entry.name)) : [join(dir, entry.name)]); }
test('todas las tablas interactivas usan el grid común; documentos y correos conservan su formato', () => {
    const missing = [], inventory = [];
    for (const file of [...files(join(root,'resources/views')), ...files(join(root,'Modules'))].filter(file=>file.endsWith('.blade.php'))) {
        const path = relative(root,file).replaceAll('\\','/');
        if (/\/(pdf|mails|emails)\//.test(path) || path.endsWith('/qr-imprimible.blade.php')) continue;
        const source = readFileSync(file, 'utf8');
        if (!source.includes('<table')) continue;
        inventory.push(path);
        if (path.endsWith('components/interactive-grid.blade.php') || path.endsWith('components/tabla-registros-mapa.blade.php')) continue;
        const tags = [...source.matchAll(/<\/?x-grid-frame\b[^>]*>|<table\b/g)];
        let depth=0;
        for (const [tag] of tags) {
            if (tag.startsWith('</x-grid-frame')) depth--;
            else if (tag.startsWith('<x-grid-frame')) depth++;
            else if (depth<1) missing.push(path);
        }
    }
    assert.deepEqual(missing, []); assert.ok(inventory.length > 20);
});
