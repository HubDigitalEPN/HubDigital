<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

test('la etiqueta del Excel conserva su identidad editorial y el selector real su vínculo', function (): void {
    $vista = 'gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-matriz';
    $rotulo = 'Tabla Darwin Core (.xlsx)';
    $clave = 'literal:'.$rotulo;
    $editor = view($vista, ['matrizId' => null, 'soloEdicionDeTextos' => true])->render();
    expect($editor)->toContain('data-wizard-copy="'.$clave.'"', $rotulo, 'Arrastra un Excel (.xlsx)')
        ->not->toContain('type="file"', 'wire:model="archivoMatriz"');
    expect(\App\Support\TextosSistema::catalogo()[hash('sha256', $rotulo)]['original'] ?? null)->toBe($rotulo);

    $operativo = new DOMDocument;
    @$operativo->loadHTML('<?xml encoding="utf-8" ?>'.view($vista, ['matrizId' => null])->render());
    $x = new DOMXPath($operativo);
    $campo = $operativo->getElementById('matriz-dwc');
    expect($campo->getAttribute('type'))->toBe('file')
        ->and($campo->getAttribute('wire:model'))->toBe('archivoMatriz')
        ->and($campo->getAttribute('accept'))->toBe('.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($campo->getAttribute('aria-labelledby'))->toBe('matriz-dwc-etiqueta matriz-dwc-accion')
        ->and($campo->getAttribute('aria-describedby'))->toBe('matriz-dwc-seleccion')
        ->and($x->query('//label[@for="matriz-dwc" and contains(., "Elegir archivo Excel")]')->length)->toBe(1);
    expect($operativo->saveHTML())->toContain('x-on:drop.prevent', 'x-on:dragover.prevent');
});

test('el oficio describe la matriz del expediente y conserva la redaccion historica firmada', function (): void {
    $solicitud = (object) ['solicitud_nombre_permiso' => 'Titular QA', 'solicitud_cedula' => '1707514251',
        'solicitud_cargo' => 'Investigador', 'solicitud_grupo' => 'Invertebrados terrestres',
        'solicitud_proyecto' => 'PRUEBA QA - SIMULACION SIN ENTREGA REAL'];
    $texto = \Modules\GestionPrestamosRecepciones\Presentation\Support\TextoSolicitudDeposito::redactar($solicitud);
    expect($texto)->toContain('matriz adjunta al expediente', 'Prueba qa - simulacion sin entrega real')->not->toContain('HubDigital', 'Google Drive');
    $clase = \Modules\GestionPrestamosRecepciones\Presentation\Support\TextoSolicitudDeposito::class;
    $plantilla = config('wizard-copy.solicitud.texto_pdf');
    expect($clase::marcadoresValidos($plantilla))->toBeTrue()
        ->and($clase::marcadoresValidos(str_replace('{identificacion}', '', $plantilla)))->toBeFalse()
        ->and($clase::redactar($solicitud, 'Oficio institucional. '.$plantilla))->toStartWith('Oficio institucional. Yo, Titular QA');
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
    expect($acciones)->toContain('eliminarMatriz');
});
