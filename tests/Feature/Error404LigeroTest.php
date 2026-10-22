<?php

declare(strict_types=1);

test('el error publico 404 conserva su estado y limita el HTML sin incrustar imagenes', function (): void {
    $response = $this->get('/pagina-inexistente-para-regresion-del-error-404');
    $response->assertNotFound()->assertSee('Página no encontrada');
    expect(strlen($response->getContent()))->toBeLessThan(20_000)
        ->and($response->getContent())->not->toContain('data:image/')
        ->and($response->getContent())->toContain('logo-epn-lockup-v2.avif', 'logo-biologia-v1.avif');
    $this->head('/pagina-inexistente-para-regresion-del-error-404')->assertNotFound();
});
