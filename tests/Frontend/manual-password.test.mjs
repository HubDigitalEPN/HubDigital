import test from 'node:test';
import assert from 'node:assert/strict';
import { manualPasswordEntry } from '../../resources/js/manual-password.js';

test('rechaza autollenado, pegado, scripts y valores cambiados sin eventos', () => {
    for (const event of [
        { isTrusted: true, inputType: 'insertReplacementText', data: 'recordada' },
        { isTrusted: true, inputType: 'insertFromPaste', data: 'recordada' },
        { isTrusted: true, inputType: 'insertText', data: 'recordada' },
        { isTrusted: false, inputType: 'insertText', data: 'x' },
    ]) {
        const entry = manualPasswordEntry();
        assert.equal(entry.before(event), false);
        assert.equal(entry.input(event, 'recordada'), false);
        assert.equal(entry.valid('recordada'), false);
    }
    const entry = manualPasswordEntry();
    assert.equal(entry.valid('rellenada-sin-evento'), false);
});

test('admite teclado e IME, edición y revelado sin autorizar un reemplazo recordado', () => {
    const entry = manualPasswordEntry();
    let value = '';
    for (const data of 'Clave!') {
        const event = { isTrusted: true, inputType: 'insertText', data };
        assert.equal(entry.before(event), true);
        value += data;
        assert.equal(entry.input(event, value), true);
        assert.equal(entry.valid(value), true);
    }
    const ime = { isTrusted: true, inputType: 'insertCompositionText', data: 'ñ' };
    assert.equal(entry.before(ime), true);
    assert.equal(entry.input(ime, value + 'ñ'), true);
    assert.equal(entry.valid('clave-reemplazada'), false);
    const borrar = { isTrusted: true, inputType: 'deleteContentBackward' };
    assert.equal(entry.before(borrar), true);
    assert.equal(entry.input(borrar, value), true);
    assert.equal(entry.valid(value), true);
    const enter = { isTrusted: true, inputType: 'insertLineBreak' };
    assert.equal(entry.before(enter), true);
    assert.equal(entry.valid(value), true, 'Enter conserva la clave escrita para enviar el formulario');
    assert.equal(entry.input(enter, value), true);
    assert.equal(entry.before(enter), true);
    assert.equal(entry.input(enter, value + '\n'), false, 'Enter no autoriza modificar la clave');
    assert.equal(entry.valid(value + '\n'), false);
    entry.reset();
    assert.equal(entry.valid(value), false);
    assert.equal(entry.valid(''), true);
});
