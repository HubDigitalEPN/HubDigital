<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\Entities\VerificacionEspecimenes;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ObservacionEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoVerificacion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoVerificacion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\VerificacionEspecimenesId;

function nuevaVerificacion(
    ResultadoVerificacion $resultado,
    array $observaciones = [],
    ?string $observacionGeneral = null,
    TipoVerificacion $tipo = TipoVerificacion::Devolucion,
): VerificacionEspecimenes {
    return VerificacionEspecimenes::registrar(
        id: VerificacionEspecimenesId::generate(),
        prestamoId: PrestamoId::generate(),
        tipo: $tipo,
        resultado: $resultado,
        observaciones: $observaciones,
        observacionGeneral: $observacionGeneral,
    );
}

test('registra sin novedades sin observaciones', function (): void {
    $verificacion = nuevaVerificacion(ResultadoVerificacion::SinNovedades);

    expect($verificacion->resultado())->toBe(ResultadoVerificacion::SinNovedades);
    expect($verificacion->observaciones())->toBe([]);
    expect($verificacion->observacionGeneral())->toBeNull();
});

test('registra con novedades por espécimen', function (): void {
    $verificacion = nuevaVerificacion(
        ResultadoVerificacion::ConNovedades,
        observaciones: [new ObservacionEspecimen('item-1', 'pata rota')],
    );

    expect($verificacion->observaciones())->toHaveCount(1);
});

test('registra con novedades solo con nota general', function (): void {
    $verificacion = nuevaVerificacion(
        ResultadoVerificacion::ConNovedades,
        observacionGeneral: 'La caja llegó abierta',
    );

    expect($verificacion->observaciones())->toBe([]);
    expect($verificacion->observacionGeneral())->toBe('La caja llegó abierta');
});

test('con novedades sin ninguna novedad lanza excepción', function (): void {
    expect(fn () => nuevaVerificacion(ResultadoVerificacion::ConNovedades))
        ->toThrow(InvalidArgumentException::class);
});

test('normaliza la nota general vacía a null', function (): void {
    $verificacion = nuevaVerificacion(
        ResultadoVerificacion::ConNovedades,
        observaciones: [new ObservacionEspecimen('item-1', 'húmedo')],
        observacionGeneral: '   ',
    );

    expect($verificacion->observacionGeneral())->toBeNull();
});

test('conserva el tipo de verificación', function (): void {
    expect(nuevaVerificacion(ResultadoVerificacion::SinNovedades, tipo: TipoVerificacion::Recepcion)->tipo())
        ->toBe(TipoVerificacion::Recepcion);
    expect(nuevaVerificacion(ResultadoVerificacion::SinNovedades, tipo: TipoVerificacion::Devolucion)->tipo())
        ->toBe(TipoVerificacion::Devolucion);
});
