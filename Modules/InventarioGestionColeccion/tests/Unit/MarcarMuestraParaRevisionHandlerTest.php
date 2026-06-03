<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarMuestraParaRevision\MarcarMuestraParaRevisionHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarMuestraParaRevision\MarcarMuestraParaRevisionInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\MuestraColecta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\MuestraColectaId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryMuestraColectaRepository;

test('marcar para revisión vuelve a estado pendiente y registra motivo', function (): void {
    $repo = new InMemoryMuestraColectaRepository;
    $muestra = MuestraColecta::crear(MuestraColectaId::generar());
    $muestra->confirmar();
    $repo->guardar($muestra);

    $output = (new MarcarMuestraParaRevisionHandler($repo))->handle(new MarcarMuestraParaRevisionInput(
        muestraId: (string) $muestra->id(),
        motivo: 'discrepancia en localidad detectada',
    ));

    expect($output->estadoRevision)->toBe('pendiente')
        ->and($output->motivoRevision)->toBe('discrepancia en localidad detectada');
});

test('marcar muestra inexistente lanza DomainException', function (): void {
    $repo = new InMemoryMuestraColectaRepository;

    (new MarcarMuestraParaRevisionHandler($repo))->handle(new MarcarMuestraParaRevisionInput(
        muestraId: (string) MuestraColectaId::generar(),
        motivo: 'X',
    ));
})->throws(DomainException::class, 'no encontrada');

test('motivo vacío rechaza la operación', function (): void {
    $repo = new InMemoryMuestraColectaRepository;
    $muestra = MuestraColecta::crear(MuestraColectaId::generar());
    $repo->guardar($muestra);

    (new MarcarMuestraParaRevisionHandler($repo))->handle(new MarcarMuestraParaRevisionInput(
        muestraId: (string) $muestra->id(),
        motivo: '',
    ));
})->throws(InvalidArgumentException::class, 'motivo');
