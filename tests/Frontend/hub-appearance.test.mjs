import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';

const source = readFileSync(new URL('../../public/js/hub-appearance.js', import.meta.url), 'utf8');
function fixture({stored = null, systemDark = false, portal = false} = {}) {
    const storage = new Map(stored === null ? [] : [['flux.appearance', stored]]);
    const classes = new Set();
    const root = {dataset: portal ? {portalTheme: 'light'} : {}, classList: {
        toggle(name, enabled) { enabled ? classes.add(name) : classes.delete(name); },
    }};
    const window = {localStorage: {
        getItem: key => storage.get(key) ?? null,
        setItem: (key, value) => storage.set(key, value),
    }, matchMedia: () => ({matches: systemDark})};
    runInNewContext(source, {window, document: {documentElement: root}});
    return {window, root, classes, storage};
}

test('sin preferencia válida inicia en claro aunque el sistema esté oscuro', () => {
    for (const stored of [null, '', 'invalid']) {
        const f = fixture({stored, systemDark: true});
        assert.equal(f.storage.get('flux.appearance'), 'light');
        assert.equal(f.classes.has('dark'), false);
    }
});

test('claro y oscuro se conservan al recargar con el sistema en el tema opuesto', () => {
    for (const selected of ['light', 'dark']) {
        const f = fixture({systemDark: selected === 'light'});
        f.window.Flux.applyAppearance(selected);
        const reloaded = fixture({stored: f.storage.get('flux.appearance'), systemDark: selected === 'light'});
        assert.equal(reloaded.classes.has('dark'), selected === 'dark');
        assert.equal(reloaded.storage.get('flux.appearance'), selected);
    }
});

test('abrir el portal público conserva la elección oscura para volver a la cuenta', () => {
    const f = fixture({stored: 'dark', portal: true});
    const apply = f.window.Flux.applyAppearance;
    // Flux captura el controlador al iniciar Alpine y luego sustituye su objeto global.
    f.window.Flux = {appearance: f.storage.get('flux.appearance')};
    apply(f.window.Flux.appearance);
    assert.equal(f.classes.has('dark'), false);
    assert.equal(f.storage.get('flux.appearance'), 'dark');
    delete f.root.dataset.portalTheme;
    apply(f.window.Flux.appearance);
    assert.equal(f.classes.has('dark'), true);
    const reloaded = fixture({stored: f.storage.get('flux.appearance')});
    assert.equal(reloaded.classes.has('dark'), true);
});

test('la opción Sistema existente solo se activa mediante una selección expresa', () => {
    const f = fixture({systemDark: true});
    assert.equal(f.classes.has('dark'), false);
    f.window.Flux.applyAppearance('system');
    assert.equal(f.classes.has('dark'), true);
    assert.equal(f.storage.get('flux.appearance'), 'system');
    const reloaded = fixture({stored: 'system', systemDark: false});
    assert.equal(reloaded.classes.has('dark'), false);
});
