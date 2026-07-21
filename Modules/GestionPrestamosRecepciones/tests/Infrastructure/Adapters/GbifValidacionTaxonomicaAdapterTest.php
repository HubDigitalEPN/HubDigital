<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\GbifValidacionTaxonomicaAdapter;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(fn () => Cache::flush());

it('ofrece la sugerencia fuzzy de alta confianza', function () {
    Http::fake(['api.gbif.org/*' => Http::response([
        'matchType' => 'FUZZY',
        'confidence' => 96,
        'canonicalName' => 'Andesiops peruvianus',
    ])]);

    $resultado = (new GbifValidacionTaxonomicaAdapter)->validarEspecies(['Andesiops peruvianu']);

    expect($resultado[0]['estado'])->toBe('inconsistencia_tipografica')
        ->and($resultado[0]['sugerencia'])->toBe('Andesiops peruvianus')
        ->and($resultado[0]['sugerencias'])->toBe(['Andesiops peruvianus']);
});

it('reúne varias sugerencias del match principal y de las alternativas', function () {
    Http::fake(['api.gbif.org/*' => Http::response([
        'matchType' => 'NONE',
        'confidence' => 0,
        'alternatives' => [
            ['matchType' => 'FUZZY', 'confidence' => 92, 'canonicalName' => 'Baetodes serratus'],
            ['matchType' => 'FUZZY', 'confidence' => 88, 'canonicalName' => 'Baetodes awa'],
            ['matchType' => 'FUZZY', 'confidence' => 40, 'canonicalName' => 'Baja confianza'],
        ],
    ])]);

    $resultado = (new GbifValidacionTaxonomicaAdapter)->validarEspecies(['Baetodes serratu']);

    expect($resultado[0]['estado'])->toBe('inconsistencia_tipografica')
        ->and($resultado[0]['sugerencias'])->toBe(['Baetodes serratus', 'Baetodes awa']);
});

it('marca catalogado un match exacto sin sugerencias', function () {
    Http::fake(['api.gbif.org/*' => Http::response([
        'matchType' => 'EXACT',
        'confidence' => 99,
        'canonicalName' => 'Anacroneuria',
    ])]);

    $resultado = (new GbifValidacionTaxonomicaAdapter)->validarEspecies(['Anacroneuria']);

    expect($resultado[0]['estado'])->toBe('catalogado')
        ->and($resultado[0]['sugerencias'])->toBe([]);
});

it('marca no catalogado cuando no hay candidatos confiables', function () {
    Http::fake(['api.gbif.org/*' => Http::response([
        'matchType' => 'NONE',
        'confidence' => 0,
        'alternatives' => [
            ['matchType' => 'FUZZY', 'confidence' => 30, 'canonicalName' => 'Algo dudoso'],
        ],
    ])]);

    $resultado = (new GbifValidacionTaxonomicaAdapter)->validarEspecies(['Xxxxx yyyyy']);

    expect($resultado[0]['estado'])->toBe('no_catalogado')
        ->and($resultado[0]['sugerencias'])->toBe([]);
});

it('no sugiere el mismo nombre ingresado', function () {
    Http::fake(['api.gbif.org/*' => Http::response([
        'matchType' => 'FUZZY',
        'confidence' => 90,
        'canonicalName' => 'Baetis',
        'alternatives' => [
            ['matchType' => 'FUZZY', 'confidence' => 95, 'canonicalName' => 'Baetodes'],
        ],
    ])]);

    $resultado = (new GbifValidacionTaxonomicaAdapter)->validarEspecies(['Baetis']);

    expect($resultado[0]['sugerencias'])->toBe(['Baetodes']);
});
