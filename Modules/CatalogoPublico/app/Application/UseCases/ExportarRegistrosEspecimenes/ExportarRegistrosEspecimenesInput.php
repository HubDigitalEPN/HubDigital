<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ExportarRegistrosEspecimenes;

final readonly class ExportarRegistrosEspecimenesInput
{
    public function __construct(
        public readonly string $especieNombre,
    ) {}
}
