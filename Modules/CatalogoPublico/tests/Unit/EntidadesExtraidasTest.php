<?php

declare(strict_types=1);

use Modules\CatalogoPublico\Domain\ValueObjects\EntidadesExtraidas;

test('vacio() produce entidades sin ningún campo poblado', function (): void {
    $entidades = EntidadesExtraidas::vacio();

    expect($entidades->genero)->toBeNull()
        ->and($entidades->especie)->toBeNull()
        ->and($entidades->occurrenceID)->toBeNull()
        ->and($entidades->localidad)->toBeNull()
        ->and($entidades->country)->toBeNull()
        ->and($entidades->estaVacio())->toBeTrue();
});

test('desde() normaliza espacios y descarta cadenas vacías como null', function (): void {
    $entidades = EntidadesExtraidas::desde(
        genero: '  Atta  ',
        especie: '',
        occurrenceID: 'EPN-0012',
        localidad: '   ',
        country: null,
    );

    expect($entidades->genero)->toBe('Atta')
        ->and($entidades->especie)->toBeNull()
        ->and($entidades->occurrenceID)->toBe('EPN-0012')
        ->and($entidades->localidad)->toBeNull()
        ->and($entidades->country)->toBeNull()
        ->and($entidades->estaVacio())->toBeFalse();
});

test('estaVacio() distingue cualquier campo poblado', function (): void {
    expect(EntidadesExtraidas::desde(genero: 'Atta')->estaVacio())->toBeFalse()
        ->and(EntidadesExtraidas::desde(country: 'Ecuador')->estaVacio())->toBeFalse()
        ->and(EntidadesExtraidas::desde()->estaVacio())->toBeTrue();
});
