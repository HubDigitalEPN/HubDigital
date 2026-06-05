<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProponerMuestrasPorOldCode\ProponerMuestrasPorOldCodeHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProponerMuestrasPorOldCode\ProponerMuestrasPorOldCodeInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\MuestraColectaId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;

function crearEspecimenConOldCode(string $oldCode, string $colector = 'Colector X', ?string $localidadVerbatim = null): Especimen
{
    return Especimen::crear(
        EspecimenId::generar(),
        'COD-'.bin2hex(random_bytes(4)),
        null,
        'X',
        '2024-01-01',
        $colector,
        oldCode: $oldCode,
        localidadVerbatim: $localidadVerbatim,
    );
}

test('agrupa especímenes por oldCode y propone como muestra', function (): void {
    $repo = new InMemoryEspecimenRepository;
    foreach (['BT2F3', 'BT2F3', 'BT2F3', 'LT2F2'] as $oc) {
        $repo->guardar(crearEspecimenConOldCode($oc));
    }

    $output = (new ProponerMuestrasPorOldCodeHandler($repo))->handle(
        new ProponerMuestrasPorOldCodeInput
    );

    expect($output->totalGrupos)->toBe(2)
        ->and($output->items[0]->oldCode)->toBe('BT2F3')
        ->and($output->items[0]->totalEspecimenes)->toBe(3)
        ->and($output->items[1]->oldCode)->toBe('LT2F2')
        ->and($output->items[1]->totalEspecimenes)->toBe(1);
});

test('ignora especímenes ya enlazados a una muestra', function (): void {
    $repo = new InMemoryEspecimenRepository;

    $enlazado = crearEspecimenConOldCode('BT2F3');
    $enlazado->enlazarMuestra(MuestraColectaId::generar());
    $repo->guardar($enlazado);

    $pendiente = crearEspecimenConOldCode('BT2F3');
    $repo->guardar($pendiente);

    $output = (new ProponerMuestrasPorOldCodeHandler($repo))->handle(
        new ProponerMuestrasPorOldCodeInput
    );

    expect($output->items[0]->totalEspecimenes)->toBe(1)
        ->and($output->items[0]->especimenIds[0])->toBe((string) $pendiente->id());
});

test('filtra grupos por mínimo de especímenes', function (): void {
    $repo = new InMemoryEspecimenRepository;
    $repo->guardar(crearEspecimenConOldCode('A'));
    $repo->guardar(crearEspecimenConOldCode('A'));
    $repo->guardar(crearEspecimenConOldCode('B'));

    $output = (new ProponerMuestrasPorOldCodeHandler($repo))->handle(
        new ProponerMuestrasPorOldCodeInput(minimoEspecimenes: 2)
    );

    expect($output->totalGrupos)->toBe(1)
        ->and($output->items[0]->oldCode)->toBe('A');
});

test('detecta colector y localidad verbatim comunes a todo el grupo', function (): void {
    $repo = new InMemoryEspecimenRepository;
    foreach (range(1, 3) as $_) {
        $repo->guardar(crearEspecimenConOldCode('BT2F3', 'Juan Pérez', 'Yasuní'));
    }

    $output = (new ProponerMuestrasPorOldCodeHandler($repo))->handle(
        new ProponerMuestrasPorOldCodeInput
    );

    expect($output->items[0]->colectorComun)->toBe('Juan Pérez')
        ->and($output->items[0]->localidadVerbatimComun)->toBe('Yasuní');
});

test('valor común es null si los especímenes difieren', function (): void {
    $repo = new InMemoryEspecimenRepository;
    $repo->guardar(crearEspecimenConOldCode('BT2F3', 'Juan'));
    $repo->guardar(crearEspecimenConOldCode('BT2F3', 'María'));

    $output = (new ProponerMuestrasPorOldCodeHandler($repo))->handle(
        new ProponerMuestrasPorOldCodeInput
    );

    expect($output->items[0]->colectorComun)->toBeNull();
});
