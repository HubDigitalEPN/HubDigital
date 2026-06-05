<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarEspecimenParaRevision\MarcarEspecimenParaRevisionHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarEspecimenParaRevision\MarcarEspecimenParaRevisionInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;

test('marca un espécimen para revisión con motivo', function (): void {
    $repo = new InMemoryEspecimenRepository;
    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-INV-1',
        'taxon-uuid-dummy-0000-000000000001',
        'X',
        '2024-01-01',
        'C',
    );
    $repo->guardar($especimen);

    $output = (new MarcarEspecimenParaRevisionHandler($repo))->handle(new MarcarEspecimenParaRevisionInput(
        especimenId: (string) $especimen->id(),
        motivo: 'taxonomic_notes mencionan clade incompatible',
    ));

    expect($output->estadoRevision)->toBe('pendiente')
        ->and($output->motivoRevision)->toBe('taxonomic_notes mencionan clade incompatible');
});

test('rechaza espécimen inexistente', function (): void {
    $repo = new InMemoryEspecimenRepository;

    (new MarcarEspecimenParaRevisionHandler($repo))->handle(new MarcarEspecimenParaRevisionInput(
        especimenId: (string) EspecimenId::generar(),
        motivo: 'X',
    ));
})->throws(DomainException::class, 'no encontrado');

test('motivo vacío rechaza la operación', function (): void {
    $repo = new InMemoryEspecimenRepository;
    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-INV-1',
        'taxon-uuid-dummy-0000-000000000001',
        'X',
        '2024-01-01',
        'C',
    );
    $repo->guardar($especimen);

    (new MarcarEspecimenParaRevisionHandler($repo))->handle(new MarcarEspecimenParaRevisionInput(
        especimenId: (string) $especimen->id(),
        motivo: '   ',
    ));
})->throws(InvalidArgumentException::class, 'motivo');
