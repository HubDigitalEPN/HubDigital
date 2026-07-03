<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\SepararEspecimenesEnNuevaMuestra\SepararEspecimenesEnNuevaMuestraHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\SepararEspecimenesEnNuevaMuestra\SepararEspecimenesEnNuevaMuestraInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\MuestraColecta;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryMuestraColectaRepository;

/**
 * Cubre las invariantes que se validan ANTES de la transacción (por eso no
 * necesitan una BD real). El camino feliz se prueba en el e2e contra Postgres.
 */
function bootstrapSeparar(): array
{
    $especimenRepo = new InMemoryEspecimenRepository;
    $muestraRepo = new InMemoryMuestraColectaRepository;

    $muestra = MuestraColecta::crear(
        id: $muestraRepo->nextIdentity(),
        codigoMuestra: '100',
    );
    $muestraId = (string) $muestra->id();
    $muestraRepo->guardar($muestra);

    $ids = [];
    foreach (['A1', 'B2'] as $codigo) {
        $e = Especimen::crear(
            id: $especimenRepo->nextIdentity(),
            codigoCatalogo: $codigo,
            taxonId: null,
            localidad: 'Sitio',
            fechaColecta: '',
            colector: 'Colector',
            muestraId: $muestraId,
        );
        $especimenRepo->guardar($e);
        $ids[] = (string) $e->id();
    }

    $handler = new SepararEspecimenesEnNuevaMuestraHandler($especimenRepo, $muestraRepo);

    return [$handler, $muestraId, $ids];
}

test('separar sin selección lanza error', function (): void {
    [$handler, $muestraId] = bootstrapSeparar();

    expect(fn () => $handler->handle(new SepararEspecimenesEnNuevaMuestraInput(
        muestraOrigenId: $muestraId,
        especimenIds: [],
    )))->toThrow(DomainException::class, 'al menos un espécimen');
});

test('separar todos los especímenes lanza error (debe quedar al menos uno)', function (): void {
    [$handler, $muestraId, $ids] = bootstrapSeparar();

    expect(fn () => $handler->handle(new SepararEspecimenesEnNuevaMuestraInput(
        muestraOrigenId: $muestraId,
        especimenIds: $ids, // los dos
    )))->toThrow(DomainException::class, 'No puedes separar todos');
});

test('separar de una muestra inexistente lanza error', function (): void {
    [$handler, , $ids] = bootstrapSeparar();

    expect(fn () => $handler->handle(new SepararEspecimenesEnNuevaMuestraInput(
        muestraOrigenId: '11111111-1111-4111-8111-111111111111', // UUID válido, inexistente
        especimenIds: [$ids[0]],
    )))->toThrow(DomainException::class, 'no encontrada');
});

test('ids ajenos a la muestra se ignoran; sin válidos lanza error', function (): void {
    [$handler, $muestraId] = bootstrapSeparar();

    expect(fn () => $handler->handle(new SepararEspecimenesEnNuevaMuestraInput(
        muestraOrigenId: $muestraId,
        especimenIds: ['id-que-no-pertenece'],
    )))->toThrow(DomainException::class, 'al menos un espécimen');
});
