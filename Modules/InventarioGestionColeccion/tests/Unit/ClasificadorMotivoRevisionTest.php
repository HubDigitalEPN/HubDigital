<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\ClasificadorMotivoRevision;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClaseProblemaRevision;

function clasificador(): ClasificadorMotivoRevision
{
    return new ClasificadorMotivoRevision;
}

test('un motivo vacío o nulo no produce ninguna clase', function (): void {
    expect(clasificador()->clasificar(null))->toBe([])
        ->and(clasificador()->clasificar(''))->toBe([])
        ->and(clasificador()->clasificar('   '))->toBe([]);
});

test('reconoce cada motivo que escribe el importador', function (string $motivo, ClaseProblemaRevision $esperada): void {
    expect(clasificador()->clasificar($motivo))->toBe([$esperada]);
})->with([
    ["coordenadas no numéricas o fuera de rango ('-0.6258 / -76495')", ClaseProblemaRevision::Coordenadas],
    ["fecha_colecta no parseable ('25 a 27-May-09')", ClaseProblemaRevision::Fecha],
    ['taxonomía sin validar en el depósito: pendiente', ClaseProblemaRevision::Taxonomia],
    ['occurrence_id ausente', ClaseProblemaRevision::OccurrenceId],
    ["individual_count no numérico ('varios')", ClaseProblemaRevision::IndividualCount],
]);

test('una fila con varios avisos devuelve todas sus clases, no solo la primera', function (): void {
    // Caso real del importador: 55 especímenes traen exactamente esta pareja.
    $motivo = "fecha_colecta no parseable ('08-aug-00.'); coordenadas no numéricas o fuera de rango ('-0.413 / -76013')";

    expect(clasificador()->clasificar($motivo))
        ->toBe([ClaseProblemaRevision::Coordenadas, ClaseProblemaRevision::Fecha]);
});

test('un motivo escrito a mano por el curador cae en genérico en vez de perderse', function (): void {
    expect(clasificador()->clasificar('el ejemplar llegó con la etiqueta rota'))
        ->toBe([ClaseProblemaRevision::Generico]);
});

test('la detección no se rompe con mayúsculas ni con tildes', function (): void {
    // mb_strtolower es necesario: strtolower() no baja la Í de 'TAXONOMÍA'.
    expect(clasificador()->clasificar('TAXONOMÍA SIN VALIDAR EN EL DEPÓSITO: X'))
        ->toBe([ClaseProblemaRevision::Taxonomia]);
});

test('los patrones son largos para no capturar notas libres que mencionan fecha o coordenadas', function (): void {
    // Motivo típico de la bandeja de duplicados, redactado por el curador.
    $motivo = 'error de catalogación: la fecha del cuaderno no coincide con la etiqueta';

    expect(clasificador()->clasificar($motivo))->toBe([ClaseProblemaRevision::Generico]);
});

test('desglosar separa los avisos acumulados', function (): void {
    $motivo = "occurrence_id ausente; fecha_colecta no parseable ('nov-16')";

    expect(clasificador()->desglosar($motivo))
        ->toBe(['occurrence_id ausente', "fecha_colecta no parseable ('nov-16')"]);
});

test('desglosar tolera separadores sobrantes y espacios', function (): void {
    // El fragmento vacío que deja el separador repetido se descarta.
    expect(clasificador()->desglosar('uno;  ; dos'))->toBe(['uno', 'dos'])
        ->and(clasificador()->desglosar(null))->toBe([]);
});

test('solo se marcan como corregibles las clases que tienen pantalla donde arreglarlas', function (): void {
    expect(ClaseProblemaRevision::Coordenadas->esCorregible())->toBeTrue()
        ->and(ClaseProblemaRevision::Fecha->esCorregible())->toBeTrue()
        ->and(ClaseProblemaRevision::Taxonomia->esCorregible())->toBeTrue()
        // No están en ActualizarEspecimenInput: no hay dónde corregirlos.
        ->and(ClaseProblemaRevision::OccurrenceId->esCorregible())->toBeFalse()
        ->and(ClaseProblemaRevision::IndividualCount->esCorregible())->toBeFalse()
        ->and(ClaseProblemaRevision::Generico->esCorregible())->toBeFalse();
});
