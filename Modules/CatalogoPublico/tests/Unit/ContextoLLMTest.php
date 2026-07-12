<?php

declare(strict_types=1);

use Modules\CatalogoPublico\Domain\ValueObjects\ContextoLLM;
use Modules\CatalogoPublico\Domain\ValueObjects\DatosEspecimenParaContexto;
use Modules\CatalogoPublico\Domain\ValueObjects\EstadisticaTaxon;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;

function especimenFake(string $occurrenceID, array $campos = []): DatosEspecimenParaContexto
{
    return DatosEspecimenParaContexto::crear(
        occurrenceID: $occurrenceID,
        camposVisibles: $campos + ['occurrenceID' => $occurrenceID],
    );
}

test('conEspecimenes() rechaza pregunta vacía', function (): void {
    ContextoLLM::conEspecimenes(pregunta: '  ', especimenes: []);
})->throws(InvalidArgumentException::class);

test('estaVacio() refleja la ausencia de especímenes en modo Lookup', function (): void {
    expect(ContextoLLM::conEspecimenes(pregunta: 'x', especimenes: [])->estaVacio())->toBeTrue()
        ->and(ContextoLLM::conEspecimenes(pregunta: 'x', especimenes: [especimenFake('EPN-1')])->estaVacio())->toBeFalse();
});

test('incluyeOccurrenceID() localiza al espécimen por identificador', function (): void {
    $contexto = ContextoLLM::conEspecimenes(
        pregunta: '¿Qué hay del EPN-0012?',
        especimenes: [especimenFake('EPN-0012'), especimenFake('EPN-005')],
    );

    expect($contexto->incluyeOccurrenceID('EPN-0012'))->toBeTrue()
        ->and($contexto->incluyeOccurrenceID('EPN-999'))->toBeFalse()
        ->and($contexto->occurrenceIDs())->toBe(['EPN-0012', 'EPN-005']);
});

test('especimenPor() devuelve el DTO tabular del espécimen o null', function (): void {
    $contexto = ContextoLLM::conEspecimenes(
        pregunta: 'x',
        especimenes: [especimenFake('EPN-1', ['family' => 'Formicidae'])],
    );

    expect($contexto->especimenPor('EPN-1')?->valor('family'))->toBe('Formicidae')
        ->and($contexto->especimenPor('EPN-999'))->toBeNull();
});

test('conRanking() marca el contexto como Ranking y expone estadísticas', function (): void {
    $rango = RangoTaxonomico::Family;
    $stats = [
        EstadisticaTaxon::crear($rango, 'Formicidae', 42),
        EstadisticaTaxon::crear($rango, 'Apidae', 15),
    ];

    $contexto = ContextoLLM::conRanking(
        pregunta: '¿Familia con más registros?',
        rango: $rango,
        estadisticas: $stats,
        totalUnicosEnRango: 12,
    );

    expect($contexto->esRanking())->toBeTrue()
        ->and($contexto->estaVacio())->toBeFalse()
        ->and($contexto->estadisticas)->toHaveCount(2)
        ->and($contexto->totalUnicosEnRango)->toBe(12)
        ->and($contexto->rangoAgregacion)->toBe($rango);
});

test('Ranking sin estadísticas está vacío', function (): void {
    $contexto = ContextoLLM::conRanking(
        pregunta: 'x',
        rango: RangoTaxonomico::Genus,
        estadisticas: [],
        totalUnicosEnRango: 0,
    );

    expect($contexto->estaVacio())->toBeTrue();
});
