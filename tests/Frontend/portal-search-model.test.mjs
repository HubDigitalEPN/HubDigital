import test from 'node:test';
import assert from 'node:assert/strict';
import {consultaPredictiva, completarBusqueda} from '../../resources/js/portal-search-model.js';

test('las sugerencias completan el último catálogo sin perder los fragmentos ya escritos', () => {
    const texto = 'MEPN-INV-1, MEPN-INV-2,  300';
    assert.equal(consultaPredictiva('filtroCatalogo', texto), '300');
    assert.equal(completarBusqueda('filtroCatalogo', texto, 'MEPN-INV-300'), 'MEPN-INV-1, MEPN-INV-2, MEPN-INV-300');
    assert.equal(consultaPredictiva('filtroCatalogo', 'MEPN-INV-1, '), '');
    assert.equal(completarBusqueda('filtroCatalogo', '300', 'MEPN-INV-300'), 'MEPN-INV-300');
});

test('un taxón conserva espacios y comas de su nombre y la consulta predictiva tiene un límite', () => {
    assert.equal(consultaPredictiva('filtroTaxon', ' Nombre científico, autor '), 'Nombre científico, autor');
    assert.equal(completarBusqueda('filtroTaxon', 'Nombre, autor', 'Nombre confirmado'), 'Nombre confirmado');
    assert.equal(consultaPredictiva('filtroTaxon', 'a'.repeat(150)).length, 120);
});
