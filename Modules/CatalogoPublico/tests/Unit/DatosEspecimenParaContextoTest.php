<?php

declare(strict_types=1);

use Modules\CatalogoPublico\Domain\ValueObjects\DatosEspecimenParaContexto;

test('crear() descarta campos con valor null o cadena vacía', function (): void {
    $datos = DatosEspecimenParaContexto::crear(
        occurrenceID: 'EPN-0012',
        camposVisibles: [
            'scientificName' => 'Atta cephalotes',
            'family' => 'Formicidae',
            'typeStatus' => null,
            'typeNotes' => '',
            'individualCount' => 3,
        ],
    );

    expect($datos->occurrenceID)->toBe('EPN-0012')
        ->and($datos->tieneCampo('scientificName'))->toBeTrue()
        ->and($datos->tieneCampo('family'))->toBeTrue()
        ->and($datos->tieneCampo('individualCount'))->toBeTrue()
        ->and($datos->tieneCampo('typeStatus'))->toBeFalse()
        ->and($datos->tieneCampo('typeNotes'))->toBeFalse()
        ->and($datos->valor('scientificName'))->toBe('Atta cephalotes')
        ->and($datos->valor('individualCount'))->toBe(3);
});

test('crear() lanza excepción cuando occurrenceID es vacío', function (): void {
    DatosEspecimenParaContexto::crear(occurrenceID: '   ', camposVisibles: []);
})->throws(InvalidArgumentException::class);
