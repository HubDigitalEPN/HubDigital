import test from 'node:test';
import assert from 'node:assert/strict';
import {nombreDescargaImagen} from '../../resources/js/portal-image-model.js';

test('la descarga conserva PNG, WebP y AVIF desde el nombre registrado de la fotografía', () => {
    for (const nombre of ['hormiga.PNG', 'ejemplar.webp', 'detalle.avif', 'muestra.jpeg']) {
        assert.equal(nombreDescargaImagen(nombre), nombre);
    }
});

test('un metadato ausente no inventa una extensión JPEG', () => {
    for (const nombre of [null, undefined, '', '   ', '.', '..', false, {}]) {
        assert.equal(nombreDescargaImagen(nombre), 'imagen');
    }
});

test('el nombre de descarga elimina rutas y caracteres de control sin cambiar el formato', () => {
    assert.equal(nombreDescargaImagen('../../fotos/hormiga.webp'), 'hormiga.webp');
    assert.equal(nombreDescargaImagen('C:\\fotos\\hormiga.PNG'), 'hormiga.PNG');
    assert.equal(nombreDescargaImagen('hormiga\u0000:detalle.avif'), 'hormiga__detalle.avif');
});
