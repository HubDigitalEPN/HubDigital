<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EnlazarEspecimenAMuestra\EnlazarEspecimenAMuestraHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EnlazarEspecimenAMuestra\EnlazarEspecimenAMuestraInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\MuestraColecta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\MuestraColectaId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryMuestraColectaRepository;

test('enlaza un espécimen a una muestra de colecta existente', function (): void {
    $especimenRepo = new InMemoryEspecimenRepository;
    $muestraRepo = new InMemoryMuestraColectaRepository;
    $handler = new EnlazarEspecimenAMuestraHandler($especimenRepo, $muestraRepo);

    $muestra = MuestraColecta::crear(MuestraColectaId::generar(), codigoMuestra: 'BT2F3');
    $muestraRepo->guardar($muestra);

    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-INV-1',
        'taxon-uuid-dummy-0000-000000000001',
        'X',
        '2024-01-01',
        'C',
        oldCode: 'BT2F3',
    );
    $especimenRepo->guardar($especimen);

    $output = $handler->handle(new EnlazarEspecimenAMuestraInput(
        especimenId: (string) $especimen->id(),
        muestraId: (string) $muestra->id(),
    ));

    expect($output->muestraId)->toBe((string) $muestra->id());

    $persistido = $especimenRepo->buscarPorId($especimen->id());
    expect($persistido->muestraId())->toBe((string) $muestra->id());
});

test('rechaza una muestra inexistente', function (): void {
    $especimenRepo = new InMemoryEspecimenRepository;
    $muestraRepo = new InMemoryMuestraColectaRepository;

    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-INV-1',
        'taxon-uuid-dummy-0000-000000000001',
        'X',
        '2024-01-01',
        'C',
    );
    $especimenRepo->guardar($especimen);

    (new EnlazarEspecimenAMuestraHandler($especimenRepo, $muestraRepo))->handle(
        new EnlazarEspecimenAMuestraInput(
            especimenId: (string) $especimen->id(),
            muestraId: (string) MuestraColectaId::generar(),
        )
    );
})->throws(DomainException::class, 'Muestra');
