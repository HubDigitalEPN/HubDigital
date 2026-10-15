<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\Services\AnalizadorDocumentoAmbiental;

function fixtureAmbiental(string $nombre): string
{
    return (string) file_get_contents(__DIR__.'/../Fixtures/documentos_ambientales/'.$nombre.'.txt');
}

it('reconoce guías tabulares con títulos intercalados por columnas y conserva sus controles de estructura', function (string $texto): void {
    $resultado = (new AnalizadorDocumentoAmbiental)->analizar($texto);
    expect($resultado['tipo_detectado'])->toBe(AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION)
        ->and($resultado['estructura_esencial'])->toBeTrue()
        ->and($resultado['requiere_confirmacion_humana'])->toBeTrue();
})->with([
    'columnas intercaladas' => ["Nro. MAE-DZ9-FAU-FLO-2026-999\nGUÍA DE MOVILIZACIÓN N.º 999-2026 RVS-FLO/FAU/DZ9/MAE\nDE ESPECÍMENES VIDA Autorización Proyecto de prueba\nSILVESTRE No:\n1.- ORIGEN 2.- DESTINO\nProvincia Pichincha Provincia Napo\nVálido desde: Válido hasta:\nDATOS DE LAS ESPECIES DE ENTOMOFAUNA\nCódigo Sitio Especie Estado Cantidad"],
    'título partido entre columnas' => ["GUÍA DE\nMOVILIZACIÓN DE\nAutorización N.º 999-2026 RVS-FLO/FAU/DZ9/MAE\nESPECÍMENES VIDA\nde Recolección Proyecto de prueba\nSILVESTRE\n1.- ORIGEN 2.- DESTINO\nVálido desde: Válido hasta:\nDATOS DE LAS ESPECIES\nCódigo Sitio Nombre científico Estado Cantidad"],
    'errata del emisor' => ["GUÍA DE MOVLIZACIÓN DE ESPECÍMENES DE FLORA Y FAUNA SILVESTRE\nFecha de emisión: 25 de mayo de 2026\nFecha de movilización: 25 de mayo de 2026\nVálido hasta: 29 de mayo de 2026\nDATOS DE LAS ESPECIES\nCódigo Sitio Especie Estado Cantidad"],
    'formato antiguo por ciudades' => ["GUÍA DE MOVILIZACIÓN DE VIDA SILVESTRE\nNro. 999-2021-MOV-VS-OTO-MAAE\nMinisterio del Ambiente y Agua\nEMISIÓN MOVILIZACIÓN VÁLIDO\nCiudad de origen: El Coca Ciudad de destino: Quito\nDATOS DE LAS ESPECIES\nCódigo Nombre científico Estado Cantidad"],
    'guía otorgada mediante oficio' => ["Oficio Nro. MAE-DZ8-2026-9999-OF\nAsunto: GUÍA DE MOVILIZACIÓN DE VIDA SILVESTRE Nº 999-2026-MOV-VS-OTOR-MAE\nEl Ministerio del Ambiente otorga la GUÍA DE MOVILIZACIÓN DE VIDA SILVESTRE correspondiente a la Autorización de Recolección de Vida Silvestre Nº 998-2026-OTOR-DZ8-MAE. Las muestras serán transportadas desde la Provincia de Orellana hacia la ciudad de Quito."],
    'autorización de movilización de vida silvestre' => ["AUTORIZACIÓN DE MOVILIZACIÓN DE VIDA SILVESTRE\nMAATE-DZ6-OTMO-AM-999-2025\nFecha de emisión: 08 de mayo de 2025\nFecha de movilización: 09 de mayo de 2025\nVálido hasta: 09 de mayo de 2025\nEl Ministerio del Ambiente, Agua y Transición Ecológica emite la presente Autorización de Movilización desde la provincia de Morona Santiago hasta el Laboratorio de Quito."],
]);

it('no confunde una mención de guía con el documento de movilización', function (): void {
    $texto = 'Solicito una GUÍA DE MOVILIZACIÓN N.º 999-2026. DATOS DE LAS ESPECIES: insectos. Ciudad de origen: Quito, ciudad de destino: Tena.';
    expect((new AnalizadorDocumentoAmbiental)->analizar($texto)['tipo_detectado'])->toBe(AnalizadorDocumentoAmbiental::DESCONOCIDO);
});

