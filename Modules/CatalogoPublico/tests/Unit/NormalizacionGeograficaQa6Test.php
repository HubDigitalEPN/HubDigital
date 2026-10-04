<?php

declare(strict_types=1);

use Modules\CatalogoPublico\Infrastructure\NormalizacionGeografica;

test('QA6 el selector muestra una opción por equivalencia y preserva filtros con grafías originales', function (): void {
    $originales = ['Narino', 'Nariño', 'NARIÑO', 'Choco', 'Chocó', 'Granada', 'Pichincha', "\u{00A0}"];
    $opciones = NormalizacionGeografica::nombresDisponibles($originales);
    expect($opciones)->toBe(['Chocó', 'Granada', 'Nariño', 'Pichincha'])
        ->and(NormalizacionGeografica::normalizar('Nariño'))->toBe(NormalizacionGeografica::normalizar('Narino'))
        ->and(NormalizacionGeografica::normalizar('Chocó'))->toBe(NormalizacionGeografica::normalizar('Choco'))
        ->and($originales[0])->toBe('Narino');
});

test('QA6 los valores de URL y barras resuelven una opción existente sin crear provincias', function (): void {
    $opciones = ['Nariño', 'Chocó', 'Pichincha'];
    expect(NormalizacionGeografica::nombreDisponible('  NARINO  ', $opciones))->toBe('Nariño')
        ->and(NormalizacionGeografica::nombreDisponible('Choco', $opciones))->toBe('Chocó')
        ->and(NormalizacionGeografica::nombreDisponible('Nueva provincia', $opciones))->toBeNull()
        ->and(NormalizacionGeografica::nombreDisponible('', $opciones))->toBeNull();
});
