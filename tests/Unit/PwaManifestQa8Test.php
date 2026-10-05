<?php

declare(strict_types=1);

test('QA8 la declaración PWA incluye iconos instalables que existen con sus dimensiones reales', function (): void {
    $publico = dirname(__DIR__, 2).'/public';
    $manifest = json_decode(file_get_contents($publico.'/manifest.webmanifest'), true, flags: JSON_THROW_ON_ERROR);
    foreach (['192x192', '512x512'] as $tamano) {
        $icono = array_find($manifest['icons'], static fn (array $i): bool => $i['sizes'] === $tamano && $i['type'] === 'image/png');
        expect($icono)->not->toBeNull();
        [$ancho, $alto] = getimagesize($publico.$icono['src']);
        expect($ancho.'x'.$alto)->toBe($tamano);
    }
});
