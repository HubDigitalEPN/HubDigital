<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\SincronizarEspecimenes;

final readonly class SincronizarEspecimenesOutput
{
    /** @param list<string> $occurrenceIDsActualizados */
    private function __construct(
        public array $occurrenceIDsActualizados,
    ) {}

    /** @param list<string> $occurrenceIDs */
    public static function fromPrimitives(array $occurrenceIDs): self
    {
        return new self(occurrenceIDsActualizados: $occurrenceIDs);
    }
}
