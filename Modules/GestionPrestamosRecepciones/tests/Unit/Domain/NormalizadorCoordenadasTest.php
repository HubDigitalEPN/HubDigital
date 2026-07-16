<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\Services\NormalizadorCamposDwC;

/**
 * @return array<int, array{campo: string, mensaje: string}>
 */
function advertenciasInvalidas(array $registro): array
{
    $resultado = (new NormalizadorCamposDwC)->normalizar($registro);

    return array_values(array_filter(
        $resultado->cambios,
        fn (array $c) => ! empty($c['invalido'])
    ));
}

function coordenadaInvalida(string $campo, string|float $valor): bool
{
    foreach (advertenciasInvalidas([$campo => $valor]) as $adv) {
        if ($adv['campo'] === $campo) {
            return true;
        }
    }

    return false;
}

it('acepta latitud dentro de rango con 1 a 4 decimales', function () {
    expect(coordenadaInvalida('decimalLatitude', '-0.5'))->toBeFalse()
        ->and(coordenadaInvalida('decimalLatitude', '12.3456'))->toBeFalse()
        ->and(coordenadaInvalida('decimalLongitude', '-78.4567'))->toBeFalse();
});

it('normaliza la coma decimal a punto', function () {
    $resultado = (new NormalizadorCamposDwC)->normalizar(['decimalLatitude' => '-0,4823']);

    expect($resultado->registro['decimalLatitude'])->toBe(-0.4823);
});

it('rechaza latitud fuera del rango -90/90', function () {
    expect(coordenadaInvalida('decimalLatitude', '95.1234'))->toBeTrue()
        ->and(coordenadaInvalida('decimalLatitude', '-90.5'))->toBeTrue();
});

it('rechaza longitud fuera del rango -180/180', function () {
    expect(coordenadaInvalida('decimalLongitude', '181.2'))->toBeTrue()
        ->and(coordenadaInvalida('decimalLongitude', '-180.9'))->toBeTrue();
});

it('rechaza coordenadas sin decimales (mínimo 1 decimal)', function () {
    expect(coordenadaInvalida('decimalLatitude', '12'))->toBeTrue()
        ->and(coordenadaInvalida('decimalLongitude', '-78'))->toBeTrue();
});

it('rechaza coordenadas con más de 4 decimales', function () {
    expect(coordenadaInvalida('decimalLatitude', '12.34567'))->toBeTrue()
        ->and(coordenadaInvalida('decimalLongitude', '-78.123456'))->toBeTrue();
});

it('rechaza coordenadas no numéricas', function () {
    expect(coordenadaInvalida('decimalLatitude', 'N/A'))->toBeTrue();
});
