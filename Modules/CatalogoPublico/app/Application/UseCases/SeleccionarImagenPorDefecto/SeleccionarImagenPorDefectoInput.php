<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\SeleccionarImagenPorDefecto;

final readonly class SeleccionarImagenPorDefectoInput
{
    public function __construct(
        public string $nivel,
        public string $valorTaxon,
        public string $imagenId,
    ) {}
}
