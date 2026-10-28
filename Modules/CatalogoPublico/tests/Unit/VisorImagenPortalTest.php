<?php

// RBAC requiere una cuenta verificada y membresías persistidas.
uses(Tests\DatabaseFeatureTestCase::class);

it('el visor mantiene los controles accesibles y reserva la descarga a cuentas habilitadas', function (bool $autorizado) {
    if ($autorizado) $this->actingAs(\App\Models\User::factory()->curador()->create());
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
            ->and($dom->query('//dialog//a[@aria-label="Descargar fotografía"]')->length)->toBe($autorizado ? 1 : 0)
            ->and($dom->query('//dialog/img')->length)->toBe(1)
            ->and($dom->query('//dialog/img/following-sibling::section[contains(@class,"photo-attributions")]')->length)->toBe(1)
            ->and($dom->query('//dialog/p[contains(@class,"collection-photo-view") and @x-text="vista"]')->length)->toBe(1)
            ->and($dom->query('//dialog//dt')->length)->toBe(5);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($erroresAnteriores);
    }
})->with([false, true]);
