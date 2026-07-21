<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitudDeposito;

it('genera el número con prefijo DEP y 5 dígitos desde una secuencia', function () {
    expect((string) NumeroSolicitudDeposito::fromSecuencia(1))->toBe('MEPN-INV-DEP-00001')
        ->and((string) NumeroSolicitudDeposito::fromSecuencia(42))->toBe('MEPN-INV-DEP-00042');
});

it('reconstruye desde texto con el formato DEP', function () {
    expect((string) NumeroSolicitudDeposito::from('MEPN-INV-DEP-00007'))->toBe('MEPN-INV-DEP-00007');
});

it('rechaza el formato anterior sin DEP', function () {
    NumeroSolicitudDeposito::from('MEPN-INV-00007');
})->throws(DomainException::class);

it('rechaza secuencias menores a 1', function () {
    NumeroSolicitudDeposito::fromSecuencia(0);
})->throws(DomainException::class);
