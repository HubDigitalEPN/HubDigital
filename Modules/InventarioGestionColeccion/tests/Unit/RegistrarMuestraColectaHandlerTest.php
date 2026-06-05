<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarMuestraColecta\RegistrarMuestraColectaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarMuestraColecta\RegistrarMuestraColectaInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryLocalidadRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryMuestraColectaRepository;

test('registra una muestra mínima en estado pendiente', function (): void {
    $muestraRepo = new InMemoryMuestraColectaRepository;
    $localidadRepo = new InMemoryLocalidadRepository;
    $handler = new RegistrarMuestraColectaHandler($muestraRepo, $localidadRepo);

    $output = $handler->handle(new RegistrarMuestraColectaInput);

    expect($output->estadoRevision)->toBe('pendiente')
        ->and($muestraRepo->buscarPorId($output->id))->not->toBeNull();
});

test('registra con código de muestra y localidad enlazada', function (): void {
    $muestraRepo = new InMemoryMuestraColectaRepository;
    $localidadRepo = new InMemoryLocalidadRepository;
    $localidad = Localidad::crear(LocalidadId::generar(), 'Yasuní', 'area_protegida');
    $localidadRepo->guardar($localidad);

    $handler = new RegistrarMuestraColectaHandler($muestraRepo, $localidadRepo);
    $output = $handler->handle(new RegistrarMuestraColectaInput(
        codigoMuestra: 'BT2F3',
        fechaColecta: '2001-07-21',
        fechaVerbatim: '21-Jul-01',
        localidadId: (string) $localidad->id(),
        colector: 'Juan Pérez',
    ));

    expect($output->codigoMuestra)->toBe('BT2F3')
        ->and($output->fechaColecta)->toBe('2001-07-21')
        ->and($output->localidadId)->toBe((string) $localidad->id())
        ->and($output->colector)->toBe('Juan Pérez');
});

test('rechaza una localidad_id inexistente', function (): void {
    $muestraRepo = new InMemoryMuestraColectaRepository;
    $localidadRepo = new InMemoryLocalidadRepository;
    $handler = new RegistrarMuestraColectaHandler($muestraRepo, $localidadRepo);

    $handler->handle(new RegistrarMuestraColectaInput(
        localidadId: (string) LocalidadId::generar(),
    ));
})->throws(DomainException::class, 'no encontrada');

test('persiste motivo de revisión cuando se provee', function (): void {
    $muestraRepo = new InMemoryMuestraColectaRepository;
    $localidadRepo = new InMemoryLocalidadRepository;
    $handler = new RegistrarMuestraColectaHandler($muestraRepo, $localidadRepo);

    $output = $handler->handle(new RegistrarMuestraColectaInput(
        codigoMuestra: 'BT2F3',
        motivoRevision: 'agrupación por oldCode sin confirmar',
    ));

    expect($output->motivoRevision)->toBe('agrupación por oldCode sin confirmar')
        ->and($output->estadoRevision)->toBe('pendiente');
});
