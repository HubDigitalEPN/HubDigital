<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionEstadoInvalida;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoRechazoDocumental;

function solicitudNueva(): SolicitudDeposito
{
    return SolicitudDeposito::crear(
        id: SolicitudDepositoId::generate(),
        numero: NumeroSolicitudDeposito::fromSecuencia(1),
        investigadorId: 'inv-1',
        tipoTramite: 'Depósito',
    );
}

/** Lleva una solicitud nueva hasta el estado "Requiere Corrección" (rechazo subsanable). */
function solicitudRequiereCorreccion(): SolicitudDeposito
{
    $solicitud = solicitudNueva();
    $solicitud->avanzarARevisionCuraduria();
    $solicitud->rechazarDocumentalmente('cur-1', TipoRechazoDocumental::Subsanable, 'Falta el permiso de movilización.');

    return $solicitud;
}

test('reabrirParaCorreccion vuelve a En Borrador desde Requiere Corrección', function (): void {
    $solicitud = solicitudRequiereCorreccion();
    expect($solicitud->estado())->toBe(EstadoSolicitudDeposito::RequiereCorreccion);

    $solicitud->reabrirParaCorreccion();

    expect($solicitud->estado())->toBe(EstadoSolicitudDeposito::EnBorrador);
});

test('reabrirParaCorreccion conserva el comentario del curador', function (): void {
    $solicitud = solicitudRequiereCorreccion();

    $solicitud->reabrirParaCorreccion();

    expect($solicitud->comentarioCurador())->toBe('Falta el permiso de movilización.');
});

test('tras reabrir, la solicitud puede reenviarse a revisión', function (): void {
    $solicitud = solicitudRequiereCorreccion();
    $solicitud->reabrirParaCorreccion();

    $solicitud->avanzarARevisionCuraduria();

    expect($solicitud->estado())->toBe(EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria);
});

test('reabrirParaCorreccion falla si la solicitud no está en Requiere Corrección', function (): void {
    $solicitud = solicitudNueva(); // En Borrador

    expect(fn () => $solicitud->reabrirParaCorreccion())
        ->toThrow(TransicionEstadoInvalida::class);
});

test('un rechazo definitivo (Rechazo Permanente) no puede reabrirse', function (): void {
    $solicitud = solicitudNueva();
    $solicitud->avanzarARevisionCuraduria();
    $solicitud->rechazarDocumentalmente('cur-1', TipoRechazoDocumental::Definitivo, 'No corresponde a la colección.');

    expect($solicitud->estado())->toBe(EstadoSolicitudDeposito::RechazoPermanente);
    expect(fn () => $solicitud->reabrirParaCorreccion())
        ->toThrow(TransicionEstadoInvalida::class);
});
