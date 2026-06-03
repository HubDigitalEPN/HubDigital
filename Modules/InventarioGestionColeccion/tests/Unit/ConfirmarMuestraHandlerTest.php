<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarMuestra\ConfirmarMuestraHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarMuestra\ConfirmarMuestraInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\MuestraColecta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\MuestraColectaId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryMuestraColectaRepository;

test('confirmar una muestra pendiente cambia su estado y limpia motivo', function (): void {
    $repo = new InMemoryMuestraColectaRepository;
    $muestra = MuestraColecta::crear(MuestraColectaId::generar(), motivoRevision: 'pendiente de chequeo');
    $repo->guardar($muestra);

    $output = (new ConfirmarMuestraHandler($repo))->handle(
        new ConfirmarMuestraInput(muestraId: (string) $muestra->id())
    );

    expect($output->estadoRevision)->toBe('confirmada');

    $persistida = $repo->buscarPorId($muestra->id());
    expect($persistida->motivoRevision())->toBeNull();
});

test('confirmar muestra inexistente lanza DomainException', function (): void {
    $repo = new InMemoryMuestraColectaRepository;

    (new ConfirmarMuestraHandler($repo))->handle(new ConfirmarMuestraInput(
        muestraId: (string) MuestraColectaId::generar()
    ));
})->throws(DomainException::class, 'no encontrada');

test('confirmar una muestra ya confirmada lanza DomainException', function (): void {
    $repo = new InMemoryMuestraColectaRepository;
    $muestra = MuestraColecta::crear(MuestraColectaId::generar());
    $repo->guardar($muestra);

    $handler = new ConfirmarMuestraHandler($repo);
    $handler->handle(new ConfirmarMuestraInput(muestraId: (string) $muestra->id()));
    $handler->handle(new ConfirmarMuestraInput(muestraId: (string) $muestra->id()));
})->throws(DomainException::class);
