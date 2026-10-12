<?php

declare(strict_types=1);

use App\Rules\IdentificacionEcuatoriana;

test('valida cédulas y admite numeración consular sin inferir nacionalidad', function (string $numero, bool $valido): void {
    expect(IdentificacionEcuatoriana::cedulaValida($numero))->toBe($valido);
})->with([
    ['1707514251', true], ['1707514252', false], ['3041234560', false],
    ['3041234562', true], ['0000000000', false], ['2507514253', false],
    ['170751425', false], ['A707514251', false],
]);

test('comprueba RUC públicos privados y pasaportes sin atribuir existencia', function (string $tipo, string $numero, bool $valido): void {
    $errores = [];
    (new IdentificacionEcuatoriana($tipo))->validate('identificacion', $numero, function (string $error) use (&$errores): void { $errores[] = $error; });
    expect($errores === [])->toBe($valido);
})->with([
    ['ruc', '1760001550001', true], ['ruc', '1790016919001', true],
    ['ruc', '1791234567001', true], ['ruc', '1707514251001', true],
    ['ruc', '1707514251002', false], ['ruc', '0000000000001', false],
    ['pasaporte', 'AB1234567', true], ['pasaporte', '123456789', true],
    ['pasaporte', 'AB-12345', true], ['pasaporte', 'AB<123', false], ['pasaporte', 'ABC', false],
    ['pasaporte', 'CWHXXXXH', true],
]);
