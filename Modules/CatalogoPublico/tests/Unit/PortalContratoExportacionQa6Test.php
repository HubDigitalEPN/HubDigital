<?php

declare(strict_types=1);

use Modules\CatalogoPublico\Domain\ValueObjects\ConfiguracionVisibilidad;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Domain\ValueObjects\PerfilExportacionPublica;
use Modules\CatalogoPublico\Domain\ValueObjects\RegistroExportable;
use Modules\CatalogoPublico\Infrastructure\Adapters\PhpSpreadsheetGeneradorXlsxAdapter;
use Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico;
use PhpOffice\PhpSpreadsheet\IOFactory;

function registroExportableQa6(array $cambios = [], ?ConfiguracionVisibilidad $visibilidad = null): RegistroExportable
{
    return RegistroExportable::desde(...array_replace([
        'occurrenceID' => 'QA6-EXPORT-45400', 'scientificName' => 'Naesiotus eschariferus',
        'typeStatus' => null, 'occurrenceStatus' => 'present', 'individualCount' => 1,
        'localityName' => 'Localidad registrada', 'country' => 'Ecuador',
        'decimalLatitude' => -0.658, 'decimalLongitude' => -76.452,
        'recordedBy' => 'Colector de prueba', 'samplingProtocol' => 'Método de prueba',
        'typeNotes' => null, 'specimenNotes' => null, 'stateProvince' => 'Galápagos',
        'elevationMinM' => null, 'elevationMaxM' => null, 'eventDate' => '2025-01-10',
        'caste' => null, 'lifeStage' => 'adult', 'disposition' => 'in_collection',
        'georeferenceRemarks' => 'Coordenadas recuperadas del Excel; precisión pendiente de revisión.',
        'localityExcel' => 'Localidad original', 'localityInec' => 'Referencia INEC',
        'localityInecReference' => 'Correspondencia por revisar',
        'visibilidad' => $visibilidad ?? ConfiguracionVisibilidad::todosHabilitados(),
    ], $cambios));
}

test('QA6 XLSX conserva advertencias y procedencia en bytes sin inventar incertidumbre métrica', function (): void {
    $filas = [registroExportableQa6()->toArray(), registroExportableQa6([
        'occurrenceID' => 'QA6-EXPORT-45469', 'georeferenceRemarks' => 'Referencia aproximada; revisar precisión.',
        'typeStatus' => 'paratype',
    ])->toArray()];
    $contenido = (new PhpSpreadsheetGeneradorXlsxAdapter)->generar(RegistroExportable::encabezados(), $filas);
    $archivo = tempnam(sys_get_temp_dir(), 'pest-qa6-contrato-');
    $libro = null;
    try {
        file_put_contents($archivo, $contenido);
        $libro = IOFactory::load($archivo);
        $hoja = $libro->getActiveSheet();
        $valores = $hoja->toArray(null, false, false);
        expect($valores[0])->toBe(PerfilExportacionPublica::ENCABEZADOS_XLSX);
        foreach ($filas as $i => $esperada) {
            $fila = array_combine($valores[0], array_map(static fn ($valor): string => (string) ($valor ?? ''), $valores[$i + 1]));
            expect($fila['typeStatus'])->toBe($esperada['typeStatus'])
                ->and($fila['disposition'])->toBe('in_collection')
                ->and($fila['georeferenceRemarks'])->toBe($esperada['georeferenceRemarks'])
                ->and($fila['coordinateUncertaintyInMeters'])->toBe('')
                ->and($fila['localityExcel'])->toBe('Localidad original')
                ->and($fila['localityInec'])->toBe('Referencia INEC')
                ->and($fila['exportProfile'])->toBe(PerfilExportacionPublica::IDENTIFICADOR);
            expect($hoja->getCell('H'.($i + 2))->getDataType())->toBe('n')
                ->and($hoja->getCell('I'.($i + 2))->getDataType())->toBe('n');
        }
    } finally {
        if ($libro !== null) $libro->disconnectWorksheets();
        unlink($archivo);
    }
});

test('QA6 la reserva de tipo coordenadas y localidad conserva la misma barrera en el nuevo esquema', function (): void {
    $flags = ConfiguracionVisibilidad::todosHabilitados()->toArray();
    $flags['typeStatusVisible'] = false;
    $flags['decimalLongitudeVisible'] = false;
    $flags['localityNameVisible'] = false;
    $fila = registroExportableQa6(['typeStatus' => 'holotype'], ConfiguracionVisibilidad::desde($flags))->toArray();
    foreach (['typeStatus', 'disposition', 'decimalLatitude', 'decimalLongitude', 'georeferenceRemarks',
        'coordinateUncertaintyInMeters', 'localityName', 'localityExcel', 'localityInec', 'localityInecReference'] as $campo) {
        expect($fila[$campo])->toBe('');
    }
    $incompleto = registroExportableQa6(['decimalLongitude' => null])->toArray();
    expect($incompleto['decimalLatitude'])->toBe('')->and($incompleto['decimalLongitude'])->toBe('')
        ->and($incompleto['georeferenceRemarks'])->toContain('precisión pendiente');
});

