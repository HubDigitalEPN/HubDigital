<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FilaCatalogoMapper;

function mapearLocalidad(array $fila): object
{
    return (new FilaCatalogoMapper)->mapear(['occurrenceID' => 'O-'.bin2hex(random_bytes(3))] + $fila);
}

test('descompone "País, Provincia, Localidad" en columnas Darwin Core', function (): void {
    $r = mapearLocalidad(['verbatimLocality' => 'ECUADOR, ORELLANA, YASUNÍ']);

    expect($r->country)->toBe('Ecuador')
        ->and($r->stateProvince)->toBe('Orellana')
        ->and($r->localityName)->toBe('Yasuní')
        ->and($r->localidadVerbatim)->toBe('Ecuador, Orellana, Yasuní');
});

test('con muchos segmentos la localidad específica es SOLO la última palabra tras la coma', function (): void {
    $r = mapearLocalidad(['verbatimLocality' => 'Ecuador, Orellana, Aguarico, Estación, Onkone']);

    expect($r->country)->toBe('Ecuador')
        ->and($r->stateProvince)->toBe('Orellana')
        ->and($r->municipality)->toBe('Aguarico')
        // Solo el último segmento; el texto completo queda en localidad_verbatim.
        ->and($r->localityName)->toBe('Onkone')
        ->and($r->localidadVerbatim)->toBe('Ecuador, Orellana, Aguarico, Estación, Onkone');
});

test('dos segmentos: país y localidad', function (): void {
    $r = mapearLocalidad(['verbatimLocality' => 'Ecuador, Yasuní']);

    expect($r->country)->toBe('Ecuador')
        ->and($r->stateProvince)->toBeNull()
        ->and($r->localityName)->toBe('Yasuní');
});

test('sin comas no descompone: solo normaliza la caja', function (): void {
    $r = mapearLocalidad(['verbatimLocality' => 'YASUNÍ']);

    expect($r->localidadVerbatim)->toBe('Yasuní')
        ->and($r->country)->toBeNull()
        ->and($r->localityName)->toBeNull();
});

test('no pisa un country explícito de su propia columna (no daña "PE")', function (): void {
    $r = mapearLocalidad(['verbatimLocality' => 'Peru, Cusco, Machu Picchu', 'country' => 'PE']);

    expect($r->country)->toBe('PE')
        ->and($r->stateProvince)->toBe('Cusco')
        ->and($r->localityName)->toBe('Machu picchu');
});
