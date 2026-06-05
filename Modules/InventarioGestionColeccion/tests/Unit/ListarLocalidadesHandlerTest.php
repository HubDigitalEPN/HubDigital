<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidades\ListarLocalidadesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidades\ListarLocalidadesInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryLocalidadRepository;

test('listar paginado retorna metadata correcta', function (): void {
    $repo = new InMemoryLocalidadRepository;
    foreach (['A', 'B', 'C', 'D', 'E'] as $nombre) {
        $repo->guardar(Localidad::crear(LocalidadId::generar(), $nombre, 'sitio'));
    }

    $output = (new ListarLocalidadesHandler($repo))->handle(new ListarLocalidadesInput(page: 1, perPage: 2));

    expect($output->total)->toBe(5)
        ->and($output->totalPaginas)->toBe(3)
        ->and($output->page)->toBe(1)
        ->and($output->perPage)->toBe(2)
        ->and($output->items)->toHaveCount(2);
});

test('listar respeta el offset cuando se pide página 2', function (): void {
    $repo = new InMemoryLocalidadRepository;
    foreach (['A', 'B', 'C', 'D', 'E'] as $nombre) {
        $repo->guardar(Localidad::crear(LocalidadId::generar(), $nombre, 'sitio'));
    }

    $output = (new ListarLocalidadesHandler($repo))->handle(new ListarLocalidadesInput(page: 2, perPage: 2));

    expect($output->items[0]->nombreCanonico)->toBe('C')
        ->and($output->items[1]->nombreCanonico)->toBe('D');
});

test('listar resuelve nombre del padre', function (): void {
    $repo = new InMemoryLocalidadRepository;
    $pais = Localidad::crear(LocalidadId::generar(), 'Ecuador', 'pais');
    $repo->guardar($pais);
    $prov = Localidad::crear(LocalidadId::generar(), 'Pichincha', 'provincia', padreId: (string) $pais->id());
    $repo->guardar($prov);

    $output = (new ListarLocalidadesHandler($repo))->handle(new ListarLocalidadesInput);
    $pichincha = array_values(array_filter($output->items, fn ($i) => $i->nombreCanonico === 'Pichincha'))[0];

    expect($pichincha->padreNombre)->toBe('Ecuador');
});

test('listar con repositorio vacío devuelve totalPaginas=1', function (): void {
    $repo = new InMemoryLocalidadRepository;

    $output = (new ListarLocalidadesHandler($repo))->handle(new ListarLocalidadesInput);

    expect($output->total)->toBe(0)
        ->and($output->totalPaginas)->toBe(1)
        ->and($output->items)->toBe([]);
});
