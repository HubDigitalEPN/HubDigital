import test from 'node:test';
import assert from 'node:assert/strict';
import {linajeTaxonomico, textoLinajeTaxonomico} from '../../resources/js/portal-taxonomy-lineage.js';
import {factorAbundancia3D} from '../../resources/js/portal-taxonomy-three-style.js';

const indice = nodos => ({porClave: new Map(nodos.map(n => [n.clave, n]))});
const nodo = (clave, padre, etiqueta, nombre) => ({clave, padre, etiqueta, nombre});

test('el linaje incluye los ancestros de la raíz 3D y sus rangos formales hasta el nodo elegido', () => {
    const fuente = indice([
        nodo('filo', null, 'Filo', 'Arthropoda'), nodo('clase', 'filo', 'Clase', 'Insecta'),
        nodo('orden', 'clase', 'Orden', 'Hymenoptera'), nodo('suborden', 'orden', 'Suborden', 'Apocrita'),
        nodo('superfamilia', 'suborden', 'Superfamilia', 'Formicoidea'), nodo('familia', 'superfamilia', 'Familia', 'Formicidae'),
        nodo('genero', 'familia', 'Género', 'Camponotus'), nodo('especie', 'genero', 'Especie', 'Camponotus atriceps'),
    ]);
    assert.equal(textoLinajeTaxonomico(fuente, 'familia'), 'Clase: Insecta → Orden: Hymenoptera → Suborden: Apocrita → Superfamilia: Formicoidea → Familia: Formicidae');
    assert.equal(linajeTaxonomico(fuente, 'especie').at(-1).nombre, 'Camponotus atriceps');
    assert.equal(linajeTaxonomico(fuente, 'especie').length, 7);
    assert.equal(textoLinajeTaxonomico(fuente, 'filo'), 'Filo: Arthropoda');
});

test('los huecos publicados y los homónimos conservan su identidad, sin inventar rangos intermedios', () => {
    const fuente = indice([
        nodo('clase', null, 'Clase', 'Insecta'), nodo('orden', 'clase', 'Orden', 'Diptera'),
        nodo('familia', 'orden', 'Familia', 'Dermaptera'), nodo('otro-orden', 'clase', 'Orden', 'Dermaptera'),
    ]);
    assert.equal(textoLinajeTaxonomico(fuente, 'familia'), 'Clase: Insecta → Orden: Diptera → Familia: Dermaptera');
    assert.equal(textoLinajeTaxonomico(fuente, 'otro-orden'), 'Clase: Insecta → Orden: Dermaptera');
    assert.equal(textoLinajeTaxonomico(fuente, 'retirado'), '');
    assert.equal(textoLinajeTaxonomico(null, 'retirado'), '');
    const ciclo = indice([nodo('a', 'b', 'Familia', 'A'), nodo('b', 'a', 'Orden', 'B')]);
    assert.deepEqual(linajeTaxonomico(ciclo, 'a').map(n => n.clave), ['b', 'a']);
});

test('la abundancia mantiene legibles los registros escasos y limita a un 8 % la diferencia de diámetro', () => {
    const cantidades = [1, 2, 50, 99, 100, 400, 1253, 12266, 28260];
    const factores = cantidades.map(total => factorAbundancia3D(total, 28260));
    for (let i = 0; i < 5; i++) assert.equal(factores[i], 1);
    for (let i = 1; i < factores.length; i++) assert.ok(factores[i] >= factores[i - 1]);
    assert.ok(factores.at(-1) / factores[0] <= 1.08);
    assert.equal(factorAbundancia3D(1, 99), 1);
    assert.equal(factorAbundancia3D(99, 99), 1);
    assert.equal(factorAbundancia3D(-1, 28260), 1);
    assert.equal(factorAbundancia3D(undefined, undefined), 1);
});
