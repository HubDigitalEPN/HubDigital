<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\AplicarEdicionMasivaEspecimenes\AplicarEdicionMasivaEspecimenesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\AplicarEdicionMasivaEspecimenes\AplicarEdicionMasivaEspecimenesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DeshacerEdicionMasiva\DeshacerEdicionMasivaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DeshacerEdicionMasiva\DeshacerEdicionMasivaInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEdicionesMasivas\ListarEdicionesMasivasHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEdicionesMasivas\ListarEdicionesMasivasInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReemplazarTextoEnEspecimenes\ReemplazarTextoEnEspecimenesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReemplazarTextoEnEspecimenes\ReemplazarTextoEnEspecimenesInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\NormalizadorValorCampoEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryBitacoraEdicionMasivaRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;

/** Transacción de mentira: ejecuta el bloque tal cual. */
function transaccionesDirectas(): TransactionManagerPort
{
    return new class implements TransactionManagerPort
    {
        public function executeTransactional(callable $callback): mixed
        {
            return $callback();
        }
    };
}

/**
 * Siembra N especímenes y devuelve [handlerFijar, handlerReemplazar, handlerDeshacer,
 * repoEspecimenes, repoBitacora, ids].
 */
function sembrarEdicionMasiva(int $cuantos = 3): array
{
    $especimenes = new InMemoryEspecimenRepository;
    $bitacora = new InMemoryBitacoraEdicionMasivaRepository;
    $normalizador = new NormalizadorValorCampoEspecimen;
    $tx = transaccionesDirectas();

    $ids = [];
    for ($i = 1; $i <= $cuantos; $i++) {
        $id = EspecimenId::generar();
        $ids[] = (string) $id;
        $especimenes->guardar(Especimen::crear(
            $id, "MEPN-{$i}", null, 'Yasuni', '2001-01-01', 'Troya, A.',
            biome: 'bosque humedo',
        ));
    }

    return [
        new AplicarEdicionMasivaEspecimenesHandler($especimenes, $bitacora, $normalizador, $tx),
        new ReemplazarTextoEnEspecimenesHandler($especimenes, $bitacora, $normalizador, $tx),
        new DeshacerEdicionMasivaHandler($especimenes, $bitacora, $tx),
        $especimenes,
        $bitacora,
        $ids,
    ];
}

function valorDe(InMemoryEspecimenRepository $repo, string $id, string $campo): ?string
{
    return $repo->buscarPorId(EspecimenId::desde($id))?->valorDeCampoEditable($campo);
}

// ── Fijar un valor ──────────────────────────────────────────────────────────

test('fija el campo en los especímenes seleccionados y no toca el resto', function (): void {
    [$fijar, , , $repo, , $ids] = sembrarEdicionMasiva(3);

    $out = $fijar->handle(new AplicarEdicionMasivaEspecimenesInput(
        especimenIds: [$ids[0], $ids[1]], campo: 'biome', valor: 'páramo',
    ));

    expect($out->cambiados)->toBe(2)
        ->and(valorDe($repo, $ids[0], 'biome'))->toBe('páramo')
        ->and(valorDe($repo, $ids[1], 'biome'))->toBe('páramo')
        ->and(valorDe($repo, $ids[2], 'biome'))->toBe('bosque humedo');
});

test('las filas que ya tenían el valor no se cuentan ni se registran', function (): void {
    [$fijar, , , , $bitacora, $ids] = sembrarEdicionMasiva(2);

    $out = $fijar->handle(new AplicarEdicionMasivaEspecimenesInput(
        especimenIds: $ids, campo: 'biome', valor: 'bosque humedo',
    ));

    // Si se registraran, deshacer las "revertiría" a un valor que nunca dejaron.
    expect($out->cambiados)->toBe(0)
        ->and($out->sinCambio)->toBe(2)
        ->and($out->edicionId)->toBeNull()
        ->and($bitacora->listarRecientes())->toBe([]);
});

test('vaciar un campo escribe null y queda registrado para poder deshacerlo', function (): void {
    [$fijar, , , $repo, $bitacora, $ids] = sembrarEdicionMasiva(1);

    $out = $fijar->handle(new AplicarEdicionMasivaEspecimenesInput(
        especimenIds: $ids, campo: 'biome', vaciar: true,
    ));

    expect($out->cambiados)->toBe(1)
        ->and(valorDe($repo, $ids[0], 'biome'))->toBeNull()
        ->and($bitacora->detallesDe($out->edicionId)[0]->valorPrevio())->toBe('bosque humedo');
});

