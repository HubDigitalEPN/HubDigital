<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\Entities\RegistroEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRegistroEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\RegistroEspecimenId;

function registroNoCatalogado(): RegistroEspecimen
{
    return RegistroEspecimen::crear(
        id: RegistroEspecimenId::generate(),
        nombreCientifico: 'Baetodes nuevo',
        noCatalogado: true,
    );
}

it('guarda el comentario libre al justificar y recorta espacios', function () {
    $registro = registroNoCatalogado();

    $registro->justificar('Es una especie nueva', '  Colectada en zona de páramo sin registro previo  ');

    expect($registro->estado())->toEqual(EstadoRegistroEspecimen::ValidacionManualPorCuraduria)
        ->and($registro->motivoJustificacion())->toBe('Es una especie nueva')
        ->and($registro->comentarioJustificacion())->toBe('Colectada en zona de páramo sin registro previo');
});

it('deja el comentario en null cuando llega vacío o solo espacios', function () {
    $registro = registroNoCatalogado();

    $registro->justificar('No listada en catálogo', '   ');

    expect($registro->comentarioJustificacion())->toBeNull();
});

it('permite justificar sin comentario', function () {
    $registro = registroNoCatalogado();

    $registro->justificar('Es una especie nueva');

    expect($registro->comentarioJustificacion())->toBeNull();
});

it('actualiza el comentario al cambiar la justificación', function () {
    $registro = registroNoCatalogado();
    $registro->justificar('Es una especie nueva', 'comentario inicial');

    $registro->cambiarJustificacion('No listada en catálogo', 'comentario corregido');

    expect($registro->motivoJustificacion())->toBe('No listada en catálogo')
        ->and($registro->comentarioJustificacion())->toBe('comentario corregido');
});

it('reconstituye el comentario desde persistencia', function () {
    $registro = RegistroEspecimen::reconstituir(
        id: RegistroEspecimenId::generate(),
        nombreCientifico: 'Baetodes nuevo',
        nombreCorregido: null,
        estado: EstadoRegistroEspecimen::ValidacionManualPorCuraduria,
        noCatalogado: true,
        motivoJustificacion: 'Es una especie nueva',
        comentarioJustificacion: 'un comentario guardado',
    );

    expect($registro->comentarioJustificacion())->toBe('un comentario guardado');
});
