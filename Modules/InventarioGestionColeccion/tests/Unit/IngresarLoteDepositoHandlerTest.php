<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IngresarLoteDeposito\IngresarLoteDepositoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IngresarLoteDeposito\IngresarLoteDepositoInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverEntidadDepositante\ResolverEntidadDepositanteHandler;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCustodia;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoRevision;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FilaCatalogoMapper;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEntidadDepositanteRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\ResolverTaxonomiaDwCEnMemoria;

/** Fila Darwin Core como la guarda el módulo de depósitos en `datos_dwc` (camelCase). */
function filaDwC(array $sobrescribir = []): array
{
    return array_merge([
        'scientificName' => 'Schistocerca',
        'genus' => 'Schistocerca',
        'taxonRank' => 'genus',
        'country' => 'Ecuador',
        'stateProvince' => 'Loja',
        'localityName' => 'Reserva Natural El Madrigal',
        'eventDate' => '28-Jan-2023',
        'recordedBy' => 'Padilla, D.',
        'individualCount' => '4',
        'decimalLatitude' => '-4.0312',
        'decimalLongitude' => '-79.2056',
        'habitat' => 'dry shrubland',
    ], $sobrescribir);
}

function ingestaDeposito(InMemoryEspecimenRepository $repo): IngresarLoteDepositoHandler
{
    // La entidad depositante se resuelve contra su propio repositorio; en memoria, para
    // que estas pruebas no den de alta instituciones en ningún sitio.
    return new IngresarLoteDepositoHandler(
        $repo,
        new FilaCatalogoMapper,
        new ResolverEntidadDepositanteHandler(new InMemoryEntidadDepositanteRepository),
        new ResolverTaxonomiaDwCEnMemoria,
    );
}

function entradaDeposito(array $filas, string $custodia = 'Temporal'): IngresarLoteDepositoInput
{
    return new IngresarLoteDepositoInput(
        numeroSolicitud: 'MEPN-INV-DEP-00002',
        codigoQrLote: 'LOTE-DEP00002',
        estadoCustodia: $custodia,
        filas: $filas,
    );
}

test('ingresa a la coleccion un especimen por cada fila de la matriz', function (): void {
    $repo = new InMemoryEspecimenRepository;

    $salida = ingestaDeposito($repo)->handle(entradaDeposito([
        ['indice' => 1, 'datosDwC' => filaDwC(), 'estadoRegistro' => 'Validado Técnicamente'],
        ['indice' => 2, 'datosDwC' => filaDwC(['scientificName' => 'Apis mellifera']), 'estadoRegistro' => 'Validado Técnicamente'],
        ['indice' => 3, 'datosDwC' => filaDwC(['scientificName' => 'Morpho helenor']), 'estadoRegistro' => 'Validado Técnicamente'],
    ]));

    expect($salida->especimenesCreados)->toBe(3)
        ->and($salida->omitidosPorDuplicado)->toBe(0)
        ->and($repo->buscarTodos())->toHaveCount(3);
});

test('el codigo de catalogo se deriva del numero de solicitud y la fila', function (): void {
    $repo = new InMemoryEspecimenRepository;

    $salida = ingestaDeposito($repo)->handle(entradaDeposito([
        ['indice' => 1, 'datosDwC' => filaDwC(), 'estadoRegistro' => 'Validado Técnicamente'],
        ['indice' => 12, 'datosDwC' => filaDwC(), 'estadoRegistro' => 'Validado Técnicamente'],
    ]));

    expect($salida->codigosCreados)->toBe(['MEPN-INV-DEP-00002-0001', 'MEPN-INV-DEP-00002-0012']);
});

test('los especimenes entran con el regimen de tenencia del tramite', function (string $custodia): void {
    $repo = new InMemoryEspecimenRepository;

    ingestaDeposito($repo)->handle(entradaDeposito(
        [['indice' => 1, 'datosDwC' => filaDwC(), 'estadoRegistro' => 'Validado Técnicamente']],
        $custodia,
    ));

    expect($repo->buscarTodos()[0]->estadoCustodia())->toBe(EstadoCustodia::from($custodia));
})->with(['Temporal', 'Permanente', 'Cuarentena']);

test('la taxonomia entra como verbatim porque el deposito admite especies no catalogadas', function (): void {
    $repo = new InMemoryEspecimenRepository;

    ingestaDeposito($repo)->handle(entradaDeposito([
        ['indice' => 1, 'datosDwC' => filaDwC(['scientificName' => 'Morpho sp. nov.']), 'estadoRegistro' => 'Validación Manual por Curaduría'],
    ]));

    $especimen = $repo->buscarTodos()[0];

    expect($especimen->taxonVerbatim())->toBe('Morpho sp. nov.')
        ->and($especimen->taxonId())->toBeNull();
});

