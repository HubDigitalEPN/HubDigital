<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DatosIntegradosDocumento;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

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
        ->and($solicitud->datosFaltantesParaPersistir())->toContain('N.º Individuos', 'Grupo Animal', 'Localidad');
    if ($origen === 'Nacional (Ecuador)') {
        expect($solicitud->datosFaltantesParaPersistir())->toContain('N.º Morfoespecies', 'N.º Lotes');
    }
})->with([
    ['Nacional (Ecuador)', 'Sin permisos del MAE', []],
    ['Nacional (Ecuador)', 'Posee permisos del MAE', ['N.º Permiso Recolección', 'N.º Permiso Movilización']],
    ['Exterior (Extranjero)', 'Proviene de colección foránea', ['N.º Investigación']],
]);
