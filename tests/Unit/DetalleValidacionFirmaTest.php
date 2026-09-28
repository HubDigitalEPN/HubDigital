<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DetalleValidacionFirma;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma;

function detalleFirmaMotor(bool $aceptada): DetalleValidacionFirma
{
    return new DetalleValidacionFirma(
        resultado: ResultadoValidacionFirma::Firmado,
        integridadCriptografica: true,
        documentoCompletoFirmado: true,
        contenidoOficialCoincide: true,
        certificadoVigente: true,
        certificadoConfiable: true,
        certificado: [
            'nombre' => 'Firmante de prueba',
            'tipo_firma' => 'ETSI.CAdES.detached',
        ],
        aceptadaPorMotor: $aceptada,
        formatoFirmaAceptado: true,
    );
}

test('conserva la aceptación emitida por el motor Java', function (): void {
    expect(detalleFirmaMotor(true)->esAceptable())->toBeTrue();
});

test('no sustituye un rechazo del motor por comprobaciones PHP', function (): void {
    expect(detalleFirmaMotor(false)->esAceptable())->toBeFalse();
});

test('una respuesta sin decisión explícita del motor queda sin aceptar', function (): void {
    $detalle = new DetalleValidacionFirma(
        resultado: ResultadoValidacionFirma::Firmado,
        integridadCriptografica: true,
        documentoCompletoFirmado: true,
        contenidoOficialCoincide: true,
        certificadoVigente: true,
        certificadoConfiable: true,
        certificado: ['tipo_firma' => 'ETSI.CAdES.detached'],
    );

    expect($detalle->esAceptable())->toBeFalse();
});
