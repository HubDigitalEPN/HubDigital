<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\SeleccionarImagenPorDefecto;

final readonly class SeleccionarImagenPorDefectoOutput
{
    private function __construct(
        public string $nivel,
        public string $valorTaxon,
        public string $imagenId,
    ) {}

    public static function crear(string $nivel, string $valorTaxon, string $imagenId): self
    {
        return new self($nivel, $valorTaxon, $imagenId);
    }
}
