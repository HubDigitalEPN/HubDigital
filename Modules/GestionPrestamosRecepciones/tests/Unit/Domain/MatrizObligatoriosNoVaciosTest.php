<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\Entities\MatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\CamposObligatoriosVaciosException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;

/**
 * @param  array<int, array<string, mixed>>  $registros
 * @param  string[]  $columnas
 */
function matrizCon(array $registros, array $columnas): MatrizEspecies
{
    $matriz = MatrizEspecies::crear(
        id: MatrizEspeciesId::generate(),
        solicitudId: 'sol-1',
        camposDwCPresentes: array_fill_keys($columnas, true),
        tipoTramite: TipoTramite::Deposito->value,
    );

    foreach ($registros as $datos) {
        $matriz->agregarRegistroEspecimen(
            $datos['scientificName'] ?? '',
            datosDwC: $datos,
        );
    }

    return $matriz;
}

test('lanza excepción cuando un campo obligatorio llega vacío', function (): void {
    $matriz = matrizCon(
        registros: [
            ['scientificName' => 'Atta cephalotes', 'decimalLatitude' => '-0.2', 'decimalLongitude' => ''],
        ],
        columnas: ['scientificName', 'decimalLatitude', 'decimalLongitude'],
    );

    expect(fn () => $matriz->validarObligatoriosNoVacios(['scientificName', 'decimalLatitude', 'decimalLongitude']))
        ->toThrow(CamposObligatoriosVaciosException::class);
});

test('la excepción identifica la fila y el campo vacío', function (): void {
    $matriz = matrizCon(
        registros: [
            ['scientificName' => 'Atta cephalotes', 'decimalLatitude' => '', 'decimalLongitude' => '-78.5'],
        ],
        columnas: ['scientificName', 'decimalLatitude', 'decimalLongitude'],
    );

    try {
        $matriz->validarObligatoriosNoVacios(['decimalLatitude']);
        $this->fail('Se esperaba CamposObligatoriosVaciosException');
    } catch (CamposObligatoriosVaciosException $e) {
        expect($e->violaciones())->toBe([
            ['fila' => 'Atta cephalotes', 'campo' => 'decimalLatitude'],
        ]);
    }
});

test('no lanza cuando todos los obligatorios están completos', function (): void {
    $matriz = matrizCon(
        registros: [
            ['scientificName' => 'Atta cephalotes', 'decimalLatitude' => '-0.2', 'decimalLongitude' => '-78.5'],
            ['scientificName' => 'Camponotus sp.', 'decimalLatitude' => '1.1', 'decimalLongitude' => '-79.0'],
        ],
        columnas: ['scientificName', 'decimalLatitude', 'decimalLongitude'],
    );

    $matriz->validarObligatoriosNoVacios(['scientificName', 'decimalLatitude', 'decimalLongitude']);

    expect(true)->toBeTrue();
});

test('ignora campos críticos que no están presentes como columna', function (): void {
    $matriz = matrizCon(
        registros: [
            ['scientificName' => 'Atta cephalotes'],
        ],
        columnas: ['scientificName'],
    );

    // decimalLatitude no es columna presente: lo cubre validarCamposDwC(), no este invariante.
    $matriz->validarObligatoriosNoVacios(['scientificName', 'decimalLatitude']);

    expect(true)->toBeTrue();
});
