<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

/** Contrato de referencia resuelta compartido por pantalla y exportación. */
final readonly class LocalidadInecPublica
{
    private function __construct(public ?string $nombre, public string $referencia) {}

    public static function desde(?string $nombreOficial, ?string $referencia): self
    {
        $nombre = trim($nombreOficial ?? '');
        if ($nombre === '') return new self(null, 'Sin correspondencia confirmada');

        return new self($nombre, trim($referencia ?? '') ?: 'Referencia INEC confirmada');
    }
}
