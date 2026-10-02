<?php

use Laravel\Fortify\Features;
use App\Models\User;

function documentoFormularioPortal(string $html): DOMXPath
{
    $previo = libxml_use_internal_errors(true);
    try {
        $documento = new DOMDocument;
        $documento->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($documento);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
    }
}

it('el propósito público usa radios excluyentes y restaura la selección tras un error', function () {
    $this->skipUnlessFortifyFeature(Features::registration());
    $respuesta = $this->withSession(['_old_input' => ['rol' => 'DEPOSITANTE']])->get(route('register'))->assertOk();
    $dom = documentoFormularioPortal($respuesta->getContent());
    expect($dom->query('//fieldset[legend[contains(., "propósito")]]//input[@type="radio" and @name="rol"]')->length)->toBe(2)
        ->and($dom->query('//input[@name="rol" and @checked]')->length)->toBe(1)
        ->and($dom->query('//input[@name="rol" and @checked]')->item(0)->getAttribute('value'))->toBe('DEPOSITANTE')
        ->and($dom->query('//input[@name="rol" and @type="hidden"]')->length)->toBe(0);
});

it('la recuperación tiene un único encabezado principal con nombre identificable', function () {
    $this->skipUnlessFortifyFeature(Features::resetPasswords());
    $dom = documentoFormularioPortal($this->get(route('password.request'))->assertOk()->getContent());
    expect($dom->query('//h1')->length)->toBe(1)
        ->and(trim($dom->query('//h1')->item(0)->textContent))->toBe('Recuperar contraseña');
});

it('cada control de visibilidad nombra su campo y conserva oculto el valor inicial', function (string $ruta, array $campos, array $parametros) {
    if ($ruta === 'register') $this->skipUnlessFortifyFeature(Features::registration());
    if ($ruta === 'password.reset') $this->skipUnlessFortifyFeature(Features::resetPasswords());
    if ($ruta === 'password.confirm') $this->actingAs(User::factory()->create());
    $dom = documentoFormularioPortal($this->get(route($ruta, $parametros))->assertOk()->getContent());
    foreach ($campos as $campo => $etiqueta) {
        $boton = $dom->query('//button[@aria-label="'.$etiqueta.'"]');
        expect($boton->length)->toBe(1);
        $control = $boton->item(0)->getAttribute('aria-controls');
        $entrada = $dom->query('//input[@id="'.$control.'" and @name="'.$campo.'"]');
        expect($entrada->length)->toBe(1)
            ->and($entrada->item(0)->getAttribute('type'))->toBe('password')
            ->and($boton->item(0)->getAttribute('aria-pressed'))->toBe('false');
    }
})->with([
    ['login', ['password' => 'Mostrar contraseña'], []],
    ['register', ['password' => 'Mostrar contraseña', 'password_confirmation' => 'Mostrar confirmación de contraseña'], []],
    ['password.reset', ['password' => 'Mostrar nueva contraseña', 'password_confirmation' => 'Mostrar confirmación de contraseña'], ['token' => 'qa3-token', 'email' => 'qa3@example.test']],
    ['password.confirm', ['password' => 'Mostrar contraseña'], []],
]);
