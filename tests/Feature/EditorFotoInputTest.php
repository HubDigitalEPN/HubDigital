<?php

declare(strict_types=1);

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\ComponentAttributeBag;

uses(Tests\TestCase::class);

test('el selector editorial renderiza la acción y el estado de una carga de fotografía', function (): void {
    $vista = base_path('resources/views/components/editor-foto-input.blade.php');
    $html = view()->file($vista, ['attributes' => new ComponentAttributeBag([
        'id' => 'ficha-foto',
        'modelo' => 'foto',
    ])])->render();
    $html .= view()->file($vista, ['attributes' => new ComponentAttributeBag([
        'id' => 'lab-bloque-foto-archivo',
        'modelo' => 'fotosBloques.bloque_1',
        'etiqueta' => 'Fotografía (opcional)',
    ])])->render();
    $errores = (new ViewErrorBag)->put('default', new MessageBag(['fotoEditorial' => 'Elija una imagen válida.']));
    view()->share('errors', $errores);
    $html .= view()->file(base_path('resources/views/livewire/administracion/partials/foto-editorial.blade.php'))->render();

    $documento = new DOMDocument;
    $documento->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($documento);

    foreach (['ficha-foto' => 'foto', 'lab-bloque-foto-archivo' => 'fotosBloques.bloque_1', 'foto-editorial-archivo' => 'fotoEditorial'] as $id => $modelo) {
        $entrada = $xpath->query('//input[@id="'.$id.'"]')->item(0);
        expect($entrada)->toBeInstanceOf(DOMElement::class)
            ->and($entrada->getAttribute('type'))->toBe('file')
            ->and($entrada->getAttribute('wire:model'))->toBe($modelo)
            ->and($entrada->getAttribute('accept'))->toBe('image/jpeg,image/png,image/webp,image/avif')
            ->and($entrada->getAttribute('aria-describedby'))->toBe($id.'-etiqueta '.$id.'-seleccion')
            ->and($entrada->getAttribute('x-on:change'))->toContain('files[0]?.name');
        expect($xpath->evaluate('normalize-space(//label[@for="'.$id.'"])'))->toBe('Elegir fotografía')
            ->and($xpath->evaluate('string(//*[@id="'.$id.'-seleccion" and @role="status"])'))->toBe('Ninguna foto seleccionada');
    }

    expect($xpath->evaluate('string(//*[@id="lab-bloque-foto-archivo-etiqueta"])'))->toBe('Fotografía (opcional)')
        ->and($xpath->evaluate('normalize-space(//*[@role="status" and @*[name()="wire:target"]="fotoEditorial"])'))->toBe('Cargando fotografía…')
        ->and($xpath->evaluate('normalize-space(//*[@role="alert"])'))->toBe('Elija una imagen válida.')
        ->and($xpath->query('//button[@type="submit" and @*[name()="wire:loading.attr"]="disabled" and @*[name()="wire:target"]="fotoEditorial,guardarFotoEditorial"]')->length)->toBe(1);
});
