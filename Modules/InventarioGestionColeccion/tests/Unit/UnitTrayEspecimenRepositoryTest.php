<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryUnitTrayEspecimenRepository;

test('sincronizar asigna los especimenes al unit tray', function (): void {
    $repo = new InMemoryUnitTrayEspecimenRepository;
    $tray = UnitTrayId::generar();

    $repo->sincronizar($tray, ['esp-1', 'esp-2']);

    expect($repo->especimenIdsPorUnitTray($tray))->toEqualCanonicalizing(['esp-1', 'esp-2']);
});

test('sincronizar reemplaza el conjunto anterior del tray', function (): void {
    $repo = new InMemoryUnitTrayEspecimenRepository;
    $tray = UnitTrayId::generar();

    $repo->sincronizar($tray, ['esp-1', 'esp-2']);
    $repo->sincronizar($tray, ['esp-2', 'esp-3']);

    expect($repo->especimenIdsPorUnitTray($tray))->toEqualCanonicalizing(['esp-2', 'esp-3']);
});

test('un especimen se reubica al asignarse a otro tray', function (): void {
    $repo = new InMemoryUnitTrayEspecimenRepository;
    $trayA = UnitTrayId::generar();
    $trayB = UnitTrayId::generar();

    $repo->sincronizar($trayA, ['esp-1']);
    $repo->sincronizar($trayB, ['esp-1']);

    expect($repo->especimenIdsPorUnitTray($trayA))->toBe([])
        ->and($repo->especimenIdsPorUnitTray($trayB))->toBe(['esp-1'])
        ->and((string) $repo->unitTrayDeEspecimen('esp-1'))->toBe((string) $trayB);
});

test('unitTrayDeEspecimen retorna null si no esta asignado', function (): void {
    $repo = new InMemoryUnitTrayEspecimenRepository;

    expect($repo->unitTrayDeEspecimen('esp-x'))->toBeNull();
});

test('eliminarPorUnitTray quita todas las asignaciones del tray', function (): void {
    $repo = new InMemoryUnitTrayEspecimenRepository;
    $tray = UnitTrayId::generar();

    $repo->sincronizar($tray, ['esp-1', 'esp-2']);
    $repo->eliminarPorUnitTray($tray);

    expect($repo->especimenIdsPorUnitTray($tray))->toBe([]);
});
