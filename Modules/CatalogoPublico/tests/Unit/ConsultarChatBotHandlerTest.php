<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Modules\CatalogoPublico\Application\Ports\DatosEspecimenProveedor;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotInput;
use Modules\CatalogoPublico\Domain\Entities\EspecimenDivulgable;
use Modules\CatalogoPublico\Domain\Services\CalculadorEstadisticasTaxonomicas;
use Modules\CatalogoPublico\Domain\Services\RecuperadorContextoEspecimenes;
use Modules\CatalogoPublico\Domain\ValueObjects\ChatBotMensajes;
use Modules\CatalogoPublico\Domain\ValueObjects\ConfiguracionVisibilidad;
use Modules\CatalogoPublico\Domain\ValueObjects\EntidadesExtraidas;
use Modules\CatalogoPublico\Domain\ValueObjects\IntencionConsulta;
use Modules\CatalogoPublico\Domain\ValueObjects\JerarquiaTaxonomica;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;
use Modules\CatalogoPublico\Tests\Support\FakeClasificadorIntencion;
use Modules\CatalogoPublico\Tests\Support\FakeGeneradorRespuestaChatBot;
use Modules\CatalogoPublico\Tests\Support\FakeProveedorEspecimenesPort;
use Modules\CatalogoPublico\Tests\Support\InMemoryEspecimenDivulgableRepository;
use Modules\CatalogoPublico\Tests\Support\InMemoryProveedorEspecimenesParaArbol;

function armarHandler(): array
{
    $proveedor = new FakeProveedorEspecimenesPort;
    $repo = new InMemoryEspecimenDivulgableRepository;
    $clasificador = new FakeClasificadorIntencion;
    $generador = new FakeGeneradorRespuestaChatBot;
    $proveedorArbol = new InMemoryProveedorEspecimenesParaArbol($repo);

    $handler = new ConsultarChatBotHandler(
        clasificador: $clasificador,
        recuperador: new RecuperadorContextoEspecimenes($proveedor, $repo),
        calculador: new CalculadorEstadisticasTaxonomicas($proveedorArbol),
        generador: $generador,
    );

    return [$handler, $proveedor, $repo, $clasificador, $generador, $proveedorArbol];
}

function agregarAlArbol(
    InMemoryProveedorEspecimenesParaArbol $arbol,
    InMemoryEspecimenDivulgableRepository $repo,
    string $occurrenceID,
    string $family,
    string $genus,
    string $species,
): void {
    $especimenId = (string) Str::uuid();
    $repo->registrarOccurrence($occurrenceID, $especimenId);
    $repo->guardar(EspecimenDivulgable::sincronizar(
        id: $repo->nextIdentity(),
        especimenId: $especimenId,
        configuracion: ConfiguracionVisibilidad::todosHabilitados(),
    ));

    $arbol->agregar(
        occurrenceID: $occurrenceID,
        jerarquia: JerarquiaTaxonomica::desde(
            phylum: 'Arthropoda',
            class: 'Insecta',
            order: 'Hymenoptera',
            family: $family,
            genus: $genus,
            specificEpithet: explode(' ', $species)[1] ?? 'sp',
            scientificName: $species,
        ),
    );
}

function agregarAttaVisible(FakeProveedorEspecimenesPort $proveedor, InMemoryEspecimenDivulgableRepository $repo, string $occurrenceID): void
{
    $especimenId = (string) Str::uuid();

    $proveedor->agregar(new DatosEspecimenProveedor(
        especimenId: $especimenId,
        occurrenceId: $occurrenceID,
        scientificName: 'Atta cephalotes',
        individualCount: 1,
        typeStatus: null,
        typeNotes: null,
        specimenNotes: null,
        samplingProtocol: 'Trampa Winkler',
        recordedBy: null,
        occurrenceStatus: 'present',
        family: 'Formicidae',
        genus: 'Atta',
        country: 'Ecuador',
        localityName: 'Reserva Yasuní',
        decimalLatitude: null,
        decimalLongitude: null,
    ));

    $repo->registrarOccurrence($occurrenceID, $especimenId);
    $repo->guardar(EspecimenDivulgable::sincronizar(
        id: $repo->nextIdentity(),
        especimenId: $especimenId,
        configuracion: ConfiguracionVisibilidad::todosHabilitados(),
    ));
}

test('rechaza preguntas vacías', function (): void {
    [$handler] = armarHandler();

    $handler->handle(new ConsultarChatBotInput('   '));
})->throws(InvalidArgumentException::class);

test('cuando la intención es fuera de dominio no invoca ni al recuperador ni al generador', function (): void {
    [$handler, $proveedor, $repo, $clasificador, $generador] = armarHandler();
    agregarAttaVisible($proveedor, $repo, 'EPN-0012');
    $clasificador->registrar('¿Receta de pan?', IntencionConsulta::fueraDelDominio());

    $output = $handler->handle(new ConsultarChatBotInput('¿Receta de pan?'));

    expect($output->dentroDeDominio)->toBeFalse()
        ->and($output->respuesta)->toBe(ChatBotMensajes::FUERA_DE_DOMINIO)
        ->and($output->especimenesReferenciados)->toBe([])
        ->and($output->contexto)->toBeNull()
        ->and($generador->llamadas)->toBe(0);
});