it('reconoce actos oficiales de recolección con títulos abreviados y conserva el rechazo de una negativa', function (string $texto, string $esperado): void {
    expect((new AnalizadorDocumentoAmbiental)->analizar($texto)['tipo_detectado'])->toBe($esperado);
})->with([
    'ARVS abreviado' => ["AUTORIZACIÓN VIDA SILVESTRE\nN0 999-2025-ARVS-OTMO-DZ6-MAATE\nEl Ministerio del Ambiente, Agua y Transición Ecológica; esta dependencia Ministerial autoriza a:\nPara realizar el estudio. ÁREA GEOGRÁFICA QUE CUBRE LA RECOLECCIÓN. METODOLOGÍA PARA RECOLECTAR. MÉTODOS DE PRESERVACIÓN Y MOVILIZACIÓN.", AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION],
    'permiso concedido por oficio' => ["Oficio Nro. MAATE-DZ7-2025-9999-O\nMinisterio del Ambiente, Agua y Transición Ecológica\nAutoriza el permiso de Recolección de flora y fauna silvestre, signado con el Nro. 999-2025-FLO-FAU-DZ7L-GBVS/MAATE, el mismo que tendrá una vigencia hasta el 22 de junio de 2026.", AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION],
    'permiso denegado' => ["Oficio Nro. MAATE-DZ7-2025-9999-O\nMinisterio del Ambiente, Agua y Transición Ecológica\nNo autoriza el permiso de Recolección de flora y fauna silvestre. Tendrá una vigencia hasta el 22 de junio de 2026.", AnalizadorDocumentoAmbiental::DESCONOCIDO],
]);

it('reconoce familias versionadas de autorizaciones sin depender de una plantilla visual', function (
    string $fixture,
    string $numeroDocumento,
    string $numeroAutorizacion,
): void {
    $resultado = (new AnalizadorDocumentoAmbiental)->analizar(fixtureAmbiental($fixture));

    expect($resultado['tipo_detectado'])->toBe(AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION)
        ->and($resultado['numero_documento'])->toBe($numeroDocumento)
        ->and($resultado['numero_autorizacion'])->toBe($numeroAutorizacion)
        ->and($resultado['decision_automatica'])->toBe('REVISION_HUMANA')
        ->and($resultado['requiere_confirmacion_humana'])->toBeTrue()
        ->and($resultado['version_reglas'])->toBe(AnalizadorDocumentoAmbiental::VERSION_REGLAS)
        ->and($resultado['evidencias_campos']['numero_documento']['fragmento'] ?? null)->not->toBeNull()
        ->and($resultado['evidencias_campos']['numero_autorizacion']['patron'] ?? null)->not->toBeNull();
})->with([
    'formato zonal tabular' => ['autorizacion_zonal_2025', '101-2025-FA-FLO/DZ5/MAATE', '101-2025-FA-FLO/DZ5/MAATE'],
    'formato SUIA con código separado' => ['autorizacion_suia_2023', 'MAATE-ARSFC-2023-9123', 'MAATE-ARSFC-2023-9123'],
    'oficio Quipux que otorga autorización' => ['autorizacion_oficio_2026', 'MAATE-DZ8-2026-9999-O', '555-2026-OTOR-DZ8-MAATE'],
]);

it('reconoce familias versionadas de guías y conserva evidencia por campo', function (
    string $fixture,
    string $numeroGuia,
    string $numeroAutorizacion,
    string $fechaInicio,
    string $fechaFin,
): void {
    $analizador = new AnalizadorDocumentoAmbiental;
    $resultado = $analizador->analizar(fixtureAmbiental($fixture));

    expect($resultado['tipo_detectado'])->toBe(AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION)
        ->and($resultado['numero_documento'])->toBe($numeroGuia)
        ->and($resultado['numero_autorizacion'])->toBe($numeroAutorizacion)
        ->and($resultado['valido_desde'])->toBe($fechaInicio)
        ->and($resultado['valido_hasta'])->toBe($fechaFin)
        ->and($resultado['estado'])->toBe('revision')
        ->and($analizador->campoTieneEvidenciaSuficiente($resultado, 'numero_documento'))->toBeTrue()
        ->and($analizador->campoTieneEvidenciaSuficiente($resultado, 'numero_autorizacion'))->toBeTrue();
})->with([
    'guía zonal 2022' => ['guia_zonal_2022', 'MAATE-DZ2-OTE-123-2022', '321-2022-RVS-FLO-FAU-DZ2-OTE', '2022-11-15', '2022-11-18'],
    'guía zonal 2026 con ruido OCR y saltos' => ['guia_zonal_2026_ocr_mutado', '777-2026-MAE-DZ7-OTMA-UBVS', '111-2026 IC-FLO/FAU/DZL/OTM//MAE', '2026-07-01', '2026-07-03'],
    'guía SUIA 2023' => ['guia_suia_2023', '54321', 'MAATE-ARSFC-2023-9123', '2023-06-08', '2023-06-09'],
]);

it('limita los códigos de muestra a la sección probatoria incluso si cambia el formato', function (): void {
    $zonal = (new AnalizadorDocumentoAmbiental)->analizar(fixtureAmbiental('guia_zonal_2022'));
    $ocr = (new AnalizadorDocumentoAmbiental)->analizar(fixtureAmbiental('guia_zonal_2026_ocr_mutado'));

    expect($zonal['codigos_muestra'])->toBe(['QA1', 'QA2'])
        ->and($zonal['evidencias_codigos_muestra'])->toHaveCount(2)
        ->and($ocr['codigos_muestra'])->toBe(['PMB-01', 'PMB-02'])
        ->and($ocr['codigos_muestra'])->not->toContain('ABC-9999')
        ->and($ocr['numero_individuos'])->toBe(37)
        ->and($ocr['numero_lotes'])->toBe(2)
        ->and($ocr['evidencias_campos']['numero_individuos']['fragmento'] ?? null)->not->toBeNull();
});

