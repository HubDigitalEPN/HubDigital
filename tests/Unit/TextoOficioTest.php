<?php

declare(strict_types=1);

use App\Support\TextoOficio;

test('convierte el título en mayúsculas a oración y conserva sus nombres geográficos', function (): void {
    expect(TextoOficio::oracion('MONITOREO AMBIENTAL DE LA LAGUNA LIMPIOPUNGO, PARROQUIA EL CÁRMEN'))
        ->toBe('Monitoreo ambiental de la laguna Limpiopungo, parroquia El Cármen');
});

test('conserva nombres propios ya escritos y normaliza el nombre del titular', function (): void {
    expect(TextoOficio::oracion('Monitoreo ambiental de la laguna Limpiopungo, parroquia El Cármen'))
        ->toBe('Monitoreo ambiental de la laguna Limpiopungo, parroquia El Cármen')
        ->and(TextoOficio::nombre('HERNAN AUGUSTO TROYA PROANO'))->toBe('Hernan Augusto Troya Proano');
});
