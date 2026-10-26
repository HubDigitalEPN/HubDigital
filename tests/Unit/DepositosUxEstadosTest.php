<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\RecepcionFisicaLote;

test('la recepción distingue criterios pendientes de resultados comprobados', function (): void {
    $componente = new RecepcionFisicaLote;
    $tienePendientes = Closure::bind(
        fn (): bool => $this->tieneCriteriosPendientesOInvalidos(),
        $componente,
        RecepcionFisicaLote::class,
    );

    expect($tienePendientes())->toBeTrue();

    $componente->estadoCriterio = [
        0 => 'conforme',
        1 => 'conforme',
        2 => 'no_conforme',
        3 => 'conforme',
    ];

    expect($tienePendientes())->toBeFalse();

    $componente->estadoCriterio[2] = 'valor_invalido';

    expect($tienePendientes())->toBeTrue();
});

test('las vistas conservan estados documentales y la impresión del QR sin ayudas', function (): void {
    $raiz = dirname(__DIR__, 2);
    $documentos = file_get_contents($raiz.'/Modules/GestionPrestamosRecepciones/resources/views/investigador/registro-solicitud-deposito/paso-documentos.blade.php');
    $detalle = file_get_contents($raiz.'/Modules/GestionPrestamosRecepciones/resources/views/investigador/detalle-deposito.blade.php');
    $recepcion = file_get_contents($raiz.'/Modules/GestionPrestamosRecepciones/resources/views/curador/recepcion-fisica-lote.blade.php');

    expect($documentos)
        ->toContain('Sin archivos requeridos', 'Analizando documentos…', 'Ver mis depósitos')
        ->not->toContain("WizardCopy::text('documentos.intro_sin_archivos')", 'Cargando documentos requeridos');

    expect((require $raiz.'/config/wizard-copy.php')['documentos']['intro_sin_archivos'])
        ->toContain('no necesitas adjuntar archivos');

    expect($detalle)
        ->toContain('Imprimir QR', "route('prestamos.deposito.qr', \$deposito->id)", 'target="_blank" rel="noopener"')
        ->not->toContain('Imprimir / Guardar QR (PDF)', 'Guardar como PDF');

    expect($recepcion)
        ->toContain('Pendiente de comprobar', 'Criterio confirmado', 'No conformidad / observación')
        ->not->toContain('wire:model="conforme.');
});
