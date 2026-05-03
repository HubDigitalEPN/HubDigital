<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarInformacionDivulgada;

final readonly class ConsultarInformacionDivulgadaInput
{
    public function __construct(
        public string $occurrenceID,
    ) {}
}
