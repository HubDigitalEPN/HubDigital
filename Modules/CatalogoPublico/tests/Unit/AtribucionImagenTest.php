<?php

declare(strict_types=1);

use Modules\CatalogoPublico\Domain\ValueObjects\AtribucionImagen;
use Modules\CatalogoPublico\Domain\ValueObjects\AutorImagen;

test('una atribución admite nombre completo o autoría pendiente sin inferir licencia, titular ni contacto', function (): void {
    expect((new AtribucionImagen)->toArray())->toBe(['photographer' => '', 'license' => '', 'license_holder' => '', 'license_institution' => '', 'contact' => '', 'view' => ''])
        ->and(AutorImagen::desdeNombreCompleto('María del Carmen Gómez')->nombreCompleto())->toBe('María del Carmen Gómez')
        ->and(AutorImagen::desdeNombreCompleto('Cher')->nombreCompleto())->toBe('Cher');
});

test('las atribuciones rechazan controles y textos fuera del límite sin truncar datos', function (string $valor): void {
    new AtribucionImagen(photographer: $valor);
})->throws(InvalidArgumentException::class)->with([str_repeat('A', 256), "Fotógrafo\nOtra línea", "Autor\0"]);

test('el tipo de vista se deduce solo de sufijos reconocidos y conserva los personalizados', function (): void {
    expect(AtribucionImagen::vistaDesdeNombre('MEPN-INV-34619_D.jpg'))->toBe('Dorsal view')
        ->and(AtribucionImagen::vistaDesdeSufijo('p'))->toBe('Profile view')
        ->and(AtribucionImagen::vistaDesdeSufijo('LD'))->toBe('Vista LD')
        ->and(AtribucionImagen::vistaDesdeNombre('sin-vista.jpg'))->toBe('');
});
