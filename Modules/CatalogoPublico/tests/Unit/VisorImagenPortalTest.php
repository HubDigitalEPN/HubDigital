<?php

uses(Tests\InfrastructureTestCase::class);

it('el visor usa un diálogo nativo identificado y mantiene sus controles dentro del diálogo', function () {
    $html = view('catalogopublico::components.visor-imagen')->render();
    $erroresAnteriores = libxml_use_internal_errors(true);
    try {
        $documento = new DOMDocument;
        $documento->loadHTML('<?xml encoding="UTF-8">'.$html);
        $dom = new DOMXPath($documento);
        $dialogo = $dom->query('//dialog')->item(0);

        expect($dom->query('//dialog')->length)->toBe(1)
            ->and($dom->query('//dialog//h2[@id="'.$dialogo->getAttribute('aria-labelledby').'"]')->length)->toBe(1)
            ->and($dom->query('//dialog//button[@type="button" and @aria-label="Cerrar fotografía" and @autofocus]')->length)->toBe(1)
            ->and($dom->query('//dialog//a[@aria-label="Descargar fotografía"]')->length)->toBe(1)
            ->and($dom->query('//dialog/img')->length)->toBe(1);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($erroresAnteriores);
    }
});
