<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\Entities\RegistroEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\RegistroEspecimenId;

/**
 * Registro con una celda que la normalización marcó como inválida, que es el único
 * caso en el que el curador tiene permitido intervenir.
 */
function registroConCeldaAnomala(string $campo = 'decimalLongitude', mixed $valorMalo = '-76.4000001'): RegistroEspecimen
{
    return RegistroEspecimen::crear(
        id: RegistroEspecimenId::generate(),
        nombreCientifico: 'Schistocerca',
        datosDwC: ['scientificName' => 'Schistocerca', $campo => $valorMalo],
        normalizaciones: [[
            'campo' => $campo,
            'original' => $valorMalo,
            'normalizado' => null,
            'invalido' => true,
            'mensaje' => 'fuera de rango',
        ]],
    );
}

test('el curador puede sanar una celda marcada como anomala', function (): void {
    $registro = registroConCeldaAnomala();

    $registro->corregirCeldaAnomala('decimalLongitude', -76.4, 'curador-1', new DateTimeImmutable('2026-07-21T10:00:00+00:00'));

    expect($registro->datosDwC()['decimalLongitude'])->toBe(-76.4)
        ->and($registro->camposAnomalos())->toBe([])
        ->and($registro->fueCorregidoPorCuraduria())->toBeTrue();
});

test('la correccion deja auditoria de quien, que y cuando', function (): void {
    $registro = registroConCeldaAnomala();

    $registro->corregirCeldaAnomala('decimalLongitude', -76.4, 'curador-1', new DateTimeImmutable('2026-07-21T10:00:00+00:00'));

    $auditoria = $registro->correccionesCuratoriales();

    expect($auditoria)->toHaveCount(1)
        ->and($auditoria[0]['campo'])->toBe('decimalLongitude')
        ->and($auditoria[0]['anterior'])->toBe('-76.4000001')
        ->and($auditoria[0]['nuevo'])->toBe(-76.4)
        ->and($auditoria[0]['curadorId'])->toBe('curador-1')
        ->and($auditoria[0]['corregidoEn'])->toStartWith('2026-07-21T10:00:00');
});

test('no se puede tocar un campo que el sistema dio por bueno', function (): void {
    $registro = registroConCeldaAnomala();

    // scientificName está en los datos pero no marcado como anómalo
    $registro->corregirCeldaAnomala('scientificName', 'Otra especie', 'curador-1', new DateTimeImmutable);
})->throws(DomainException::class, 'no está marcado como anómalo');

test('no se puede tocar un campo que ni siquiera existe en la matriz', function (): void {
    $registro = registroConCeldaAnomala();

    $registro->corregirCeldaAnomala('campoInventado', 'x', 'curador-1', new DateTimeImmutable);
})->throws(DomainException::class);

test('toda correccion exige saber quien la hizo', function (): void {
    $registro = registroConCeldaAnomala();

    $registro->corregirCeldaAnomala('decimalLongitude', -76.4, '  ', new DateTimeImmutable);
})->throws(DomainException::class, 'quién la hizo');

test('se conserva el valor original que declaro el depositante', function (): void {
    $registro = registroConCeldaAnomala();

    $registro->corregirCeldaAnomala('decimalLongitude', -76.4, 'curador-1', new DateTimeImmutable);

    $norm = $registro->normalizaciones()[0];

    expect($norm['original'])->toBe('-76.4000001')
        ->and($norm['normalizado'])->toBe(-76.4)
        ->and($norm['corregidoPorCuraduria'])->toBeTrue();
});

test('una celda ya saneada no se puede volver a corregir', function (): void {
    $registro = registroConCeldaAnomala();
    $registro->corregirCeldaAnomala('decimalLongitude', -76.4, 'curador-1', new DateTimeImmutable);

    // Ya no está marcada como inválida, así que queda fuera del alcance permitido
    $registro->corregirCeldaAnomala('decimalLongitude', -1.0, 'curador-1', new DateTimeImmutable);
})->throws(DomainException::class, 'no está marcado como anómalo');

test('varias correcciones se acumulan sin pisarse', function (): void {
    $registro = RegistroEspecimen::crear(
        id: RegistroEspecimenId::generate(),
        nombreCientifico: 'Schistocerca',
        datosDwC: ['decimalLatitude' => 'x', 'decimalLongitude' => 'y'],
        normalizaciones: [
            ['campo' => 'decimalLatitude', 'original' => 'x', 'normalizado' => null, 'invalido' => true],
            ['campo' => 'decimalLongitude', 'original' => 'y', 'normalizado' => null, 'invalido' => true],
        ],
    );

    $registro->corregirCeldaAnomala('decimalLatitude', -4.03, 'curador-1', new DateTimeImmutable);
    $registro->corregirCeldaAnomala('decimalLongitude', -79.2, 'curador-2', new DateTimeImmutable);

    expect($registro->correccionesCuratoriales())->toHaveCount(2)
        ->and($registro->camposAnomalos())->toBe([])
        ->and(array_column($registro->correccionesCuratoriales(), 'curadorId'))->toBe(['curador-1', 'curador-2']);
});
