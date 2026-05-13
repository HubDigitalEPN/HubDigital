<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimen\ActualizarEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimen\ActualizarEspecimenInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EntidadDepositante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EntidadDepositanteId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEntidadDepositanteRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;

function especimenDePrueba(string $codigo = 'COLEOP-001'): Especimen
{
    return Especimen::crear(
        EspecimenId::generar(),
        $codigo,
        'taxon-uuid-dummy-0000-000000000001',
        'Pichincha',
        '2024-01-01',
        'Colector Original',
    );
}

test('actualizar un especimen con datos validos persiste los cambios', function (): void {
    $especimenRepo = new InMemoryEspecimenRepository;
    $especimen = especimenDePrueba();
    $especimenRepo->guardar($especimen);

    $output = (new ActualizarEspecimenHandler($especimenRepo, new InMemoryEntidadDepositanteRepository))->handle(
        new ActualizarEspecimenInput(
            especimenId: (string) $especimen->id(),
            localidad: 'Imbabura',
            fechaColecta: '2025-06-15',
            colector: 'Nuevo Colector',
            entidadDepositanteId: null,
        )
    );

    expect($output->localidad)->toBe('Imbabura')
        ->and($output->colector)->toBe('Nuevo Colector')
        ->and($output->fechaColecta)->toBe('2025-06-15')
        ->and($output->codigoCatalogo)->toBe('COLEOP-001')
        ->and($output->entidadDepositanteId)->toBeNull();
});

test('actualizar persiste los cambios en el repositorio', function (): void {
    $especimenRepo = new InMemoryEspecimenRepository;
    $especimen = especimenDePrueba();
    $especimenRepo->guardar($especimen);

    (new ActualizarEspecimenHandler($especimenRepo, new InMemoryEntidadDepositanteRepository))->handle(
        new ActualizarEspecimenInput(
            especimenId: (string) $especimen->id(),
            localidad: 'Manabí',
            fechaColecta: '2025-01-01',
            colector: 'Colector B',
            entidadDepositanteId: null,
        )
    );

    $persistido = $especimenRepo->buscarPorId($especimen->id());
    expect($persistido->localidad())->toBe('Manabí')
        ->and($persistido->colector())->toBe('Colector B');
});

test('actualizar recorta espacios de localidad y colector', function (): void {
    $especimenRepo = new InMemoryEspecimenRepository;
    $especimen = especimenDePrueba();
    $especimenRepo->guardar($especimen);

    (new ActualizarEspecimenHandler($especimenRepo, new InMemoryEntidadDepositanteRepository))->handle(
        new ActualizarEspecimenInput(
            especimenId: (string) $especimen->id(),
            localidad: '  Esmeraldas  ',
            fechaColecta: '2024-01-01',
            colector: '  Dr. Nuevo  ',
            entidadDepositanteId: null,
        )
    );

    $persistido = $especimenRepo->buscarPorId($especimen->id());
    expect($persistido->localidad())->toBe('Esmeraldas')
        ->and($persistido->colector())->toBe('Dr. Nuevo');
});

test('actualizar asigna entidad depositante cuando existe', function (): void {
    $especimenRepo = new InMemoryEspecimenRepository;
    $entidadRepo = new InMemoryEntidadDepositanteRepository;

    $especimen = especimenDePrueba();
    $especimenRepo->guardar($especimen);

    $entidad = EntidadDepositante::crear(
        EntidadDepositanteId::generar(),
        'Universidad Central',
        'institucion',
        'contacto@uce.edu.ec',
    );
    $entidadRepo->guardar($entidad);

    $output = (new ActualizarEspecimenHandler($especimenRepo, $entidadRepo))->handle(
        new ActualizarEspecimenInput(
            especimenId: (string) $especimen->id(),
            localidad: 'Quito',
            fechaColecta: '2024-01-01',
            colector: 'Colector',
            entidadDepositanteId: (string) $entidad->id(),
        )
    );

    expect($output->entidadDepositanteId)->toBe((string) $entidad->id());
});

test('actualizar un especimen inexistente lanza DomainException', function (): void {
    $handler = new ActualizarEspecimenHandler(
        new InMemoryEspecimenRepository,
        new InMemoryEntidadDepositanteRepository,
    );

    $handler->handle(new ActualizarEspecimenInput(
        especimenId: '00000000-0000-4000-8000-000000000000',
        localidad: 'Quito',
        fechaColecta: '2024-01-01',
        colector: 'Colector',
        entidadDepositanteId: null,
    ));
})->throws(DomainException::class);

test('actualizar con entidad depositante inexistente lanza DomainException', function (): void {
    $especimenRepo = new InMemoryEspecimenRepository;
    $especimen = especimenDePrueba();
    $especimenRepo->guardar($especimen);

    $handler = new ActualizarEspecimenHandler($especimenRepo, new InMemoryEntidadDepositanteRepository);

    $handler->handle(new ActualizarEspecimenInput(
        especimenId: (string) $especimen->id(),
        localidad: 'Quito',
        fechaColecta: '2024-01-01',
        colector: 'Colector',
        entidadDepositanteId: '00000000-0000-4000-8000-000000000000',
    ));
})->throws(DomainException::class);
