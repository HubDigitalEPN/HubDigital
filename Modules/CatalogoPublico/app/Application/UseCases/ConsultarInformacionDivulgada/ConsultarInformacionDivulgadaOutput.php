<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarInformacionDivulgada;

final readonly class ConsultarInformacionDivulgadaOutput
{
    /**
     * @param  array<string, scalar|null>  $datosVisibles
     * Solo incluye claves cuyos flags de visibilidad están habilitados.
     */
    private function __construct(
        public array $datosVisibles,
    ) {}

    /** @param array<string, scalar|null> $datos */
    public static function fromPrimitives(array $datos): self
    {
        return new self(datosVisibles: $datos);
    }
}
