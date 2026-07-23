<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\Events\DepositoDevuelto;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionEstadoInvalida;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoQRLote;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;

function solicitudEnEstado(
    EstadoSolicitudDeposito $estado,
    TipoTramite $tipo = TipoTramite::Deposito,
): SolicitudDeposito {
    return SolicitudDeposito::reconstituir(
        id: SolicitudDepositoId::generate(),
        numero: NumeroSolicitudDeposito::fromSecuencia(1),
        investigadorId: 'inv-001',
        tipoTramite: $tipo,
        estado: $estado,
        origenRecoleccion: null,
        situacionRegulatoria: null,
        provinciaOrigen: null,
        sinDocumentacion: false,
        nroPermisoRecoleccion: null,
        nroPermisoMovilizacion: null,
        grupoAnimal: null,
        localidad: null,
        origenDonacion: null,
        documentosAdjuntos: [],
        datosFaltantes: [],
        curadorResponsable: 'cur-001',
        codigoQR: CodigoQRLote::generar(),
    );
}

test('un deposito aprobado se puede devolver a su depositante', function (): void {
    $solicitud = solicitudEnEstado(EstadoSolicitudDeposito::AprobadaDocumentalmente);

    $solicitud->registrarDevolucion('cur-001');

    expect($solicitud->estado())->toBe(EstadoSolicitudDeposito::Devuelta);
});

test('la devolucion emite su evento de dominio con el curador responsable', function (): void {
    $solicitud = solicitudEnEstado(EstadoSolicitudDeposito::AprobadaDocumentalmente);

    $solicitud->registrarDevolucion('cur-042');
    $eventos = $solicitud->pullEvents();

    $devolucion = array_values(array_filter($eventos, fn ($e) => $e instanceof DepositoDevuelto));

    expect($devolucion)->toHaveCount(1)
        ->and($devolucion[0]->curadorId)->toBe('cur-042');
});

test('una donacion no se devuelve: es una cesion definitiva al patrimonio', function (): void {
    $donacion = solicitudEnEstado(EstadoSolicitudDeposito::AprobadaDocumentalmente, TipoTramite::Donacion);

    $donacion->registrarDevolucion('cur-001');
})->throws(DomainException::class, 'cesión definitiva');

test('no se devuelve un deposito que aun no fue aprobado', function (EstadoSolicitudDeposito $estado): void {
    solicitudEnEstado($estado)->registrarDevolucion('cur-001');
})->with([
    EstadoSolicitudDeposito::EnBorrador,
    EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria,
    EstadoSolicitudDeposito::RequiereCorreccion,
    EstadoSolicitudDeposito::RechazoPermanente,
])->throws(TransicionEstadoInvalida::class);

test('no se devuelve dos veces el mismo deposito', function (): void {
    $solicitud = solicitudEnEstado(EstadoSolicitudDeposito::AprobadaDocumentalmente);
    $solicitud->registrarDevolucion('cur-001');

    $solicitud->registrarDevolucion('cur-001');
})->throws(TransicionEstadoInvalida::class);

test('la devolucion exige saber que curador la registro', function (): void {
    solicitudEnEstado(EstadoSolicitudDeposito::AprobadaDocumentalmente)->registrarDevolucion('   ');
})->throws(DomainException::class);
