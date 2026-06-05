<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FilaCatalogoMapper;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FilaMapeada;

function mapearFila(array $fila): FilaMapeada
{
    return (new FilaCatalogoMapper)->mapear($fila);
}

test('camelCase del Excel se normaliza a snake_case', function (): void {
    $fila = mapearFila([
        'occurrenceID' => 'MEPN:INV:1',
        'oldCode' => 'BT2F3',
        'recordedBy' => 'Juan Pérez',
        'scientificName' => 'Morpho peleides',
        'localityName' => 'Yasuní',
    ]);

    expect($fila->occurrenceId)->toBe('MEPN:INV:1')
        ->and($fila->oldCode)->toBe('BT2F3')
        ->and($fila->colector)->toBe('Juan Pérez')
        ->and($fila->taxonVerbatim)->toBe('Morpho peleides')
        ->and($fila->localityName)->toBe('Yasuní');
});

test('corrige typo invidualCount → individual_count numérico', function (): void {
    $fila = mapearFila([
        'occurrenceID' => 'X',
        'invidualCount' => '8',
    ]);

    expect($fila->individualCount)->toBe(8)
        ->and($fila->individualCountVerbatim)->toBeNull();
});

test('individual_count no numérico se mueve a verbatim y emite warning', function (): void {
    $fila = mapearFila([
        'occurrenceID' => 'X',
        'invidualCount' => 'RBSO9-2',
    ]);

    expect($fila->individualCount)->toBeNull()
        ->and($fila->individualCountVerbatim)->toBe('RBSO9-2')
        ->and($fila->requiereRevision())->toBeTrue()
        ->and($fila->motivoRevision())->toContain('individual_count no numérico');
});

test('coordenadas válidas se persisten como float', function (): void {
    $fila = mapearFila([
        'occurrenceID' => 'X',
        'decimalLatitude' => '-0.658',
        'decimalLongitude' => '-76.452',
    ]);

    expect($fila->decimalLatitude)->toBe(-0.658)
        ->and($fila->decimalLongitude)->toBe(-76.452)
        ->and($fila->coordVerbatim)->toBeNull();
});

test('coordenadas con texto "dañada" se mueven a coord_verbatim', function (): void {
    $fila = mapearFila([
        'occurrenceID' => 'X',
        'decimalLatitude' => 'dañada',
        'decimalLongitude' => 'dañada',
    ]);

    expect($fila->decimalLatitude)->toBeNull()
        ->and($fila->decimalLongitude)->toBeNull()
        ->and($fila->coordVerbatim)->toContain('dañada')
        ->and($fila->requiereRevision())->toBeTrue();
});

test('coordenada fuera de rango también va a verbatim', function (): void {
    $fila = mapearFila([
        'occurrenceID' => 'X',
        'decimalLatitude' => '200.5',
        'decimalLongitude' => '0',
    ]);

    expect($fila->decimalLatitude)->toBeNull()
        ->and($fila->coordVerbatim)->toContain('200.5');
});

test('fecha en español "21-Jul-01" se parsea y se preserva verbatim', function (): void {
    $fila = mapearFila([
        'occurrenceID' => 'X',
        'eventDate' => '21-Jul-01',
    ]);

    expect($fila->fechaVerbatim)->toBe('21-Jul-01')
        ->and($fila->fechaColecta)->toBe('2001-07-21');
});

test('fecha no parseable preserva verbatim y emite warning', function (): void {
    $fila = mapearFila([
        'occurrenceID' => 'X',
        'eventDate' => 'sin fecha registrada',
    ]);

    expect($fila->fechaVerbatim)->toBe('sin fecha registrada')
        ->and($fila->fechaColecta)->toBe('')
        ->and($fila->requiereRevision())->toBeTrue()
        ->and($fila->motivoRevision())->toContain('fecha_colecta no parseable');
});

test('occurrence_id ausente emite warning específico', function (): void {
    $fila = mapearFila([
        'oldCode' => 'BT2F3',
    ]);

    expect($fila->occurrenceId)->toBeNull()
        ->and($fila->requiereRevision())->toBeTrue()
        ->and($fila->motivoRevision())->toContain('occurrence_id ausente');
});

test('cuando falta occurrence_id, codigo_catalogo cae a old_code', function (): void {
    $fila = mapearFila([
        'oldCode' => 'BT2F3',
    ]);

    expect($fila->codigoCatalogo)->toBe('BT2F3');
});

test('cuando falta occurrence_id y old_code, codigo_catalogo se genera', function (): void {
    $fila = mapearFila([
        'recordedBy' => 'X',
    ]);

    expect($fila->codigoCatalogo)->toStartWith('MEPN:INV:GEN-');
});

test('endemic se parsea como boolean desde múltiples representaciones', function (string $raw, bool $esperado): void {
    $fila = mapearFila([
        'occurrenceID' => 'X',
        'Endemic' => $raw,
    ]);

    expect($fila->endemic)->toBe($esperado);
})->with([
    ['Yes', true],
    ['yes', true],
    ['Sí', true],
    ['Si', true],
    ['1', true],
    ['true', true],
    ['No', false],
    ['no', false],
    ['0', false],
    ['false', false],
]);

test('endemic vacío o desconocido devuelve null', function (?string $raw): void {
    $fila = mapearFila([
        'occurrenceID' => 'X',
        'Endemic' => $raw ?? '',
    ]);

    expect($fila->endemic)->toBeNull();
})->with(['', '   ', 'maybe', null]);

test('localidad_verbatim usa verbatimLocality si está; sino localityName', function (): void {
    $conVerbatim = mapearFila([
        'occurrenceID' => 'X',
        'verbatimLocality' => 'YSN-Onko',
        'localityName' => 'Yasuní Onkonegare',
    ]);
    expect($conVerbatim->localidadVerbatim)->toBe('YSN-Onko');

    $soloName = mapearFila([
        'occurrenceID' => 'Y',
        'localityName' => 'Yasuní Onkonegare',
    ]);
    expect($soloName->localidadVerbatim)->toBe('Yasuní Onkonegare');
});

test('campos opcionales vacíos quedan en null', function (): void {
    $fila = mapearFila([
        'occurrenceID' => 'X',
        'biome' => '',
        'habitat' => '   ',
    ]);

    expect($fila->biome)->toBeNull()
        ->and($fila->habitat)->toBeNull();
});

test('cero pérdida: una fila completamente vacía aún genera un FilaMapeada', function (): void {
    $fila = mapearFila([]);

    expect($fila->codigoCatalogo)->toStartWith('MEPN:INV:GEN-')
        ->and($fila->requiereRevision())->toBeTrue();
});

test('elevación min y max se mapean por separado', function (): void {
    $fila = mapearFila([
        'occurrenceID' => 'X',
        'minimumElevationInMeters' => '200',
        'maximumElevationInMeters' => '350',
    ]);

    expect($fila->elevationMinM)->toBe(200.0)
        ->and($fila->elevationMaxM)->toBe(350.0);
});

test('acta_recepcion con prefijo # se lee correctamente', function (): void {
    $fila = mapearFila([
        'occurrenceID' => 'X',
        '#actaRecepcion' => 'AR-2024-001',
    ]);

    expect($fila->actaRecepcion)->toBe('AR-2024-001');
});
