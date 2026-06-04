<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EntidadDepositante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EntidadDepositanteId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoEntidadDepositante;

test('crear una entidad depositante almacena los datos correctamente', function (): void {
    $entidad = EntidadDepositante::crear(
        EntidadDepositanteId::generar(),
        'Universidad Central',
        'institucion',
        'contacto@uce.edu.ec',
    );

    expect($entidad->nombre())->toBe('Universidad Central')
        ->and($entidad->tipo())->toBe(TipoEntidadDepositante::Institucion)
        ->and($entidad->contacto())->toBe('contacto@uce.edu.ec');
});

test('crear recorta espacios del nombre y contacto', function (): void {
    $entidad = EntidadDepositante::crear(
        EntidadDepositanteId::generar(),
        '  Universidad Central  ',
        'institucion',
        '  contacto@uce.edu.ec  ',
    );

    expect($entidad->nombre())->toBe('Universidad Central')
        ->and($entidad->contacto())->toBe('contacto@uce.edu.ec');
});

test('crear con tipo invalido lanza InvalidArgumentException', function (): void {
    EntidadDepositante::crear(
        EntidadDepositanteId::generar(),
        'Entidad',
        'tipo_inexistente',
        'contacto@ejemplo.com',
    );
})->throws(InvalidArgumentException::class, 'tipo');

test('crear acepta todos los tipos validos', function (string $tipo): void {
    $entidad = EntidadDepositante::crear(
        EntidadDepositanteId::generar(),
        'Entidad de prueba',
        $tipo,
        'contacto@ejemplo.com',
    );

    expect($entidad->tipo()->value)->toBe($tipo);
})->with(['institucion', 'persona']);

test('actualizar modifica nombre tipo y contacto', function (): void {
    $entidad = EntidadDepositante::crear(
        EntidadDepositanteId::generar(),
        'Universidad Central',
        'institucion',
        'contacto@uce.edu.ec',
    );

    $entidad->actualizar('Dr. Juan Perez', 'persona', 'juan@correo.com');

    expect($entidad->nombre())->toBe('Dr. Juan Perez')
        ->and($entidad->tipo())->toBe(TipoEntidadDepositante::Persona)
        ->and($entidad->contacto())->toBe('juan@correo.com');
});

test('actualizar con tipo invalido lanza InvalidArgumentException', function (): void {
    $entidad = EntidadDepositante::crear(
        EntidadDepositanteId::generar(),
        'Universidad Central',
        'institucion',
        'contacto@uce.edu.ec',
    );

    $entidad->actualizar('Nombre', 'tipo_invalido', 'contacto');
})->throws(InvalidArgumentException::class);

test('el id asignado en crear se conserva', function (): void {
    $id = EntidadDepositanteId::generar();
    $entidad = EntidadDepositante::crear($id, 'Test', 'persona', 'test@test.com');

    expect((string) $entidad->id())->toBe((string) $id);
});

test('crear acepta nombre solo, dejando tipo y contacto en null', function (): void {
    $entidad = EntidadDepositante::crear(
        EntidadDepositanteId::generar(),
        'Donante anónimo',
    );

    expect($entidad->nombre())->toBe('Donante anónimo')
        ->and($entidad->tipo())->toBeNull()
        ->and($entidad->contacto())->toBeNull();
});

test('crear con tipo o contacto vacíos los normaliza a null', function (?string $tipo, ?string $contacto): void {
    $entidad = EntidadDepositante::crear(
        EntidadDepositanteId::generar(),
        'Donante',
        $tipo,
        $contacto,
    );

    expect($entidad->tipo())->toBeNull()
        ->and($entidad->contacto())->toBeNull();
})->with([
    [null, null],
    ['', ''],
    ['   ', '   '],
]);

test('actualizar puede llevar tipo y contacto a null', function (): void {
    $entidad = EntidadDepositante::crear(
        EntidadDepositanteId::generar(),
        'Universidad Central',
        'institucion',
        'contacto@uce.edu.ec',
    );

    $entidad->actualizar('Universidad Central', null, null);

    expect($entidad->tipo())->toBeNull()
        ->and($entidad->contacto())->toBeNull();
});
