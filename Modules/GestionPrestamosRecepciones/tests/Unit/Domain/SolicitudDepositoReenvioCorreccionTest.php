<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionEstadoInvalida;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoRechazoDocumental;

function solicitudDeposito(): SolicitudDeposito
{
    return SolicitudDeposito::crear(
        id: SolicitudDepositoId::generate(),
        numero: NumeroSolicitudDeposito::fromSecuencia(1),
        investigadorId: 'inv-1',
        tipoTramite: 'Depósito',
    );
}

test('una solicitud devuelta para corrección se reenvía sin cambiar a borrador', function (): void {
    $solicitud = solicitudDeposito();
    $solicitud->avanzarARevisionCuraduria();
    $solicitud->rechazarDocumentalmente('cur-1', TipoRechazoDocumental::Subsanable, 'Falta el permiso.');

    expect($solicitud->estado())->toBe(EstadoSolicitudDeposito::RequiereCorreccion);

    // Se corrige EN SU SITIO (sigue en "Requiere Corrección") y se reenvía directamente.
    $solicitud->avanzarARevisionCuraduria();

    expect($solicitud->estado())->toBe(EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria);
});

test('la solicitud devuelta conserva el comentario del curador durante la corrección', function (): void {
    $solicitud = solicitudDeposito();
    $solicitud->avanzarARevisionCuraduria();
    $solicitud->rechazarDocumentalmente('cur-1', TipoRechazoDocumental::Subsanable, 'Falta el permiso.');

    expect($solicitud->comentarioCurador())->toBe('Falta el permiso.');
});

test('un rechazo definitivo no puede reenviarse', function (): void {
    $solicitud = solicitudDeposito();
    $solicitud->avanzarARevisionCuraduria();
    $solicitud->rechazarDocumentalmente('cur-1', TipoRechazoDocumental::Definitivo, 'No corresponde.');

    expect($solicitud->estado())->toBe(EstadoSolicitudDeposito::RechazoPermanente);
    expect(fn () => $solicitud->avanzarARevisionCuraduria())
        ->toThrow(TransicionEstadoInvalida::class);
});

test('una solicitud ya en revisión no puede reenviarse de nuevo', function (): void {
    $solicitud = solicitudDeposito();
    $solicitud->avanzarARevisionCuraduria();

    expect(fn () => $solicitud->avanzarARevisionCuraduria())
        ->toThrow(TransicionEstadoInvalida::class);
});