test('lookup vacío con entidades reconocidas delega al LLM para contexto general', function (): void {
    [$handler, , , $clasificador, $generador] = armarHandler();
    $clasificador->registrar(
        'qué me puedes decir de Fulanoides raroensis',
        IntencionConsulta::dentroDelDominio(EntidadesExtraidas::desde(especie: 'Fulanoides raroensis')),
    );

    $output = $handler->handle(new ConsultarChatBotInput('qué me puedes decir de Fulanoides raroensis'));

    expect($output->dentroDeDominio)->toBeTrue()
        ->and($output->respuesta)->toBe(FakeGeneradorRespuestaChatBot::RESPUESTA_CANONICA)
        ->and($generador->llamadas)->toBe(1)
        ->and($output->especimenesReferenciados)->toBe([]);
});

test('lookup vacío con entidades vacías corta como SIN_RESULTADOS sin llamar al LLM', function (): void {
    [$handler, , , $clasificador, $generador] = armarHandler();
    $clasificador->registrar(
        'pregunta muy vaga',
        IntencionConsulta::dentroDelDominio(EntidadesExtraidas::vacio()),
    );

    $output = $handler->handle(new ConsultarChatBotInput('pregunta muy vaga'));

    expect($output->dentroDeDominio)->toBeTrue()
        ->and($output->respuesta)->toBe(ChatBotMensajes::SIN_RESULTADOS)
        ->and($generador->llamadas)->toBe(0);
});

test('con especímenes recuperados delega al generador y expone los occurrenceIDs referenciados', function (): void {
    [$handler, $proveedor, $repo, $clasificador, $generador] = armarHandler();
    agregarAttaVisible($proveedor, $repo, 'EPN-0012');
    agregarAttaVisible($proveedor, $repo, 'EPN-0013');

    $clasificador->registrar(
        'especímenes del género Atta',
        IntencionConsulta::dentroDelDominio(EntidadesExtraidas::desde(genero: 'Atta')),
    );

    $output = $handler->handle(new ConsultarChatBotInput('especímenes del género Atta'));

    expect($output->dentroDeDominio)->toBeTrue()
        ->and($output->respuesta)->toBe(FakeGeneradorRespuestaChatBot::RESPUESTA_CANONICA)
        ->and($generador->llamadas)->toBe(1)
        ->and($generador->ultimoContexto)->not->toBeNull();

    $ids = $output->especimenesReferenciados;
    sort($ids);
    expect($ids)->toBe(['EPN-0012', 'EPN-0013']);
});

test('modo Ranking calcula top por rango y no llama al recuperador de especímenes', function (): void {
    [$handler, , $repo, $clasificador, $generador, $arbol] = armarHandler();
    agregarAlArbol($arbol, $repo, 'EPN-1', 'Formicidae', 'Atta', 'Atta cephalotes');
    agregarAlArbol($arbol, $repo, 'EPN-2', 'Formicidae', 'Atta', 'Atta laevigata');
    agregarAlArbol($arbol, $repo, 'EPN-3', 'Formicidae', 'Solenopsis', 'Solenopsis invicta');
    agregarAlArbol($arbol, $repo, 'EPN-4', 'Apidae', 'Bombus', 'Bombus atratus');
    agregarAlArbol($arbol, $repo, 'EPN-5', 'Nymphalidae', 'Morpho', 'Morpho menelaus');

    $clasificador->registrar(
        '¿Cuál es la familia con más registros?',
        IntencionConsulta::ranking(RangoTaxonomico::Family, 3),
    );

    $output = $handler->handle(new ConsultarChatBotInput('¿Cuál es la familia con más registros?'));

    expect($output->dentroDeDominio)->toBeTrue()
        ->and($output->respuesta)->toBe(FakeGeneradorRespuestaChatBot::RESPUESTA_CANONICA)
        ->and($generador->llamadas)->toBe(1)
        ->and($generador->ultimoContexto?->esRanking())->toBeTrue()
        ->and($generador->ultimoContexto?->rangoAgregacion)->toBe(RangoTaxonomico::Family)
        ->and($generador->ultimoContexto?->totalUnicosEnRango)->toBe(3);

    $stats = $generador->ultimoContexto->estadisticas;
    expect($stats)->toHaveCount(3)
        ->and($stats[0]->nombre)->toBe('Formicidae')
        ->and($stats[0]->cantidadRegistros)->toBe(3);
});

test('Ranking sin especímenes divulgables devuelve SIN_RESULTADOS sin llamar al LLM', function (): void {
    [$handler, , , $clasificador, $generador] = armarHandler();
    $clasificador->registrar(
        '¿top familias?',
        IntencionConsulta::ranking(RangoTaxonomico::Family, 5),
    );

    $output = $handler->handle(new ConsultarChatBotInput('¿top familias?'));

    expect($output->respuesta)->toBe(ChatBotMensajes::SIN_RESULTADOS)
        ->and($generador->llamadas)->toBe(0);
});
