<?php

declare(strict_types=1);

use Modules\CatalogoPublico\Domain\Exceptions\AutorImagenInvalidoException;
use Modules\CatalogoPublico\Domain\ValueObjects\AutorImagen;

test('crear arma el nombre completo a partir de nombre y apellido', function (): void {
    $autor = AutorImagen::crear('María', 'Gómez');

    expect($autor->nombre)->toBe('María')
        ->and($autor->apellido)->toBe('Gómez')
        ->and($autor->nombreCompleto())->toBe('María Gómez');
});

test('crear recorta los espacios sobrantes', function (): void {
    $autor = AutorImagen::crear('  María  ', '  Gómez  ');

    expect($autor->nombreCompleto())->toBe('María Gómez');
});

test('crear exige nombre y apellido', function (string $nombre, string $apellido): void {
    AutorImagen::crear($nombre, $apellido);
})->throws(AutorImagenInvalidoException::class)->with([
    'sin nombre' => ['', 'Gómez'],
    'sin apellido' => ['María', ''],
    'solo espacios en nombre' => ['   ', 'Gómez'],
    'solo espacios en apellido' => ['María', '   '],
    'ambos vacíos' => ['', ''],
]);
