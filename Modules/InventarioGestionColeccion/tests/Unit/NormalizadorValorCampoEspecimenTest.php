<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\NormalizadorValorCampoEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\RegistroColumnasEspecimen;

function normalizador(): NormalizadorValorCampoEspecimen
{
    return new NormalizadorValorCampoEspecimen;
}

// ── Lista blanca ────────────────────────────────────────────────────────────

test('la lista blanca solo contiene claves que existen en el registro de columnas', function (): void {
    $conocidas = array_column(RegistroColumnasEspecimen::todas(), 'clave');

    foreach (RegistroColumnasEspecimen::clavesEditablesEnMasa() as $clave) {
        expect($conocidas)->toContain($clave);
    }
});

test('la lista blanca excluye identificadores, claves foráneas y campos verbatim', function (): void {
    $editables = RegistroColumnasEspecimen::clavesEditablesEnMasa();

    // Identifican una pieza concreta: fijarlos en bloque destruye la identidad.
    foreach (['codigoCatalogo', 'occurrenceId', 'catalogNumber', 'oldCode', 'cardexLiquidCollectionCode'] as $id) {
        expect($editables)->not->toContain($id);
    }

    // El dato crudo del Excel: es la única prueba de qué decía la etiqueta.
    foreach (['taxonVerbatim', 'localidadVerbatim', 'fechaVerbatim', 'coordVerbatim',
        'individualCountVerbatim', 'endemicVerbatim', 'verbatimLatitude', 'verbatimLongitude',
        'verbatimElevation', 'verbatimDepth', 'verbatimSrs', 'verbatimCoordinateSystem'] as $verbatim) {
        expect($editables)->not->toContain($verbatim);
    }
});

test('la lista blanca excluye el estado de revisión, las coordenadas y las fechas de colecta', function (): void {
    $editables = RegistroColumnasEspecimen::clavesEditablesEnMasa();

    // Tienen flujo propio que mantiene coherentes estado y motivo.
    expect($editables)->not->toContain('estadoRevision')
        ->and($editables)->not->toContain('motivoRevision')
        ->and($editables)->not->toContain('estado');

    // Hechos medidos de cada ejemplar, no metadatos repetibles.
    foreach (['decimalLatitude', 'decimalLongitude', 'elevationMinM', 'elevationMaxM',
        'individualCount', 'fechaColecta', 'fechaColectaFin'] as $medido) {
        expect($editables)->not->toContain($medido);
    }
});

test('clavesEditablesDeTexto excluye los campos que no son de texto', function (): void {
    expect(RegistroColumnasEspecimen::clavesEditablesDeTexto())->not->toContain('endemic')
        ->and(RegistroColumnasEspecimen::clavesEditablesDeTexto())->toContain('colector');
});

// ── Normalización ───────────────────────────────────────────────────────────

test('un texto vacío se normaliza a null para vaciar el campo', function (): void {
    expect(normalizador()->normalizar('biome', ''))->toBeNull()
        ->and(normalizador()->normalizar('biome', '   '))->toBeNull()
        ->and(normalizador()->normalizar('biome', null))->toBeNull();
});

test('los campos obligatorios rechazan quedarse vacíos', function (): void {
    // La entidad tipa colector y localidad como string no-null.
    normalizador()->normalizar('colector', '');
})->throws(InvalidArgumentException::class, 'no puede quedar vacío');

test('un valor más largo que la columna se rechaza antes de tocar la base', function (): void {
    // sex es varchar(40): que reviente aquí y no a mitad del lote en Postgres.
    normalizador()->normalizar('sex', str_repeat('x', 41));
})->throws(InvalidArgumentException::class, 'admite 40');

test('un valor que cabe justo se acepta', function (): void {
    expect(normalizador()->normalizar('sex', str_repeat('x', 40)))->toBe(str_repeat('x', 40));
});

test('una clave fuera de la lista blanca se rechaza', function (): void {
    normalizador()->normalizar('codigoCatalogo', 'MEPN-1');
})->throws(InvalidArgumentException::class, 'no se puede editar en masa');

test('el campo booleano acepta las formas habituales en español e inglés', function (string $entrada, string $esperado): void {
    expect(normalizador()->normalizar('endemic', $entrada))->toBe($esperado);
})->with([
    ['sí', 'true'], ['Si', 'true'], ['1', 'true'], ['true', 'true'], ['yes', 'true'],
    ['no', 'false'], ['NO', 'false'], ['0', 'false'], ['false', 'false'],
]);

test('el campo booleano rechaza cualquier otra cosa', function (): void {
    normalizador()->normalizar('endemic', 'quizás');
})->throws(InvalidArgumentException::class, 'solo admite sí o no');

// ── Buscar y reemplazar ─────────────────────────────────────────────────────

test('reemplaza texto plano dentro del valor', function (): void {
    expect(normalizador()->reemplazarEn('Bosque de Yasuni', 'Yasuni', 'Yasuní'))
        ->toBe('Bosque de Yasuní');
});

test('el texto buscado se trata como literal, no como expresión regular', function (): void {
    // Sin preg_quote, '.' casaría con cualquier carácter y arrasaría el campo.
    expect(normalizador()->reemplazarEn('A.B y AXB', 'A.B', 'OK'))->toBe('OK y AXB');

    // Un paréntesis suelto es una regex inválida: sin escapar, esto reventaría.
    expect(normalizador()->reemplazarEn('valor (dudoso)', '(dudoso)', ''))->toBe('valor ');
});

test('por defecto distingue mayúsculas y puede no hacerlo', function (): void {
    expect(normalizador()->reemplazarEn('Yasuni y yasuni', 'yasuni', 'X'))
        ->toBe('Yasuni y X')
        ->and(normalizador()->reemplazarEn('Yasuni y yasuni', 'yasuni', 'X', distinguirMayusculas: false))
        ->toBe('X y X');
});

test('con palabra completa no parte palabras que contienen el patrón', function (): void {
    // Con el interruptor puesto solo cae el 'sp' suelto del final.
    expect(normalizador()->reemplazarEn('Aspidosperma sp', 'sp', 'sp.', palabraCompleta: true))
        ->toBe('Aspidosperma sp.');

    // Y sin él se estropea el nombre del género, que es justo lo que hay que
    // poder evitar: las dos 'sp' de dentro de la palabra también se sustituyen.
    expect(normalizador()->reemplazarEn('Aspidosperma sp', 'sp', 'sp.'))
        ->toBe('Asp.idosp.erma sp.');
});

test('reemplazar sobre null o con un patrón vacío no cambia nada', function (): void {
    expect(normalizador()->reemplazarEn(null, 'a', 'b'))->toBeNull()
        ->and(normalizador()->reemplazarEn('texto', '', 'b'))->toBe('texto');
});

test('un reemplazo que desborda la columna se rechaza nombrando el espécimen', function (): void {
    normalizador()->verificarTamano('sex', str_repeat('x', 41), 'MEPN-INV-0001');
})->throws(InvalidArgumentException::class, 'MEPN-INV-0001');

test('verificarTamano no se queja de los campos de texto largo', function (): void {
    normalizador()->verificarTamano('localityNotes', str_repeat('x', 5000), 'MEPN-INV-0001');
})->throwsNoExceptions();

test('aTexto representa booleanos y nulos de forma estable para la bitácora', function (): void {
    expect(normalizador()->aTexto(true))->toBe('true')
        ->and(normalizador()->aTexto(false))->toBe('false')
        ->and(normalizador()->aTexto(null))->toBeNull()
        ->and(normalizador()->aTexto('hola'))->toBe('hola');
});
