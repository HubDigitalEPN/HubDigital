<?php

declare(strict_types=1);

use App\Services\NormalizadorEcCatalogo;

test('corrige todas las apariciones literales EC con la excepción del inicio del contenido', function (string $original, string $esperado): void {
    expect(NormalizadorEcCatalogo::texto($original))->toBe($esperado);
})->with([
    ['ECuador', 'Ecuador'], ['EC', 'Ec'], ['dECimalLatitude', 'decimalLatitude'],
    ['creativECommons.org', 'creativecommons.org'], ['ECtatomminae y rECord', 'Ectatomminae y record'],
    ['Colección EC', 'Colección ec'], ['Ecuador y ec', 'Ecuador y ec'],
]);
