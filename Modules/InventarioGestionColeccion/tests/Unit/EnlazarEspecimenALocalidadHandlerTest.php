<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EnlazarEspecimenALocalidad\EnlazarEspecimenALocalidadHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EnlazarEspecimenALocalidad\EnlazarEspecimenALocalidadInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryLocalidadRepository;

test('enlaza un espécimen a una localidad existente', function (): void {
    $especimenRepo = new InMemoryEspecimenRepository;
    $localidadRepo = new InMemoryLocalidadRepository;
    $handler = new EnlazarEspecimenALocalidadHandler($especimenRepo, $localidadRepo);

    $localidad = Localidad::crear(LocalidadId::generar(), 'Yasuní', 'area_protegida');
    $localidadRepo->guardar($localidad);

    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-INV-1',
        'taxon-uuid-dummy-0000-000000000001',
        'Yasuní (verbatim)',
        '2024-01-01',
        'Colector',
        localidadVerbatim: 'Yasuní (verbatim)',
    );
    $especimenRepo->guardar($especimen);

    $output = $handler->handle(new EnlazarEspecimenALocalidadInput(
        especimenId: (string) $especimen->id(),
        localidadId: (string) $localidad->id(),
    ));

    expect($output->localidadId)->toBe((string) $localidad->id())
        ->and($output->localidadVerbatim)->toBe('Yasuní (verbatim)');

    $persistido = $especimenRepo->buscarPorId($especimen->id());
    expect($persistido->localidadId())->toBe((string) $localidad->id());
});

test('rechaza un espécimen inexistente', function (): void {
    $especimenRepo = new InMemoryEspecimenRepository;
    $localidadRepo = new InMemoryLocalidadRepository;

    (new EnlazarEspecimenALocalidadHandler($especimenRepo, $localidadRepo))->handle(
        new EnlazarEspecimenALocalidadInput(
            especimenId: (string) EspecimenId::generar(),
            localidadId: (string) LocalidadId::generar(),
        )
    );
})->throws(DomainException::class, 'no encontrado');

test('rechaza una localidad inexistente', function (): void {
    $especimenRepo = new InMemoryEspecimenRepository;
    $localidadRepo = new InMemoryLocalidadRepository;

    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-INV-1',
        'taxon-uuid-dummy-0000-000000000001',
        'X',
        '2024-01-01',
        'C',
    );
    $especimenRepo->guardar($especimen);

    (new EnlazarEspecimenALocalidadHandler($especimenRepo, $localidadRepo))->handle(
        new EnlazarEspecimenALocalidadInput(
            especimenId: (string) $especimen->id(),
            localidadId: (string) LocalidadId::generar(),
        )
    );
})->throws(DomainException::class, 'Localidad');
