<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarLocalidadCanonicaParaVerbatim\ConfirmarLocalidadCanonicaParaVerbatimHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarLocalidadCanonicaParaVerbatim\ConfirmarLocalidadCanonicaParaVerbatimInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryLocalidadRepository;

test('enlaza todos los especímenes con un verbatim a la localidad canónica', function (): void {
    $especimenRepo = new InMemoryEspecimenRepository;
    $localidadRepo = new InMemoryLocalidadRepository;
    $yasuni = Localidad::crear(LocalidadId::generar(), 'Yasuní', 'area_protegida');
    $localidadRepo->guardar($yasuni);

    for ($i = 0; $i < 3; $i++) {
        $especimenRepo->guardar(Especimen::crear(
            EspecimenId::generar(),
            "COD-{$i}",
            null,
            'X',
            '2024-01-01',
            'C',
            localidadVerbatim: 'Yasuní, Onkonegare',
        ));
    }

    $output = (new ConfirmarLocalidadCanonicaParaVerbatimHandler($especimenRepo, $localidadRepo))->handle(
        new ConfirmarLocalidadCanonicaParaVerbatimInput(
            verbatim: 'Yasuní, Onkonegare',
            localidadId: (string) $yasuni->id(),
        )
    );

    expect($output->especimenesEnlazados)->toBe(3);

    foreach ($especimenRepo->buscarTodos() as $e) {
        expect($e->localidadId())->toBe((string) $yasuni->id());
    }
});

test('no toca especímenes con localidad_id ya asignada', function (): void {
    $especimenRepo = new InMemoryEspecimenRepository;
    $localidadRepo = new InMemoryLocalidadRepository;
    $yasuni = Localidad::crear(LocalidadId::generar(), 'Yasuní', 'area_protegida');
    $otra = Localidad::crear(LocalidadId::generar(), 'Otra', 'sitio');
    $localidadRepo->guardar($yasuni);
    $localidadRepo->guardar($otra);

    $enlazadoPrevio = Especimen::crear(
        EspecimenId::generar(),
        'COD-OK',
        null,
        'X',
        '2024-01-01',
        'C',
        localidadVerbatim: 'Yasuní',
    );
    $enlazadoPrevio->enlazarLocalidad($otra->id());
    $especimenRepo->guardar($enlazadoPrevio);

    $pendiente = Especimen::crear(
        EspecimenId::generar(),
        'COD-PENDIENTE',
        null,
        'X',
        '2024-01-01',
        'C',
        localidadVerbatim: 'Yasuní',
    );
    $especimenRepo->guardar($pendiente);

    $output = (new ConfirmarLocalidadCanonicaParaVerbatimHandler($especimenRepo, $localidadRepo))->handle(
        new ConfirmarLocalidadCanonicaParaVerbatimInput(
            verbatim: 'Yasuní',
            localidadId: (string) $yasuni->id(),
        )
    );

    expect($output->especimenesEnlazados)->toBe(1);

    $previo = $especimenRepo->buscarPorId($enlazadoPrevio->id());
    expect($previo->localidadId())->toBe((string) $otra->id());
});

test('rechaza localidad inexistente', function (): void {
    $especimenRepo = new InMemoryEspecimenRepository;
    $localidadRepo = new InMemoryLocalidadRepository;

    (new ConfirmarLocalidadCanonicaParaVerbatimHandler($especimenRepo, $localidadRepo))->handle(
        new ConfirmarLocalidadCanonicaParaVerbatimInput(
            verbatim: 'X',
            localidadId: (string) LocalidadId::generar(),
        )
    );
})->throws(DomainException::class, 'Localidad canónica');

test('rechaza verbatim vacío', function (): void {
    $localidadRepo = new InMemoryLocalidadRepository;
    $yasuni = Localidad::crear(LocalidadId::generar(), 'Yasuní', 'area_protegida');
    $localidadRepo->guardar($yasuni);

    (new ConfirmarLocalidadCanonicaParaVerbatimHandler(new InMemoryEspecimenRepository, $localidadRepo))->handle(
        new ConfirmarLocalidadCanonicaParaVerbatimInput(
            verbatim: '   ',
            localidadId: (string) $yasuni->id(),
        )
    );
})->throws(InvalidArgumentException::class, 'verbatim');
