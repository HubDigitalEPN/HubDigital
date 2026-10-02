<?php

declare(strict_types=1);

use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\FiltrosLenguajeChat;

test('las fechas del chat conservan días y operadores antes y después', function (string $pregunta, array $esperado): void {
    expect((new FiltrosLenguajeChat)->extraer($pregunta))->toBe($esperado);
})->with([
    ['Busca registros desde 2000-05-01 hasta 2000-05-31', ['desde' => '2000-05-01', 'hasta' => '2000-05-31']],
    ['Busca registros del 1 al 4 de mayo de 2000', ['desde' => '2000-05-01', 'hasta' => '2000-05-04']],
    ['Busca registros desde el 1 de mayo de 2000 hasta el 4 de mayo de 2000', ['desde' => '2000-05-01', 'hasta' => '2000-05-04']],
    ['Busca registros del 28 de febrero de 2000 al 1 de marzo de 2000', ['desde' => '2000-02-28', 'hasta' => '2000-03-01']],
    ['Busca registros el 5 de mayo de 2000', ['desde' => '2000-05-05', 'hasta' => '2000-05-05']],
    ['Busca registros colectados antes de 1950', ['hasta' => '1949-12-31']],
    ['Busca registros colectados antes del 1950', ['hasta' => '1949-12-31']],
    ['Busca registros colectados después de 1950', ['desde' => '1951-01-01']],
    ['Busca registros colectados después del 1950', ['desde' => '1951-01-01']],
    ['Busca registros antes de 2000-05-05', ['hasta' => '2000-05-04']],
    ['Busca registros después de 2000-02-28', ['desde' => '2000-02-29']],
    ['Busca registros entre 1999 y 2000', ['desde' => '1999-01-01', 'hasta' => '2000-12-31']],
    ['Busca registros en mayo de 2000', ['mes' => '5', 'desde' => '2000-05-01', 'hasta' => '2000-05-31']],
]);

test('una fecha imposible o invertida exige aclaración sin normalizar el día', function (string $pregunta): void {
    expect((new FiltrosLenguajeChat)->extraer($pregunta))->toHaveKey('error_consulta');
})->with([
    'Busca registros entre 2021-01-01 y 2020-01-01',
    'Busca registros desde 2000-02-30 hasta 2000-03-01',
    'Busca registros del 31 al 31 de abril de 2000',
    'Busca registros antes de 2000-13-01',
    'Busca registros desde 2000-05-01 hasta el próximo martes',
    'Busca registros desde 2000-05-01 y 2000-05-04',
    'Busca registros desde 2000-05-01 hasta 2000-5-04',
    'Busca registros antes de mayo de 2000',
    'Busca registros antes del mayo de 2000',
    'Busca registros desde el 1 de mayo de 2000 hasta el próximo martes',
    'Busca registros desde el 1 de mayo de 2000 hasta el 4 de mayo',
    'Busca registros desde el 1 de mayo de 2000',
    'Busca registros desde el 1 de mayo de 2000 y el 4 de mayo de 2000',
    'Busca registros del 1 hasta 4 de mayo de 2000',
    'Busca registros desde el 4 de mayo de 2000 hasta el 1 de mayo de 2000',
    'Busca registros desde el 30 de febrero de 2000 hasta el 4 de marzo de 2000',
    'Busca registros entre 1000 y 500 metros de altitud',
]);

test('el intervalo de elevación se extrae sin convertirse en fechas', function (): void {
    expect((new FiltrosLenguajeChat)->extraer('Busca Dichotomius satanas entre 1000 y 2000 metros de altitud'))
        ->toBe(['elev_desde' => '1000', 'elev_hasta' => '2000'])
        ->and((new FiltrosLenguajeChat)->extraer('Busca entre 1800 y 2000 metros de altitud'))
        ->toBe(['elev_desde' => '1800', 'elev_hasta' => '2000'])
        ->and((new FiltrosLenguajeChat)->extraer('Busca entre 1.000 y 2.000 metros de elevación'))
        ->toBe(['elev_desde' => '1000', 'elev_hasta' => '2000']);
});
