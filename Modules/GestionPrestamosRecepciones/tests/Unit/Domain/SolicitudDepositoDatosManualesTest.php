<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DatosIntegradosDocumento;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

test('una localidad que se dejó vacía explícitamente no vuelve a completarse desde el PDF', function (): void {
    $solicitud = SolicitudDeposito::crear(SolicitudDepositoId::generate(), NumeroSolicitudDeposito::fromSecuencia(8), 'depositante-qa', 'Depósito');
    $solicitud->declararOrigenRecoleccion('Nacional (Ecuador)');
    $solicitud->completarDatoFaltante('Localidad', '');
    $solicitud->integrarDatosDeDocumentos(new DatosIntegradosDocumento(null, null, null, null, 'Localidad del PDF', null), []);
    expect($solicitud->localidad())->toBeNull()->and($solicitud->datosFaltantesParaPersistir())->not->toContain('Localidad');
});

test('rechaza cantidades vacías de material aunque se complete directamente el dominio', function (string $campo): void {
    $solicitud = SolicitudDeposito::crear(
        SolicitudDepositoId::generate(), NumeroSolicitudDeposito::fromSecuencia(3), 'depositante-qa', 'Depósito',
    );
    $solicitud->declararOrigenRecoleccion('Nacional (Ecuador)');
    $solicitud->declararSituacionRegulatoria('Sin permisos del MAE');
    $solicitud->integrarDatosDeDocumentos(new DatosIntegradosDocumento(null, null, null, null, null, null), []);
    foreach (['0', '-1', '1.5', '2147483648'] as $valor) {
        expect(fn () => $solicitud->completarDatoFaltante($campo, $valor))->toThrow(\DomainException::class);
        expect($solicitud->datosFaltantesParaPersistir())->toContain($campo);
    }
    $solicitud->completarDatoFaltante($campo, '1');
    expect($solicitud->datosFaltantesParaPersistir())->not->toContain($campo);
})->with(['N.º Individuos', 'N.º Morfoespecies', 'N.º Lotes']);

test('mantiene pendientes las cantidades cero extraídas del documento', function (): void {
    $solicitud = SolicitudDeposito::crear(
        SolicitudDepositoId::generate(), NumeroSolicitudDeposito::fromSecuencia(4), 'depositante-qa', 'Depósito',
    );
    $solicitud->declararOrigenRecoleccion('Nacional (Ecuador)');
    $solicitud->declararSituacionRegulatoria('Sin permisos del MAE');
    $solicitud->integrarDatosDeDocumentos(new DatosIntegradosDocumento(
        null, null, null, null, null, null, nroIndividuos: '0', nroMorfoespecies: '0', nroLotes: '0',
    ), []);
    expect($solicitud->nroIndividuos())->toBeNull()
        ->and($solicitud->nroMorfoespecies())->toBeNull()
        ->and($solicitud->nroLotes())->toBeNull()
        ->and($solicitud->datosFaltantesParaPersistir())->toContain('N.º Individuos', 'N.º Morfoespecies', 'N.º Lotes');
});

test('permite registrar manualmente el origen de una donación', function (): void {
    $solicitud = SolicitudDeposito::crear(
        id: SolicitudDepositoId::generate(),
        numero: NumeroSolicitudDeposito::fromSecuencia(1),
        investigadorId: 'depositante-qa',
        tipoTramite: 'Donación',
    );

    $solicitud->completarDatoFaltante('Origen Donación', 'Colección didáctica sintética QA');

    expect($solicitud->origenDonacion())->toBe('Colección didáctica sintética QA');
});

test('exige números regulatorios solo cuando corresponden al origen y situación declarados', function (string $origen, string $situacion, array $permisos): void {
    $solicitud = SolicitudDeposito::crear(
        SolicitudDepositoId::generate(), NumeroSolicitudDeposito::fromSecuencia(2), 'depositante-qa', 'Depósito',
    );
    $solicitud->declararOrigenRecoleccion($origen);
    $solicitud->declararSituacionRegulatoria($situacion);
    $solicitud->integrarDatosDeDocumentos(new DatosIntegradosDocumento(null, null, null, null, null, null), []);
    // Completar provincia después de extraer tampoco debe inventar un requisito MAE.
    $solicitud->completarDatoFaltante($origen === 'Exterior (Extranjero)' ? 'Administración Política' : 'Provincia', 'Santo Domingo de los Tsáchilas');
    expect(array_values(array_intersect($solicitud->datosFaltantesParaPersistir(), [
        'N.º Permiso Recolección', 'N.º Permiso Movilización', 'N.º Investigación',
    ])))->toBe($permisos)
        ->and($solicitud->datosFaltantesParaPersistir())->toContain('N.º Individuos', 'Grupo Animal')
        ->and($solicitud->datosFaltantesParaPersistir())->not->toContain('Localidad');
    if ($origen === 'Nacional (Ecuador)') {
        expect($solicitud->datosFaltantesParaPersistir())->toContain('N.º Morfoespecies', 'N.º Lotes');
    }
})->with([
    ['Nacional (Ecuador)', 'Sin permisos del MAE', []],
    ['Nacional (Ecuador)', 'Posee permisos del MAE', ['N.º Permiso Recolección', 'N.º Permiso Movilización']],
    ['Exterior (Extranjero)', 'Proviene de colección foránea', ['N.º Investigación']],
]);
