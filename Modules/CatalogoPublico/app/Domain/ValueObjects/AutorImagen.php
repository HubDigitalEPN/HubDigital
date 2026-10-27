<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

use Modules\CatalogoPublico\Domain\Exceptions\AutorImagenInvalidoException;

final readonly class AutorImagen
{
    private function __construct(
        public string $nombre,
        public string $apellido,
    ) {}

    public static function crear(string $nombre, string $apellido): self
    {
        if (trim($nombre) === '' || trim($apellido) === '') {
            throw new AutorImagenInvalidoException(
                'La subida de una imagen exige el nombre y el apellido de quien la sube'
            );
        }

        return new self(trim($nombre), trim($apellido));
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombre.' '.$this->apellido);
    }

    /** Conserva nombres completos, monónimos y autoría todavía no informada. */
    public static function desdeNombreCompleto(string $nombre): self
    {
        return new self(trim($nombre), '');
    }
}