test('las filas sin validar taxonomicamente quedan marcadas para revision del curador', function (): void {
    $repo = new InMemoryEspecimenRepository;

    $salida = ingestaDeposito($repo)->handle(entradaDeposito([
        ['indice' => 1, 'datosDwC' => filaDwC(), 'estadoRegistro' => 'Validado Técnicamente'],
        ['indice' => 2, 'datosDwC' => filaDwC(), 'estadoRegistro' => 'Pendiente'],
        ['indice' => 3, 'datosDwC' => filaDwC(), 'estadoRegistro' => 'Validación Manual por Curaduría', 'motivoJustificacion' => 'Es una Especie Nueva'],
    ]));

    expect($salida->marcadosParaRevision)->toBe(2);

    $porCodigo = [];
    foreach ($repo->buscarTodos() as $e) {
        $porCodigo[$e->codigoCatalogo()] = $e;
    }

    expect($porCodigo['MEPN-INV-DEP-00002-0002']->estadoRevision())->toBe(EstadoRevision::Pendiente)
        ->and($porCodigo['MEPN-INV-DEP-00002-0003']->motivoRevision())->toContain('Es una Especie Nueva');
});

test('reejecutar la ingesta no duplica especimenes ya ingresados', function (): void {
    $repo = new InMemoryEspecimenRepository;
    $filas = [
        ['indice' => 1, 'datosDwC' => filaDwC(), 'estadoRegistro' => 'Validado Técnicamente'],
        ['indice' => 2, 'datosDwC' => filaDwC(), 'estadoRegistro' => 'Validado Técnicamente'],
    ];

    ingestaDeposito($repo)->handle(entradaDeposito($filas));
    $segunda = ingestaDeposito($repo)->handle(entradaDeposito($filas));

    expect($segunda->especimenesCreados)->toBe(0)
        ->and($segunda->omitidosPorDuplicado)->toBe(2)
        ->and($repo->buscarTodos())->toHaveCount(2);
});

test('una reejecucion parcial solo ingresa las filas que faltaban', function (): void {
    $repo = new InMemoryEspecimenRepository;

    ingestaDeposito($repo)->handle(entradaDeposito([
        ['indice' => 1, 'datosDwC' => filaDwC(), 'estadoRegistro' => 'Validado Técnicamente'],
    ]));

    $segunda = ingestaDeposito($repo)->handle(entradaDeposito([
        ['indice' => 1, 'datosDwC' => filaDwC(), 'estadoRegistro' => 'Validado Técnicamente'],
        ['indice' => 2, 'datosDwC' => filaDwC(), 'estadoRegistro' => 'Validado Técnicamente'],
    ]));

    expect($segunda->especimenesCreados)->toBe(1)
        ->and($segunda->omitidosPorDuplicado)->toBe(1)
        ->and($repo->buscarTodos())->toHaveCount(2);
});

test('la ausencia de occurrenceID no manda el especimen a revision', function (): void {
    // El occurrenceID lo asigna la colección al publicar, no el depositante: si contara
    // como anomalía, todo lote ingresado saturaría la cola de revisión del curador.
    $repo = new InMemoryEspecimenRepository;

    $salida = ingestaDeposito($repo)->handle(entradaDeposito([
        ['indice' => 1, 'datosDwC' => filaDwC(), 'estadoRegistro' => 'Validado Técnicamente'],
    ]));

    expect($salida->marcadosParaRevision)->toBe(0)
        ->and($repo->buscarTodos()[0]->motivoRevision())->toBeNull();
});

test('los problemas reales de calidad de dato si mandan el especimen a revision', function (): void {
    $repo = new InMemoryEspecimenRepository;

    $salida = ingestaDeposito($repo)->handle(entradaDeposito([
        ['indice' => 1, 'datosDwC' => filaDwC(['decimalLatitude' => 'no-es-una-coordenada']), 'estadoRegistro' => 'Validado Técnicamente'],
    ]));

    expect($salida->marcadosParaRevision)->toBe(1)
        ->and($repo->buscarTodos()[0]->motivoRevision())->toContain('coordenadas');
});

test('no se ocupa fila_origen_excel, que pertenece al importador del catalogo', function (): void {
    // Esa columna tiene un índice único en toda la tabla y el catálogo importado ya
    // ocupa el rango 1..48856: escribir ahí el número de fila del depósito reventaría
    // con una violación de unicidad contra el material heredado.
    $repo = new InMemoryEspecimenRepository;

    ingestaDeposito($repo)->handle(entradaDeposito([
        ['indice' => 1, 'datosDwC' => filaDwC(), 'estadoRegistro' => 'Validado Técnicamente'],
    ]));

    expect($repo->buscarTodos()[0]->filaOrigenExcel())->toBeNull();
});

test('el acta de recepcion queda como trazabilidad en cada especimen', function (): void {
    $repo = new InMemoryEspecimenRepository;

    ingestaDeposito($repo)->handle(entradaDeposito([
        ['indice' => 1, 'datosDwC' => filaDwC(), 'estadoRegistro' => 'Validado Técnicamente'],
    ]));

    expect($repo->buscarTodos()[0]->actaRecepcion())->toBe('LOTE-DEP00002');
});
