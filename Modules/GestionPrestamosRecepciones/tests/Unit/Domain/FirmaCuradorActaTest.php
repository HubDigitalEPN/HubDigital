<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaFirmadaCriptograficamentePorCurador;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaValidada;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionDeEstadoInvalidaException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AlcancePrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\FirmaCuradorMetadata;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoPrestamo;

function firmaMetaValida(): FirmaCuradorMetadata
{
    return FirmaCuradorMetadata::crear(
        commonName: 'JUAN CURADOR',
        serialCertificado: 'AABBCC',
        huella: 'sha256:abc',
        algoritmo: 'SHA256withRSA',
        selloDeTiempo: new DateTimeImmutable('2026-07-02 10:00:00'),
    );
}

function actaEnPendienteValidacion(): ActaPrestamo
{
    $inicio = new DateTimeImmutable('2026-01-01');

    $acta = ActaPrestamo::emitir(
        id: ActaPrestamoId::generate(),
        codigoPrestamo: CodigoPrestamo::fromParts(2026, 1),
        solicitudPrestamoId: SolicitudPrestamoId::generate(),
        tipoPrestamo: TipoPrestamo::Temporal,
        alcancePrestamo: AlcancePrestamo::Nacional,
        fechaInicio: $inicio,
        fechaFin: $inicio->modify('+3 months'),
        pdfRuta: 'actas/acta.pdf',
    );

    $acta->marcarEnviada('inv-001');
    $acta->subirFirma('actas/firmada.pdf', 'documentos/id.pdf');
    $acta->pullEvents();

    return $acta;
}

// ── FirmaCuradorMetadata ────────────────────────────────────────────────────

test('crear rechaza common name vacío', function (): void {
    FirmaCuradorMetadata::crear('  ', 'serial', 'huella', 'algo');
})->throws(InvalidArgumentException::class);

test('crear acepta sello de tiempo nulo', function (): void {
    $meta = FirmaCuradorMetadata::crear('CN', 'serial', 'huella', 'algo');

    expect($meta->selloDeTiempo())->toBeNull()
        ->and($meta->commonName())->toBe('CN');
});

// ── ActaPrestamo::validarConFirmaCurador ────────────────────────────────────

test('validarConFirmaCurador marca el acta validada y registra la firma', function (): void {
    $acta = actaEnPendienteValidacion();

    $acta->validarConFirmaCurador('cur-001', 'actas/firmadas-curador/acta.pdf', firmaMetaValida());

    expect($acta->estado()->value)->toBe('validada')
        ->and($acta->validadaPor())->toBe('cur-001')
        ->and($acta->pdfFirmadoCuradorRuta())->toBe('actas/firmadas-curador/acta.pdf')
        ->and($acta->firmaCuradorMetadata()?->commonName())->toBe('JUAN CURADOR')
        ->and($acta->validadaEn())->not->toBeNull();
});

test('validarConFirmaCurador emite ActaValidada y ActaFirmadaCriptograficamentePorCurador', function (): void {
    $acta = actaEnPendienteValidacion();

    $acta->validarConFirmaCurador('cur-001', 'actas/firmadas-curador/acta.pdf', firmaMetaValida());
    $eventos = array_map('get_class', $acta->pullEvents());

    expect($eventos)->toContain(ActaValidada::class)
        ->and($eventos)->toContain(ActaFirmadaCriptograficamentePorCurador::class);
});

test('validarConFirmaCurador rechaza ruta de PDF vacía', function (): void {
    actaEnPendienteValidacion()->validarConFirmaCurador('cur-001', '   ', firmaMetaValida());
})->throws(InvalidArgumentException::class);

test('validarConFirmaCurador no permite firmar un acta que no está pendiente de validación', function (): void {
    $inicio = new DateTimeImmutable('2026-01-01');
    $acta = ActaPrestamo::emitir(
        id: ActaPrestamoId::generate(),
        codigoPrestamo: CodigoPrestamo::fromParts(2026, 2),
        solicitudPrestamoId: SolicitudPrestamoId::generate(),
        tipoPrestamo: TipoPrestamo::Temporal,
        alcancePrestamo: AlcancePrestamo::Nacional,
        fechaInicio: $inicio,
        fechaFin: $inicio->modify('+3 months'),
        pdfRuta: 'actas/acta.pdf',
    );

    // Estado PendienteEnvio → transición inválida.
    $acta->validarConFirmaCurador('cur-001', 'actas/firmadas-curador/acta.pdf', firmaMetaValida());
})->throws(TransicionDeEstadoInvalidaException::class);
