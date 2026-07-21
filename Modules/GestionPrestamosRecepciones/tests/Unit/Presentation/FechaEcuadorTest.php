<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Presentation\Support\FechaEcuador;

it('convierte una fecha UTC a la hora local de Ecuador', function () {
    $utc = new DateTimeImmutable('2026-07-16 02:00:00', new DateTimeZone('UTC'));

    // Ecuador es UTC−5, así que 02:00 UTC = 21:00 del día anterior.
    expect(FechaEcuador::formatear($utc))->toBe('15/07/2026 21:00');
});

it('respeta el formato indicado', function () {
    $utc = new DateTimeImmutable('2026-07-16 10:30:00', new DateTimeZone('UTC'));

    expect(FechaEcuador::formatear($utc, 'd/m/Y'))->toBe('16/07/2026')
        ->and(FechaEcuador::formatear($utc, 'H:i'))->toBe('05:30');
});

it('devuelve el marcador de vacío cuando la fecha es null', function () {
    expect(FechaEcuador::formatear(null))->toBe('—')
        ->and(FechaEcuador::formatear(null, 'd/m/Y', 'sin fecha'))->toBe('sin fecha');
});
