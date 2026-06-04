<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarLocalidadesPorVerbatim\BuscarLocalidadesPorVerbatimHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarLocalidadesPorVerbatim\BuscarLocalidadesPorVerbatimInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryLocalidadRepository;

test('encuentra candidatos por fragmento de nombre (substring case-insensitive)', function (): void {
    $repo = new InMemoryLocalidadRepository;
    $repo->guardar(Localidad::crear(LocalidadId::generar(), 'Yasuní', 'area_protegida'));
    $repo->guardar(Localidad::crear(LocalidadId::generar(), 'Yasuní Estación', 'sitio'));
    $repo->guardar(Localidad::crear(LocalidadId::generar(), 'Pichincha', 'provincia'));

    // Nota: el matching accent-tolerante con unaccent es trabajo de P5.1.
    $output = (new BuscarLocalidadesPorVerbatimHandler($repo))->handle(
        new BuscarLocalidadesPorVerbatimInput(verbatim: 'yasuní')
    );

    expect($output->verbatimConsultado)->toBe('yasuní')
        ->and($output->items)->toHaveCount(2);
});

test('ordena por puntaje de similitud descendente', function (): void {
    $repo = new InMemoryLocalidadRepository;
    $repo->guardar(Localidad::crear(LocalidadId::generar(), 'Yasuní Onkonegare', 'sitio'));
    $repo->guardar(Localidad::crear(LocalidadId::generar(), 'Yasuní', 'area_protegida'));

    $output = (new BuscarLocalidadesPorVerbatimHandler($repo))->handle(
        new BuscarLocalidadesPorVerbatimInput(verbatim: 'Yasuní')
    );

    // El nombre exacto debe tener mayor puntaje que la versión larga
    expect($output->items[0]->nombreCanonico)->toBe('Yasuní')
        ->and($output->items[0]->puntajeSimilitud)->toBeGreaterThan($output->items[1]->puntajeSimilitud);
});

test('respeta el límite solicitado', function (): void {
    $repo = new InMemoryLocalidadRepository;
    foreach (['Yasuní 1', 'Yasuní 2', 'Yasuní 3', 'Yasuní 4'] as $n) {
        $repo->guardar(Localidad::crear(LocalidadId::generar(), $n, 'sitio'));
    }

    $output = (new BuscarLocalidadesPorVerbatimHandler($repo))->handle(
        new BuscarLocalidadesPorVerbatimInput(verbatim: 'Yasuní', limite: 2)
    );

    expect($output->items)->toHaveCount(2);
});

test('verbatim vacío retorna lista vacía', function (): void {
    $repo = new InMemoryLocalidadRepository;
    $repo->guardar(Localidad::crear(LocalidadId::generar(), 'Yasuní', 'sitio'));

    $output = (new BuscarLocalidadesPorVerbatimHandler($repo))->handle(
        new BuscarLocalidadesPorVerbatimInput(verbatim: '   ')
    );

    expect($output->items)->toBe([]);
});