test('la simulación calcula el efecto sin escribir ni dejar bitácora', function (): void {
    [$fijar, , , $repo, $bitacora, $ids] = sembrarEdicionMasiva(2);

    $out = $fijar->handle(new AplicarEdicionMasivaEspecimenesInput(
        especimenIds: $ids, campo: 'biome', valor: 'páramo', simular: true,
    ));

    expect($out->cambiados)->toBe(2)
        ->and($out->muestra[0]['previo'])->toBe('bosque humedo')
        ->and($out->muestra[0]['nuevo'])->toBe('páramo')
        ->and(valorDe($repo, $ids[0], 'biome'))->toBe('bosque humedo')
        ->and($bitacora->listarRecientes())->toBe([]);
});

test('una selección vacía se rechaza', function (): void {
    [$fijar] = sembrarEdicionMasiva(1);
    $fijar->handle(new AplicarEdicionMasivaEspecimenesInput(especimenIds: [], campo: 'biome', valor: 'x'));
})->throws(InvalidArgumentException::class, 'No hay especímenes seleccionados');

test('un campo fuera de la lista blanca se rechaza', function (): void {
    [$fijar, , , , , $ids] = sembrarEdicionMasiva(1);
    $fijar->handle(new AplicarEdicionMasivaEspecimenesInput(
        especimenIds: $ids, campo: 'codigoCatalogo', valor: 'X',
    ));
})->throws(InvalidArgumentException::class, 'no se puede editar en masa');

// ── Buscar y reemplazar ─────────────────────────────────────────────────────

test('reemplaza el texto solo donde aparece y guarda el previo de cada fila', function (): void {
    [$fijar, $reemplazar, , $repo, $bitacora, $ids] = sembrarEdicionMasiva(3);
    // Una de las tres queda con otro bioma para que no coincida.
    $fijar->handle(new AplicarEdicionMasivaEspecimenesInput(
        especimenIds: [$ids[2]], campo: 'biome', valor: 'páramo',
    ));

    $out = $reemplazar->handle(new ReemplazarTextoEnEspecimenesInput(
        especimenIds: $ids, campo: 'biome', buscar: 'humedo', reemplazo: 'húmedo',
    ));

    expect($out->cambiados)->toBe(2)
        ->and($out->sinCoincidencia)->toBe(1)
        ->and(valorDe($repo, $ids[0], 'biome'))->toBe('bosque húmedo')
        ->and(valorDe($repo, $ids[2], 'biome'))->toBe('páramo');

    // Cada fila guarda SU valor previo: es lo que justifica la tabla de detalle.
    $detalles = $bitacora->detallesDe($out->edicionId);
    expect($detalles)->toHaveCount(2)
        ->and($detalles[0]->valorPrevio())->toBe('bosque humedo');
});

test('reemplazar en un campo que no es de texto se rechaza', function (): void {
    [, $reemplazar, , , , $ids] = sembrarEdicionMasiva(1);
    $reemplazar->handle(new ReemplazarTextoEnEspecimenesInput(
        especimenIds: $ids, campo: 'endemic', buscar: 'a', reemplazo: 'b',
    ));
})->throws(InvalidArgumentException::class, 'no lo es');

test('buscar un texto vacío se rechaza', function (): void {
    [, $reemplazar, , , , $ids] = sembrarEdicionMasiva(1);
    $reemplazar->handle(new ReemplazarTextoEnEspecimenesInput(
        especimenIds: $ids, campo: 'biome', buscar: '  ', reemplazo: 'b',
    ));
})->throws(InvalidArgumentException::class, 'texto que quieres buscar');

// ── Deshacer ────────────────────────────────────────────────────────────────

test('deshacer devuelve todas las filas a su valor previo', function (): void {
    [$fijar, , $deshacer, $repo, , $ids] = sembrarEdicionMasiva(3);
    $out = $fijar->handle(new AplicarEdicionMasivaEspecimenesInput(
        especimenIds: $ids, campo: 'biome', valor: 'páramo',
    ));

    $rev = $deshacer->handle(new DeshacerEdicionMasivaInput($out->edicionId));

    expect($rev->revertidos)->toBe(3)
        ->and($rev->conflictos)->toBe(0)
        ->and(valorDe($repo, $ids[0], 'biome'))->toBe('bosque humedo');
});

