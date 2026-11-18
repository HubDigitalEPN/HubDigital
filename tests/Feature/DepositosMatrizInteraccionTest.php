<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

test('el oficio describe la matriz del expediente y conserva la redaccion historica firmada', function (): void {
    $solicitud = (object) ['solicitud_nombre_permiso' => 'Titular QA', 'solicitud_cedula' => '1707514251',
        'solicitud_cargo' => 'Investigador', 'solicitud_grupo' => 'Invertebrados terrestres',
        'solicitud_proyecto' => 'PRUEBA QA - SIMULACION SIN ENTREGA REAL'];
    $texto = \Modules\GestionPrestamosRecepciones\Presentation\Support\TextoSolicitudDeposito::redactar($solicitud);
    expect($texto)->toContain('matriz adjunta al expediente de HubDigital', 'Prueba qa - simulacion sin entrega real')->not->toContain('Google Drive');
    $solicitud->solicitud_texto = 'Redacción histórica conservada';
    expect(\Modules\GestionPrestamosRecepciones\Presentation\Support\TextoSolicitudDeposito::redactar($solicitud))
        ->toBe('Redacción histórica conservada');
});

test('los botones renderizados de la matriz conservan argumentos ejecutables y escapan nombres taxonomicos', function (): void {
    $id = '1b935675-f355-4a59-935d-4e1dd1add34a';
    $nombre = "O'brienia \"QA\" <script>";
    $html = view('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-matriz', [
        'matrizId' => 'qa-matriz', 'archivoMatrizNombre' => 'matriz-qa.xlsx', 'totalRegistros' => 1,
        'camposDwCRecomendadosFaltantes' => [],
        'estadosRegistros' => [$id => ['especieIngresada' => 'Dolichoderus sp. QA01',
            'especieCorregida' => null, 'estado' => 'Pendiente', 'especiesSugeridas' => [$nombre]]],
    ])->render();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $acciones = [];
    foreach ($dom->getElementsByTagName('button') as $boton) {
        if ($boton->hasAttribute('wire:click')) $acciones[] = $boton->getAttribute('wire:click');
    }
    expect($acciones)->toContain("mantenerNombreOriginal('{$id}')")
        ->and(implode('|', $acciones))->toContain('aceptarSugerencia(', '\\u0027', '\\u003C')->not->toContain('@js', '<script>')
        ->and($dom->getElementsByTagName('script')->length)->toBe(0);
});
