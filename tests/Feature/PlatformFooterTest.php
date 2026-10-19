<?php

uses(Tests\DatabaseFeatureTestCase::class);

test('el pie es compacto y sus tres enlaces abren la información completa', function (): void {
    $this->get(route('portal.privacidad'))->assertOk()
        ->assertSee(route('portal.licencia'), false)->assertSee(route('portal.citar'), false)->assertSee(route('portal.creditos'), false)
        ->assertDontSee('Cómo citar la plataforma y sus fotografías')->assertDontSee('Créditos y financiamiento');
    $this->get(route('portal.licencia'))->assertOk()->assertSee('CC BY 4.0')->assertSee('materiales de terceros');
    $this->get(route('portal.citar'))->assertOk()->assertSee('MEPNINV00001')->assertSee('Fecha de consulta:')->assertSee('Versión');
    $this->get(route('portal.creditos'))->assertOk()->assertSee('Hernán Troya')->assertSee('Financiamiento:');
});