test('QA6 tipo y disposición tienen filtros independientes y conservan valores desconocidos', function (): void {
    $filtros = FiltrosBusqueda::desde(['filtroTipo' => 'Holotipo', 'filtroDisposicion' => 'En la colección']);
    expect($filtros->tipo)->toBe('holotype')->and($filtros->disposicion)->toBe('in_collection')
        ->and(FiltrosBusqueda::desde(['filtroDisposicion' => 'in_collection'])->estaVacio())->toBeFalse()
        ->and(EtiquetaDatoPublico::tipo(null))->toBe('No informado')
        ->and(EtiquetaDatoPublico::tipo('in_collection'))->toBe('in_collection')
        ->and(EtiquetaDatoPublico::disposicion('in_collection'))->toBe('En la colección')
        ->and(EtiquetaDatoPublico::tipo('Estado curatorial no catalogado'))->toBe('Estado curatorial no catalogado')
        ->and(FiltrosBusqueda::desde(['filtroTipo' => 'Estado curatorial no catalogado'])->tipo)->toBe('Estado curatorial no catalogado');
});

test('QA9 XLSX separa detección estado original disposición y notas en sus bytes', function (): void {
    $casos = ['present' => 'detected', 'absent' => 'notDetected', 'detected' => 'detected', 'notDetected' => 'notDetected',
        'destroyed' => '', 'loaned' => '', 'in_collection' => '', 'Estado por revisar' => '', '' => ''];
    $filas = [];
    foreach ($casos as $original => $deteccion) {
        $filas[] = registroExportableQa6(['occurrenceStatus' => $original, 'typeStatus' => 'paratype',
            'specimenNotes' => 'Destrucción para barcoding; conservar nota original.'])->toArray();
    }
    $contenido = (new PhpSpreadsheetGeneradorXlsxAdapter)->generar(RegistroExportable::encabezados(), $filas);
    $archivo = tempnam(sys_get_temp_dir(), 'pest-qa9-estado-'); $libro = null;
    try {
        file_put_contents($archivo, $contenido);
        $libro = IOFactory::load($archivo);
        $valores = $libro->getActiveSheet()->toArray(null, false, false);
        expect($valores[0])->toBe(PerfilExportacionPublica::ENCABEZADOS_XLSX)->toHaveCount(27);
        foreach (array_keys($casos) as $i => $original) {
            $fila = array_combine($valores[0], array_map(static fn ($v): string => (string) ($v ?? ''), $valores[$i + 1]));
            expect($fila['occurrenceStatus'])->toBe($casos[$original])
                ->and($fila['occurrenceStatusVerbatim'])->toBe($original)
                ->and($fila['disposition'])->toBe('in_collection')->and($fila['typeStatus'])->toBe('paratype')
                ->and($fila['specimenNotes'])->toBe('Destrucción para barcoding; conservar nota original.')
                ->and($fila['exportProfile'])->toBe('hubdigital.portal-publico/3.0');
        }
    } finally {
        $libro?->disconnectWorksheets(); unlink($archivo);
    }
    $flags = ConfiguracionVisibilidad::todosHabilitados()->toArray();
    $flags['occurrenceStatusVisible'] = false;
    $reservada = registroExportableQa6(['occurrenceStatus' => 'destroyed'], ConfiguracionVisibilidad::desde($flags))->toArray();
    expect($reservada['occurrenceStatus'])->toBe('')->and($reservada['occurrenceStatusVerbatim'])->toBe('')
        ->and($reservada['disposition'])->toBe('in_collection');
    expect(registroExportableQa6(['occurrenceStatus' => null])->toArray()['occurrenceStatus'])->toBe('');
});

test('QA7 XLSX tipa incertidumbre métrica sin convertir desconocidos en cero', function (): void {
    $contenido = (new PhpSpreadsheetGeneradorXlsxAdapter)->generar(['occurrenceID', 'coordinateUncertaintyInMeters'], [
        ['001', '12.5'], ['002', null], ['003', 'desconocida'], ['004', 0],
    ]);
    $archivo = tempnam(sys_get_temp_dir(), 'pest-qa7-incertidumbre-'); $libro = null;
    try {
        file_put_contents($archivo, $contenido);
        $libro = IOFactory::load($archivo); $hoja = $libro->getActiveSheet();
        // El lector representa inlineStr como RichText, igual que en el contrato QA5.
        expect((string) $hoja->getCell('A2')->getValue())->toBe('001')->and($hoja->getCell('A2')->getDataType())->toBe('inlineStr')
            ->and($hoja->getCell('B2')->getDataType())->toBe('n')->and((float) $hoja->getCell('B2')->getValue())->toBe(12.5)
            ->and((string) $hoja->getCell('B3')->getValue())->toBe('')
            ->and((string) $hoja->getCell('B4')->getValue())->toBe('desconocida')->and($hoja->getCell('B4')->getDataType())->toBe('inlineStr')
            ->and($hoja->getCell('B5')->getDataType())->toBe('n')->and((float) $hoja->getCell('B5')->getValue())->toBe(0.0);
    } finally {
        $libro?->disconnectWorksheets(); unlink($archivo);
    }
});
