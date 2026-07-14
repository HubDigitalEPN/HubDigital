<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Modules\CatalogoPublico\Application\Ports\DatosEspecimenProveedor;
use Modules\CatalogoPublico\Domain\Entities\EspecimenDivulgable;
use Modules\CatalogoPublico\Domain\Services\RecuperadorContextoEspecimenes;
use Modules\CatalogoPublico\Domain\ValueObjects\ConfiguracionVisibilidad;
use Modules\CatalogoPublico\Domain\ValueObjects\EntidadesExtraidas;
use Modules\CatalogoPublico\Tests\Support\FakeProveedorEspecimenesPort;
use Modules\CatalogoPublico\Tests\Support\InMemoryEspecimenDivulgableRepository;

/**
 * @param  array<string, bool>|null  $overridesVisibilidad
 * @return array{FakeProveedorEspecimenesPort, InMemoryEspecimenDivulgableRepository}
 */
function sembrarEspecimen(
    FakeProveedorEspecimenesPort $proveedor,
    InMemoryEspecimenDivulgableRepository $repo,
    string $occurrenceID,
    string $scientificName,
    ?string $genus,
    ?string $family = null,
    ?string $localityName = null,
    ?string $country = 'Ecuador',
    ?string $samplingProtocol = null,
    ?string $stateProvince = null,
    bool $sincronizarDivulgable = true,
    ?array $overridesVisibilidad = null,
): array {
    $especimenId = (string) Str::uuid();

    $datos = new DatosEspecimenProveedor(
        especimenId: $especimenId,
        occurrenceId: $occurrenceID,
        scientificName: $scientificName,
        individualCount: 1,
        typeStatus: null,
        typeNotes: null,
        specimenNotes: null,
        samplingProtocol: $samplingProtocol,
        recordedBy: null,
        occurrenceStatus: 'present',
        family: $family,
        genus: $genus,
        country: $country,
        localityName: $localityName,
        decimalLatitude: null,
        decimalLongitude: null,
        stateProvince: $stateProvince,
    );

    $proveedor->agregar($datos);
    $repo->registrarOccurrence($occurrenceID, $especimenId);

    if ($sincronizarDivulgable) {
        $flags = ConfiguracionVisibilidad::todosHabilitados()->toArray();
        if ($overridesVisibilidad !== null) {
            $flags = array_merge($flags, $overridesVisibilidad);
        }

        $repo->guardar(EspecimenDivulgable::sincronizar(
            id: $repo->nextIdentity(),
            especimenId: $especimenId,
            configuracion: ConfiguracionVisibilidad::desde($flags),
        ));
    }

    return [$proveedor, $repo];
}

function nuevoRecuperador(): array
{
    $proveedor = new FakeProveedorEspecimenesPort;
    $repo = new InMemoryEspecimenDivulgableRepository;
    $servicio = new RecuperadorContextoEspecimenes($proveedor, $repo);

    return [$servicio, $proveedor, $repo];
}

test('entidades vacías no vuelcan el catálogo entero', function (): void {
    [$servicio, $proveedor, $repo] = nuevoRecuperador();
    sembrarEspecimen($proveedor, $repo, 'EPN-1', 'Atta cephalotes', 'Atta', 'Formicidae');
    sembrarEspecimen($proveedor, $repo, 'EPN-2', 'Bombus atratus', 'Bombus', 'Apidae');

    expect($servicio->recuperar(EntidadesExtraidas::vacio()))->toBe([]);
});

test('recupera espécimen por occurrenceID directo', function (): void {
    [$servicio, $proveedor, $repo] = nuevoRecuperador();
    sembrarEspecimen($proveedor, $repo, 'EPN-002', 'Bombus atratus', 'Bombus', 'Apidae', samplingProtocol: 'Red entomológica');

    $contexto = $servicio->recuperar(EntidadesExtraidas::desde(occurrenceID: 'EPN-002'));

    expect($contexto)->toHaveCount(1)
        ->and($contexto[0]->occurrenceID)->toBe('EPN-002')
        ->and($contexto[0]->valor('family'))->toBe('Apidae')
        ->and($contexto[0]->valor('samplingProtocol'))->toBe('Red entomológica');
});

test('recupera especímenes por nombre científico exacto', function (): void {
    [$servicio, $proveedor, $repo] = nuevoRecuperador();
    sembrarEspecimen($proveedor, $repo, 'EPN-005', 'Morpho menelaus', 'Morpho', 'Nymphalidae', localityName: 'Mindo');
    sembrarEspecimen($proveedor, $repo, 'EPN-006', 'Atta cephalotes', 'Atta', 'Formicidae');

    $contexto = $servicio->recuperar(EntidadesExtraidas::desde(especie: 'Morpho menelaus'));

    expect($contexto)->toHaveCount(1)
        ->and($contexto[0]->occurrenceID)->toBe('EPN-005')
        ->and($contexto[0]->valor('localityName'))->toBe('Mindo');
});

test('recupera especímenes por género (case-insensitive, filtro en memoria)', function (): void {
    [$servicio, $proveedor, $repo] = nuevoRecuperador();
    sembrarEspecimen($proveedor, $repo, 'EPN-0012', 'Atta cephalotes', 'Atta', 'Formicidae', localityName: 'Reserva Yasuní');
    sembrarEspecimen($proveedor, $repo, 'EPN-013', 'Atta laevigata', 'Atta', 'Formicidae', localityName: 'Papallacta');
    sembrarEspecimen($proveedor, $repo, 'EPN-002', 'Bombus atratus', 'Bombus', 'Apidae');

    $contexto = $servicio->recuperar(EntidadesExtraidas::desde(genero: 'atta'));

    $ids = array_map(fn ($e) => $e->occurrenceID, $contexto);
    sort($ids);
    expect($ids)->toBe(['EPN-0012', 'EPN-013']);
});

