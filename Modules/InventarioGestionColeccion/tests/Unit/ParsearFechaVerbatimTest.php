<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\ParsearFechaVerbatim;

test('ISO YYYY-MM-DD se parsea directamente', function (): void {
    $resultado = ParsearFechaVerbatim::parsear('2024-05-12');

    expect($resultado?->format('Y-m-d'))->toBe('2024-05-12');
});

test('formato dd-MMM-yy en español con pivot año actual', function (string $verbatim, string $esperado, int $pivot): void {
    expect(ParsearFechaVerbatim::parsear($verbatim, $pivot)?->format('Y-m-d'))->toBe($esperado);
})->with([
    // En 2026 (pivot=26): yy<=26 → 20yy, yy>26 → 19yy
    ['21-Jul-01', '2001-07-21', 26],
    ['11-Abr-01', '2001-04-11', 26],
    ['15-Mar-26', '2026-03-15', 26],
    ['10-Ene-50', '1950-01-10', 26],
    ['01-Dic-99', '1999-12-01', 26],
]);

test('meses en español completos también se aceptan', function (): void {
    expect(ParsearFechaVerbatim::parsear('15-julio-2020')?->format('Y-m-d'))->toBe('2020-07-15');
});

test('meses con tilde se normalizan', function (): void {
    // setiembre y septiembre, octubre con tilde no es estándar pero queremos robustez
    expect(ParsearFechaVerbatim::parsear('05-setiembre-2018')?->format('Y-m-d'))->toBe('2018-09-05');
});

test('aliases en inglés (jan/apr/aug/dec) también se aceptan', function (string $verbatim, string $esperado): void {
    expect(ParsearFechaVerbatim::parsear($verbatim, 26)?->format('Y-m-d'))->toBe($esperado);
})->with([
    ['10-Jan-20', '2020-01-10'],
    ['03-Apr-95', '1995-04-03'],
    ['25-Dec-99', '1999-12-25'],
]);

test('separadores alternativos: slash, punto, espacio', function (string $verbatim, string $esperado): void {
    expect(ParsearFechaVerbatim::parsear($verbatim, 26)?->format('Y-m-d'))->toBe($esperado);
})->with([
    ['21/Jul/01', '2001-07-21'],
    ['21.Jul.01', '2001-07-21'],
    ['21 Jul 2001', '2001-07-21'],
]);

test('formato numérico dd/mm/yy también se acepta', function (): void {
    expect(ParsearFechaVerbatim::parsear('21/07/01', 26)?->format('Y-m-d'))->toBe('2001-07-21');
});

test('verbatim vacío o sólo espacios devuelve null', function (?string $valor): void {
    expect(ParsearFechaVerbatim::parsear((string) $valor))->toBeNull();
})->with(['', '   ', "\t\n"]);

test('formato desconocido devuelve null', function (string $verbatim): void {
    expect(ParsearFechaVerbatim::parsear($verbatim))->toBeNull();
})->with([
    'dañada',
    'sin fecha',
    'Julio 2020', // sin día
    '2020',
    'mes 5 año 2020',
]);

test('mes inválido devuelve null', function (): void {
    expect(ParsearFechaVerbatim::parsear('21-Xyz-01'))->toBeNull();
});

test('día fuera de rango (32 de enero) devuelve null', function (): void {
    expect(ParsearFechaVerbatim::parsear('32-Ene-01', 26))->toBeNull();
});

test('29 de febrero en año no-bisiesto devuelve null', function (): void {
    expect(ParsearFechaVerbatim::parsear('29-Feb-01', 26))->toBeNull();
});

test('29 de febrero en año bisiesto es válido', function (): void {
    expect(ParsearFechaVerbatim::parsear('29-Feb-20', 26)?->format('Y-m-d'))->toBe('2020-02-29');
});