it('rechaza un archivo incorrecto aunque mencione permisos y movilización', function (): void {
    $resultado = (new AnalizadorDocumentoAmbiental)->analizar(<<<'TXT'
    INFORME ADMINISTRATIVO DE PRUEBA
    La reunión trató sobre permisos, recolección y guías de movilización.
    No constituye autorización, guía ni acto emitido por la autoridad ambiental.
    TXT);

    expect($resultado['tipo_detectado'])->toBe(AnalizadorDocumentoAmbiental::DESCONOCIDO)
        ->and($resultado['estado'])->toBe('rechazado')
        ->and($resultado['autocompletado_habilitado'])->toBeFalse()
        ->and($resultado['numero_documento'])->toBeNull();
});

it('envía a revisión códigos contradictorios y no autocompleta el campo ambiguo', function (): void {
    $resultado = (new AnalizadorDocumentoAmbiental)->analizar(<<<'TXT'
    AUTORIZACIÓN DE RECOLECCIÓN DE VIDA SILVESTRE N.º 111-2026-FA-FLO-DZ8-MAATE
    El Ministerio del Ambiente y Energía otorga la autorización a una organización de prueba.
    AUTORIZACIÓN DE RECOLECCIÓN DE VIDA SILVESTRE N.º 999-2026-FA-FLO-DZ8-MAATE
    Vigencia de la Autorización de Recolección: desde 01/01/2026 hasta 31/12/2026.
    ÁREA GEOGRÁFICA QUE CUBRE LA RECOLECCIÓN.
    Documento firmado electrónicamente.
    TXT);

    expect($resultado['tipo_detectado'])->toBe(AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION)
        ->and($resultado['estado'])->toBe('revision')
        ->and($resultado['numero_autorizacion'])->toBeNull()
        ->and($resultado['autocompletado_habilitado'])->toBeTrue()
        ->and($resultado['candidatos_ambiguos']['numero_autorizacion'] ?? [])->toHaveCount(2);
});

it('no confunde la vigencia desde-hasta con una ruta de movilización', function (): void {
    $resultado = (new AnalizadorDocumentoAmbiental)->analizar(fixtureAmbiental('autorizacion_zonal_2025'));

    expect($resultado['valido_desde'])->toBe('2025-07-21')
        ->and($resultado['valido_hasta'])->toBe('2025-10-21')
        ->and($resultado['origen'])->toBeNull()
        ->and($resultado['destino'])->toBeNull();
});

it('valida individualmente los dos tipos del wizard sin comparar sus códigos ni vigencias', function (): void {
    $analizador = new AnalizadorDocumentoAmbiental;
    $resultado = $analizador->validarExpediente([
        AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION => $analizador->analizar(fixtureAmbiental('autorizacion_zonal_2025')),
        AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION => $analizador->analizar(fixtureAmbiental('guia_zonal_2026_ocr_mutado')),
    ], compararEntreDocumentos: false);
    expect($resultado['estado'])->toBe('revision')->and($resultado['errores'])->toBeEmpty()
        ->and(implode(' ', $resultado['advertencias']))->not->toContain('contrastar el vínculo');
});

it('bloquea el expediente ante contradicción de códigos o vigencias', function (): void {
    $analizador = new AnalizadorDocumentoAmbiental;
    $autorizacion = $analizador->analizar(fixtureAmbiental('autorizacion_zonal_2025'));
    $guia = $analizador->analizar(fixtureAmbiental('guia_zonal_2026_ocr_mutado'));
    $resultado = $analizador->validarExpediente([
        AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION => $autorizacion,
        AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION => $guia,
    ]);

    expect($resultado['estado'])->toBe('rechazado')
        ->and($resultado['decision_automatica'])->toBe('RECHAZADO')
        ->and($resultado['autocompletado_habilitado'])->toBeFalse()
        ->and(implode(' ', $resultado['errores']))->toContain('guía cita la autorización');
});

it('envía a revisión humana cuando falta evidencia para el contraste sin inventar el vínculo', function (): void {
    $resultado = (new AnalizadorDocumentoAmbiental)->validarExpediente([
        AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION => [
            'tipo_detectado' => AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION,
            'numero_autorizacion' => null,
            'errores' => [],
            'advertencias' => [],
        ],
        AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION => [
            'tipo_detectado' => AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION,
            'numero_autorizacion' => null,
            'errores' => [],
            'advertencias' => [],
        ],
    ]);

    expect($resultado['estado'])->toBe('revision')
        ->and($resultado['decision_automatica'])->toBe('REVISION_HUMANA')
        ->and(implode(' ', $resultado['advertencias']))->toContain('No fue posible contrastar');
});