test('recupera por localidad usando substring case-insensitive', function (): void {
    [$servicio, $proveedor, $repo] = nuevoRecuperador();
    sembrarEspecimen($proveedor, $repo, 'EPN-005', 'Morpho menelaus', 'Morpho', 'Nymphalidae', localityName: 'Mindo Cloud Forest');
    sembrarEspecimen($proveedor, $repo, 'EPN-006', 'Atta cephalotes', 'Atta', 'Formicidae', localityName: 'Reserva Yasuní');

    $contexto = $servicio->recuperar(EntidadesExtraidas::desde(localidad: 'mindo'));

    expect($contexto)->toHaveCount(1)
        ->and($contexto[0]->occurrenceID)->toBe('EPN-005');
});

test('no incluye especímenes no sincronizados para divulgación', function (): void {
    [$servicio, $proveedor, $repo] = nuevoRecuperador();
    sembrarEspecimen($proveedor, $repo, 'EPN-0012', 'Atta cephalotes', 'Atta', 'Formicidae');
    sembrarEspecimen($proveedor, $repo, 'EPN-013', 'Atta laevigata', 'Atta', 'Formicidae', sincronizarDivulgable: false);

    $contexto = $servicio->recuperar(EntidadesExtraidas::desde(genero: 'Atta'));

    expect($contexto)->toHaveCount(1)
        ->and($contexto[0]->occurrenceID)->toBe('EPN-0012');
});

test('excluye espécimen cuyo occurrenceID no es visible', function (): void {
    [$servicio, $proveedor, $repo] = nuevoRecuperador();
    sembrarEspecimen(
        $proveedor,
        $repo,
        occurrenceID: 'EPN-CONFIDENCIAL',
        scientificName: 'Atta reservada',
        genus: 'Atta',
        family: 'Formicidae',
        overridesVisibilidad: ['occurrenceIDVisible' => false],
    );
    sembrarEspecimen($proveedor, $repo, 'EPN-VISIBLE', 'Atta cephalotes', 'Atta', 'Formicidae');

    $contexto = $servicio->recuperar(EntidadesExtraidas::desde(genero: 'Atta'));

    expect($contexto)->toHaveCount(1)
        ->and($contexto[0]->occurrenceID)->toBe('EPN-VISIBLE');
});

test('filtra los campos no visibles antes de armar el contexto', function (): void {
    [$servicio, $proveedor, $repo] = nuevoRecuperador();
    sembrarEspecimen(
        $proveedor,
        $repo,
        occurrenceID: 'EPN-0012',
        scientificName: 'Atta cephalotes',
        genus: 'Atta',
        family: 'Formicidae',
        localityName: 'Reserva Yasuní',
        samplingProtocol: 'Trampa Winkler',
        overridesVisibilidad: [
            'localityNameVisible' => false,
            'samplingProtocolVisible' => false,
        ],
    );

    $contexto = $servicio->recuperar(EntidadesExtraidas::desde(genero: 'Atta'));

    expect($contexto)->toHaveCount(1)
        ->and($contexto[0]->tieneCampo('family'))->toBeTrue()
        ->and($contexto[0]->tieneCampo('genus'))->toBeTrue()
        ->and($contexto[0]->tieneCampo('localityName'))->toBeFalse()
        ->and($contexto[0]->tieneCampo('samplingProtocol'))->toBeFalse();
});

test('combina filtros de localidad y country', function (): void {
    [$servicio, $proveedor, $repo] = nuevoRecuperador();
    sembrarEspecimen($proveedor, $repo, 'EPN-EC', 'Atta cephalotes', 'Atta', 'Formicidae', localityName: 'Mindo', country: 'Ecuador');
    sembrarEspecimen($proveedor, $repo, 'EPN-PE', 'Atta cephalotes', 'Atta', 'Formicidae', localityName: 'Mindo', country: 'Perú');

    $contexto = $servicio->recuperar(EntidadesExtraidas::desde(
        localidad: 'Mindo',
        country: 'Ecuador',
    ));

    expect($contexto)->toHaveCount(1)
        ->and($contexto[0]->occurrenceID)->toBe('EPN-EC');
});

test('el contexto incluye stateProvince cuando su visibilidad está habilitada', function (): void {
    [$servicio, $proveedor, $repo] = nuevoRecuperador();
    sembrarEspecimen(
        $proveedor,
        $repo,
        'EPN-DS-1',
        'Dichotomius satanas',
        'Dichotomius',
        'Scarabaeidae',
        localityName: 'Reserva Yasuní',
        country: 'Ecuador',
        stateProvince: 'Orellana',
    );

    $contexto = $servicio->recuperar(EntidadesExtraidas::desde(especie: 'Dichotomius satanas'));

    expect($contexto)->toHaveCount(1)
        ->and($contexto[0]->tieneCampo('stateProvince'))->toBeTrue()
        ->and($contexto[0]->valor('stateProvince'))->toBe('Orellana');
});

test('stateProvince desaparece del contexto cuando su visibilidad se apaga', function (): void {
    [$servicio, $proveedor, $repo] = nuevoRecuperador();
    sembrarEspecimen(
        $proveedor,
        $repo,
        'EPN-DS-2',
        'Dichotomius satanas',
        'Dichotomius',
        'Scarabaeidae',
        stateProvince: 'Napo',
        overridesVisibilidad: ['stateProvinceVisible' => false],
    );

    $contexto = $servicio->recuperar(EntidadesExtraidas::desde(especie: 'Dichotomius satanas'));

    expect($contexto)->toHaveCount(1)
        ->and($contexto[0]->tieneCampo('stateProvince'))->toBeFalse();
});