test('una fila que cambió después queda en conflicto y conserva su valor nuevo', function (): void {
    [$fijar, , $deshacer, $repo, , $ids] = sembrarEdicionMasiva(3);
    $masiva = $fijar->handle(new AplicarEdicionMasivaEspecimenesInput(
        especimenIds: $ids, campo: 'biome', valor: 'páramo',
    ));

    // El curador retoca una sola fila después de la edición masiva.
    $fijar->handle(new AplicarEdicionMasivaEspecimenesInput(
        especimenIds: [$ids[1]], campo: 'biome', valor: 'manglar',
    ));

    $rev = $deshacer->handle(new DeshacerEdicionMasivaInput($masiva->edicionId));

    expect($rev->revertidos)->toBe(2)
        ->and($rev->conflictos)->toBe(1)
        // El cambio más reciente se respeta: deshacer no lo destruye.
        ->and(valorDe($repo, $ids[1], 'biome'))->toBe('manglar')
        ->and(valorDe($repo, $ids[0], 'biome'))->toBe('bosque humedo');
});

test('deshacer revierte correctamente cuando el valor previo era nulo', function (): void {
    [$fijar, , $deshacer, $repo, , $ids] = sembrarEdicionMasiva(1);
    // habitat empieza vacío.
    $out = $fijar->handle(new AplicarEdicionMasivaEspecimenesInput(
        especimenIds: $ids, campo: 'habitat', valor: 'sotobosque',
    ));

    $deshacer->handle(new DeshacerEdicionMasivaInput($out->edicionId));

    expect(valorDe($repo, $ids[0], 'habitat'))->toBeNull();
});

test('la misma edición no se puede deshacer dos veces', function (): void {
    [$fijar, , $deshacer, , , $ids] = sembrarEdicionMasiva(1);
    $out = $fijar->handle(new AplicarEdicionMasivaEspecimenesInput(
        especimenIds: $ids, campo: 'biome', valor: 'páramo',
    ));
    $deshacer->handle(new DeshacerEdicionMasivaInput($out->edicionId));
    $deshacer->handle(new DeshacerEdicionMasivaInput($out->edicionId));
})->throws(DomainException::class, 'ya se deshizo');

test('deshacer una edición inexistente falla con un mensaje claro', function (): void {
    [, , $deshacer] = sembrarEdicionMasiva(1);
    $deshacer->handle(new DeshacerEdicionMasivaInput('no-existe'));
})->throws(DomainException::class, 'No se encontró la edición');

test('en ediciones encadenadas solo la última se puede deshacer limpiamente', function (): void {
    [$fijar, , $deshacer, $repo, , $ids] = sembrarEdicionMasiva(1);
    $a = $fijar->handle(new AplicarEdicionMasivaEspecimenesInput(
        especimenIds: $ids, campo: 'biome', valor: 'páramo',
    ));
    $b = $fijar->handle(new AplicarEdicionMasivaEspecimenesInput(
        especimenIds: $ids, campo: 'biome', valor: 'manglar',
    ));

    // Deshacer A sin haber deshecho B da conflicto: el valor ya no es el suyo.
    expect($deshacer->handle(new DeshacerEdicionMasivaInput($a->edicionId))->conflictos)->toBe(1);

    // Deshecha B, el campo vuelve a 'páramo'.
    $deshacer->handle(new DeshacerEdicionMasivaInput($b->edicionId));
    expect(valorDe($repo, $ids[0], 'biome'))->toBe('páramo');
});

// ── Historial ───────────────────────────────────────────────────────────────

test('el historial redacta cada operación en una frase legible', function (): void {
    [$fijar, $reemplazar, , , $bitacora, $ids] = sembrarEdicionMasiva(2);
    $fijar->handle(new AplicarEdicionMasivaEspecimenesInput(
        especimenIds: $ids, campo: 'biome', valor: 'páramo', actorNombre: 'Adrian',
    ));
    $reemplazar->handle(new ReemplazarTextoEnEspecimenesInput(
        especimenIds: $ids, campo: 'biome', buscar: 'páramo', reemplazo: 'Páramo',
    ));

    $items = (new ListarEdicionesMasivasHandler($bitacora))->handle(new ListarEdicionesMasivasInput)->items;

    expect($items)->toHaveCount(2)
        ->and(collect($items)->pluck('resumen')->implode(' || '))
        ->toContain('Se puso «páramo» en «Bioma»')
        ->toContain('se reemplazó «páramo» por «Páramo»');
});
