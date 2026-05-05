<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ModificarConfiguracionDivulgacion;

final readonly class ModificarConfiguracionDivulgacionInput
{
    /**
     * @param  array<int, array{occurrenceID: string, configuracion: array<string, bool>}>  $especimenes
     * configuracion siempre requerida (no null) en este Use Case.
     */
    public function __construct(
        public string $curadorId,
        public array $especimenes,
    ) {}
}
